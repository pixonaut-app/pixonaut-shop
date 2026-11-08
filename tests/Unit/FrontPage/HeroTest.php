<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\FrontPage;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\FrontPage\Hero;
use Pixonaut\Shop\Tests\TestCase;

/**
 * The hero a store's `pixonaut_shop_hero` option describes, and
 * `patterns/hero.php`, which shows it, or its placeholders while the option
 * is unset or not a hero.
 */
#[CoversClass(Hero::class)]
final class HeroTest extends TestCase
{
    private const PATTERN = __DIR__ . '/../../../patterns/hero.php';

    private const IMAGE_ID = 31;

    private const IMAGE_URL = 'https://store.example/wp-content/uploads/hero.webp';

    private const IMAGE_ALT = 'Gifts on a table';

    private const SHOP_URL = 'https://store.example/shop/';

    private const HERO = [
        'heading' => 'Gifts with a name on them',
        'text' => 'Mugs, prints and tees made for one person.',
        'button_label' => 'See the gifts',
        'button_url' => self::SHOP_URL,
        'image_id' => self::IMAGE_ID,
    ];

    /** A heading that would run as a script if it were printed as markup. */
    private const SCRIPT = '<script>alert("x")</script>';

    /** Today's hero's placeholders, as their English source strings. */
    private const PLACEHOLDERS = ['News', 'A heading about what you sell', 'One or two sentences about the store.', 'Shop now', 'See more'];

    /** Where a button block opens, with attributes or without. */
    private const BUTTON = '<!-- wp:button ';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        Functions\when('get_post_type_archive_link')->justReturn(self::SHOP_URL);
        Functions\when('wp_attachment_is_image')->alias(static fn (int $id): bool => self::IMAGE_ID === $id);
        Functions\when('wp_get_attachment_image_url')->alias(
            static fn (int $id, string $size): string|false => self::IMAGE_ID === $id && 'full' === $size ? self::IMAGE_URL : false
        );
        Functions\when('get_post_meta')->alias(
            static fn (int $id, string $key, bool $single): string => self::IMAGE_ID === $id && '_wp_attachment_image_alt' === $key && $single ? self::IMAGE_ALT : ''
        );
    }

    public function testReadsTheHeroFromItsOption(): void
    {
        Functions\expect('get_option')->once()->with('pixonaut_shop_hero')->andReturn(self::HERO);

        $hero = Hero::from_option();

        self::assertInstanceOf(Hero::class, $hero);
        self::assertSame(self::HERO['heading'], $hero->heading);
        self::assertSame(self::HERO['text'], $hero->text);
        self::assertSame(self::HERO['button_label'], $hero->button_label);
        self::assertSame(self::SHOP_URL, $hero->button_url);
        self::assertSame(self::IMAGE_ID, $hero->image_id);
        self::assertSame(self::IMAGE_URL, $hero->image_url);
        self::assertSame(self::IMAGE_ALT, $hero->image_alt);
    }

    /**
     * Option values that are not a hero, which the hero leaves aside.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function notAHero(): iterable
    {
        yield 'unset' => [false];
        yield 'a string' => ['Gifts with a name on them'];
        yield 'an empty array' => [[]];

        foreach (array_keys(self::HERO) as $key) {
            $hero = self::HERO;
            unset($hero[$key]);

            yield "without {$key}" => [$hero];
        }

        yield 'a heading that is not a string' => [['heading' => 1] + self::HERO];
        yield 'a text that is not a string' => [['text' => null] + self::HERO];
        yield 'a button label that is not a string' => [['button_label' => ['See']] + self::HERO];
        yield 'a button URL that is not a string' => [['button_url' => false] + self::HERO];
        yield 'an image ID as a string' => [['image_id' => (string) self::IMAGE_ID] + self::HERO];
        yield 'an image ID that is not an image' => [['image_id' => self::IMAGE_ID + 1] + self::HERO];
        yield 'an empty heading' => [['heading' => ''] + self::HERO];
        yield 'a heading of only spaces' => [['heading' => " \t\n"] + self::HERO];
        yield 'an empty button label' => [['button_label' => ''] + self::HERO];
        yield 'a button label of only spaces' => [['button_label' => '  '] + self::HERO];
    }

    #[DataProvider('notAHero')]
    public function testLeavesAnOptionThatIsNotAHeroAside(mixed $value): void
    {
        Functions\when('get_option')->justReturn($value);

        self::assertNull(Hero::from_option());
    }

    /** An address WordPress would not link to, such as a `javascript:` one, or none at all. */
    public function testLeavesAHeroWhoseButtonGoesNowhereAside(): void
    {
        Functions\when('get_option')->justReturn(self::HERO);
        Functions\when('esc_url_raw')->justReturn('');

        self::assertNull(Hero::from_option());
    }

    public function testLeavesAHeroWhosePictureHasNoFileAside(): void
    {
        Functions\when('get_option')->justReturn(self::HERO);
        Functions\when('wp_get_attachment_image_url')->justReturn(false);

        self::assertNull(Hero::from_option());
    }

    public function testHeroRendersTheOptionsHeadingTextOneButtonAndImage(): void
    {
        Functions\when('get_option')->justReturn(self::HERO);

        $markup = $this->render();

        self::assertStringContainsString('<h1 class="wp-block-heading">' . self::HERO['heading'] . '</h1>', $markup);
        self::assertStringContainsString('<p>' . self::HERO['text'] . '</p>', $markup);
        self::assertSame(1, substr_count($markup, self::BUTTON), 'The hero has more than one button.');
        self::assertStringContainsString('href="' . self::SHOP_URL . '">' . self::HERO['button_label'] . '</a>', $markup);
        self::assertStringContainsString('"mediaId":' . self::IMAGE_ID . ',"mediaType":"image"', $markup);
        self::assertStringContainsString(
            '<figure class="wp-block-media-text__media"><img src="' . self::IMAGE_URL . '" alt="' . self::IMAGE_ALT . '" class="wp-image-' . self::IMAGE_ID . ' size-full"/></figure>',
            $markup
        );
        self::assertStringContainsString('pixonaut-shop-hero', $markup);
        self::assertStringContainsString('"templateLock":"contentOnly"', $markup);

        foreach (self::PLACEHOLDERS as $placeholder) {
            self::assertStringNotContainsString('>' . $placeholder . '<', $markup);
        }
    }

    public function testHeroWithoutTheOptionIsUnchanged(): void
    {
        Functions\when('get_option')->justReturn(false);

        $markup = $this->render();

        foreach (self::PLACEHOLDERS as $placeholder) {
            self::assertStringContainsString('>' . $placeholder . '<', $markup);
        }
        self::assertStringContainsString('<p class="is-style-pill">', $markup);
        self::assertSame(2, substr_count($markup, self::BUTTON));
        self::assertStringContainsString('href="' . self::SHOP_URL . '">Shop now</a>', $markup);
        // The store chooses the picture.
        self::assertStringContainsString('<figure class="wp-block-media-text__media"></figure>', $markup);
        self::assertStringNotContainsString('"mediaId"', $markup);
        self::assertStringNotContainsString('<img', $markup);
    }

    #[DataProvider('notAHero')]
    public function testHeroWithAnOptionThatIsNotAHeroIsUnchanged(mixed $value): void
    {
        Functions\when('get_option')->justReturn(false);
        $unchanged = $this->render();

        Functions\when('get_option')->justReturn($value);

        self::assertSame($unchanged, $this->render());
    }

    public function testHeroEscapesItsText(): void
    {
        $hostile = [
            'heading' => self::SCRIPT,
            'text' => self::SCRIPT,
            'button_label' => self::SCRIPT,
        ] + self::HERO;
        Functions\when('get_option')->justReturn($hostile);
        Functions\when('get_post_meta')->justReturn('"><script>alert("x")</script>');

        $markup = $this->render();

        self::assertStringNotContainsString('<script', $markup);
        self::assertStringContainsString('<h1 class="wp-block-heading">' . htmlspecialchars(self::SCRIPT, \ENT_QUOTES) . '</h1>', $markup);
        self::assertStringContainsString('alt="&quot;&gt;&lt;script&gt;', $markup);
    }

    private function render(): string
    {
        ob_start();
        require self::PATTERN;

        return (string) ob_get_clean();
    }
}
