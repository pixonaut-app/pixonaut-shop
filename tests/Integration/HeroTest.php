<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\FrontPage\Hero;
use WP_Block_Patterns_Registry;

/**
 * The hero as WordPress renders the `pixonaut-shop/hero` pattern: the hero
 * the store's `pixonaut_shop_hero` option describes, or the pattern's
 * placeholders while the option is unset or not a hero.
 */
final class HeroTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/hero';

    /** A reference to the pattern, as the front page pattern holds it. */
    private const REFERENCE = '<!-- wp:pattern {"slug":"' . self::PATTERN . '"} /-->';

    private const IMAGE_FILE = '2026/11/hero.webp';

    private const IMAGE_ALT = 'Gifts on a table';

    private const HEADING = 'Gifts with a name on them';

    private const TEXT = 'Mugs, prints and tees made for one person.';

    private const BUTTON_LABEL = 'See the gifts';

    /** A heading that would run as a script if it were printed as markup. */
    private const SCRIPT = '<script>alert("x")</script>';

    private const PLACEHOLDER_HEADING = 'A heading about what you sell';

    /** @var list<int> */
    private array $attachments = [];

    protected function tearDown(): void
    {
        delete_option(Hero::OPTION);

        foreach ($this->attachments as $attachment) {
            wp_delete_attachment($attachment, true);
        }
        $this->attachments = [];
    }

    public function testHeroRendersTheOptionsHeadingTextOneButtonAndImage(): void
    {
        $image = $this->image(self::IMAGE_FILE, 'image/webp');
        $shop  = home_url('/shop/');
        update_option(Hero::OPTION, self::hero($image, ['button_url' => $shop]));

        $html = self::render();

        self::assertStringContainsString('<h1 class="wp-block-heading">' . self::HEADING . '</h1>', $html);
        self::assertStringContainsString('>' . self::TEXT . '</p>', $html);
        self::assertSame(1, substr_count($html, 'wp-block-button__link'), 'The hero has more than one button.');
        self::assertStringContainsString('href="' . esc_url($shop) . '">' . self::BUTTON_LABEL . '</a>', $html);
        self::assertMatchesRegularExpression(
            '/<img\b[^>]*\bsrc="' . preg_quote(esc_url((string) wp_get_attachment_image_url($image, 'full')), '/') . '"[^>]*>/',
            $html
        );
        self::assertStringContainsString('alt="' . self::IMAGE_ALT . '"', $html);
        self::assertStringContainsString('wp-image-' . $image, $html);
        self::assertStringNotContainsString(self::PLACEHOLDER_HEADING, $html);
        self::assertStringNotContainsString('is-style-pill', $html);
    }

    public function testHeroWithoutTheOptionIsUnchanged(): void
    {
        delete_option(Hero::OPTION);

        $html = self::render();

        self::assertStringContainsString('<h1 class="wp-block-heading">' . self::PLACEHOLDER_HEADING . '</h1>', $html);
        self::assertStringContainsString('is-style-pill', $html);
        self::assertSame(2, substr_count($html, 'wp-block-button__link'));
        self::assertStringContainsString('<figure class="wp-block-media-text__media"></figure>', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testAHeroWhosePictureIsNotAnImageRendersThePlaceholders(): void
    {
        $unchanged = self::content();
        $document  = $this->image('2026/11/terms.pdf', 'application/pdf');
        update_option(Hero::OPTION, self::hero($document));

        self::assertSame($unchanged, self::content());
    }

    public function testAHeroMissingAFieldRendersThePlaceholders(): void
    {
        $unchanged = self::content();
        $hero      = self::hero($this->image(self::IMAGE_FILE, 'image/webp'));
        unset($hero['text']);
        update_option(Hero::OPTION, $hero);

        self::assertSame($unchanged, self::content());
    }

    public function testHeroEscapesItsText(): void
    {
        update_option(Hero::OPTION, self::hero($this->image(self::IMAGE_FILE, 'image/webp'), [
            'heading'      => self::SCRIPT,
            'text'         => self::SCRIPT,
            'button_label' => self::SCRIPT,
            'button_url'   => 'javascript:alert(1)',
        ]));

        $html = self::render();

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<h1 class="wp-block-heading">&lt;script&gt;', $html);
    }

    /** The pattern as a page that holds a reference to it renders it. */
    private static function render(): string
    {
        self::content();

        return do_blocks(self::REFERENCE);
    }

    /**
     * The pattern's block markup, as the editor inserts it. The registry
     * keeps a pattern file's output once it has read it, so the pattern is
     * read from its file again here, as each request does.
     */
    private static function content(): string
    {
        $registry = WP_Block_Patterns_Registry::get_instance();
        $pattern  = $registry->get_registered(self::PATTERN);
        self::assertIsArray($pattern, self::PATTERN . ' is not registered.');

        unset($pattern['content'], $pattern['name']);
        $registry->unregister(self::PATTERN);
        $registry->register(self::PATTERN, $pattern + ['filePath' => get_theme_file_path('patterns/hero.php')]);

        $fresh = $registry->get_registered(self::PATTERN);
        self::assertIsArray($fresh);

        return $fresh['content'];
    }

    /**
     * The option's value for a hero showing the attachment.
     *
     * @param array<string, string> $changes
     *
     * @return array<string, int|string>
     */
    private static function hero(int $image, array $changes = []): array
    {
        return $changes + [
            'heading'      => self::HEADING,
            'text'         => self::TEXT,
            'button_label' => self::BUTTON_LABEL,
            'button_url'   => home_url('/'),
            'image_id'     => $image,
        ];
    }

    /** An attachment of the file and type given, its alt text set; no file is written. */
    private function image(string $file, string $type): int
    {
        $id = wp_insert_attachment(['post_mime_type' => $type, 'post_title' => basename($file), 'post_status' => 'inherit'], $file, 0, true);
        self::assertIsInt($id);
        $this->attachments[] = $id;
        update_post_meta($id, '_wp_attachment_image_alt', self::IMAGE_ALT);

        return $id;
    }
}
