<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Header;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Archive\CategoryImage;
use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Header\MegaMenu;
use Pixonaut\Shop\Header\NamedMenus;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(MegaMenu::class)]
#[CoversClass(CategoryImage::class)]
#[CoversClass(HeaderVariant::class)]
#[CoversClass(NamedMenus::class)]
#[CoversClass(BuiltScript::class)]
final class MegaMenuTest extends TestCase
{
    /** The menu saved under the header's name. */
    private const MENU_ID = 42;

    /** A menu the store picked for the header's block in the Site Editor. */
    private const PICKED_MENU_ID = 7;

    /** Product categories, by term ID, with the attachment each one's picture is, 0 for none. */
    private const BIRTHDAY = 11;

    private const WEDDING = 12;

    private const FOR_HER = 21;

    private const PICTURES = [self::BIRTHDAY => 301, self::WEDDING => 0, self::FOR_HER => 302];

    private const SHOP_URL = 'https://shop.test';

    private const BUILT_THEME = __DIR__ . '/fixtures/built';

    private const UNBUILT_THEME = __DIR__ . '/fixtures/unbuilt';

    /** @var array<int, list<array<string, mixed>>> The parsed blocks each menu holds. */
    private array $menus = [];

    /** @var ArrayObject<int, string> Each cache priming and lookup of a category, in order. */
    private ArrayObject $lookups;

    protected function setUp(): void
    {
        parent::setUp();
        NamedMenus::forget();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        Functions\when('wp_strip_all_tags')->alias(static fn (string $text): string => strip_tags($text));
        Functions\when('get_post_status')->alias(fn (int $id): string|false => isset($this->menus[$id]) ? 'publish' : false);
        Functions\when('get_post_field')->alias(static fn (string $field, int $id): string => "menu:{$id}");
        Functions\when('parse_blocks')->alias(fn (string $content): array => $this->menus[(int) substr($content, \strlen('menu:'))] ?? []);
        /** @var ArrayObject<int, string> $lookups */
        $lookups       = new ArrayObject();
        $this->lookups = $lookups;
        Functions\when('get_term_link')->alias(static function (int $term, string $taxonomy) use ($lookups): ?string {
            $lookups->append("link {$term}");

            return isset(self::PICTURES[$term]) && 'product_cat' === $taxonomy
                ? self::SHOP_URL . "/product-category/{$term}/"
                : null; // Core answers a WP_Error, which is not a string either.
        });
        Functions\when('get_term_meta')->alias(static fn (int $term, string $key): string => 'thumbnail_id' === $key ? (string) (self::PICTURES[$term] ?? '') : '');
        foreach (['_prime_term_caches', 'update_termmeta_cache', '_prime_post_caches'] as $primer) {
            Functions\when($primer)->alias(static function (array $ids) use ($lookups, $primer): void {
                $lookups->append($primer . ' ' . implode(',', $ids));
            });
        }
        // Each ID once per prefix, as core counts them across the request.
        $counter = new ArrayObject();
        Functions\when('wp_unique_id')->alias(static function (string $prefix) use ($counter): string {
            $counter[$prefix] = ($counter[$prefix] ?? 0) + 1;

            return $prefix . $counter[$prefix];
        });
        Functions\when('wp_get_attachment_image')->alias(static function (int $id, string $size, bool $icon, array $attributes): string {
            ksort($attributes);

            return "<img data-id=\"{$id}\" data-size=\"{$size}\" data-attributes='" . json_encode($attributes) . "'>";
        });
        $this->theme(self::UNBUILT_THEME);
        $this->menus[self::MENU_ID] = self::sample_menu();
    }

    public function testRegistersBeforeABlockRenders(): void
    {
        (new MegaMenu(new NamedMenus()))->register();

        self::assertNotFalse(has_filter('pre_render_block', 'Pixonaut\Shop\Header\MegaMenu->render()'));
    }

    public function testTheSimpleHeaderKeepsCoresMenu(): void
    {
        $this->header(HeaderVariant::SIMPLE);

        self::assertNull(self::render(self::header_block()));
    }

    public function testTheMegaHeaderLeavesOtherBlocksAlone(): void
    {
        $this->header(HeaderVariant::MEGA);

        self::assertNull(self::render(['blockName' => 'core/paragraph', 'attrs' => []]));
        self::assertNull(self::render(self::navigation('pixonaut-shop-footer__links')));
        self::assertSame('<p>Earlier</p>', self::render(self::header_block(), '<p>Earlier</p>'));
    }

    public function testTheMegaHeaderIsANavigationLandmark(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringStartsWith('<nav class="pixonaut-shop-mega" aria-label="Main menu">', $html);
        self::assertStringEndsWith('</nav>', $html);
    }

    /** The browser opens and closes the panel, a popover, from the button, with no script. */
    public function testTheMenuButtonOpensThePanelAsAPopover(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertMatchesRegularExpression(
            '/<button type="button" class="pixonaut-shop-mega__toggle" popovertarget="pixonaut-shop-mega-1" aria-controls="pixonaut-shop-mega-1" aria-label="Menu">/',
            $html
        );
        self::assertStringContainsString('<div id="pixonaut-shop-mega-1" class="pixonaut-shop-mega__panel" popover>', $html);
        self::assertMatchesRegularExpression(
            '/<button type="button" class="pixonaut-shop-mega__close" popovertarget="pixonaut-shop-mega-1" popovertargetaction="hide" aria-label="Close menu">/',
            $html
        );
        self::assertStringNotContainsString('role="dialog"', $html);
        self::assertStringNotContainsString(' hidden', $html);
    }

    public function testEachRenderHasItsOwnIds(): void
    {
        $this->header(HeaderVariant::MEGA);

        $first  = (string) self::render(self::header_block());
        $second = (string) self::render(self::header_block());

        self::assertStringContainsString('id="pixonaut-shop-mega-1"', $first);
        self::assertStringContainsString('id="pixonaut-shop-mega-2"', $second);
        self::assertStringContainsString('aria-controls="pixonaut-shop-mega-2"', $second);
    }

    /**
     * Every group's items show until the script picks one: without it, the
     * panel lists them all.
     */
    public function testEachGroupIsAButtonForItsChildren(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertSame(['Occasion', 'For whom', 'Customer service'], self::group_buttons($html));
        self::assertMatchesRegularExpression(
            '/<button type="button" class="pixonaut-shop-mega__group" id="pixonaut-shop-mega-group-1" aria-expanded="true" aria-controls="pixonaut-shop-mega-items-1">/',
            $html
        );
        self::assertStringContainsString('<div class="pixonaut-shop-mega__children" id="pixonaut-shop-mega-items-1" role="group" aria-labelledby="pixonaut-shop-mega-group-1">', $html);
        self::assertStringContainsString('<div class="pixonaut-shop-mega__children" id="pixonaut-shop-mega-items-2" role="group" aria-labelledby="pixonaut-shop-mega-group-2">', $html);
        self::assertSame(3, substr_count($html, 'aria-expanded="true"'));
    }

    /**
     * The categories' terms, their meta and their pictures are each loaded
     * in one query, before any category is looked at.
     */
    public function testLoadsTheCategoriesAndTheirPicturesTogether(): void
    {
        $this->header(HeaderVariant::MEGA);

        self::render(self::header_block());

        $categories = self::BIRTHDAY . ',' . self::WEDDING . ',' . self::FOR_HER;
        self::assertSame(
            [
                "_prime_term_caches {$categories}",
                "update_termmeta_cache {$categories}",
                '_prime_post_caches ' . self::PICTURES[self::BIRTHDAY] . ',' . self::PICTURES[self::FOR_HER],
            ],
            \array_slice($this->lookups->getArrayCopy(), 0, 3)
        );
        self::assertCount(6, $this->lookups, 'A category is primed more than once or looked up more than once.');
    }

    public function testAMenuWithoutCategoriesPrimesNothing(): void
    {
        $this->header(HeaderVariant::MEGA);
        $this->menus[self::MENU_ID] = [self::submenu('Help', '', [self::link('Contact', '/contact/')])];

        self::render(self::header_block());

        self::assertCount(0, $this->lookups);
    }

    public function testATopItemWithoutChildrenIsALink(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString('<a class="pixonaut-shop-mega__group" href="https://shop.test/sale/">Sale</a>', $html);
        self::assertStringContainsString('style="--pixonaut-shop-mega-rows:4"', $html);
    }

    public function testAGroupLinksToAllOfItsOwnPage(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString('<a class="pixonaut-shop-mega__all" href="https://shop.test/occasions/">All of Occasion</a>', $html);
        self::assertSame(1, substr_count($html, 'pixonaut-shop-mega__all'), 'A group without a page of its own links to one.');
    }

    public function testACategoryIsATileWithItsPicture(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString(
            '<li><a class="pixonaut-shop-mega__tile" href="https://shop.test/product-category/11/">'
            . '<span class="pixonaut-shop-mega__picture"><img data-id="301" data-size="woocommerce_thumbnail" '
            . 'data-attributes=\'{"alt":"","decoding":"async","loading":"lazy","sizes":"(max-width: 781px) 3rem, 10rem"}\'></span>'
            . '<span class="pixonaut-shop-mega__name">Birthday</span></a></li>',
            $html
        );
    }

    public function testACategoryWithoutAPictureAndALinkInATileGroupHaveAnEmptyPicture(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString(
            '<a class="pixonaut-shop-mega__tile" href="https://shop.test/product-category/12/"><span class="pixonaut-shop-mega__picture is-empty"></span><span class="pixonaut-shop-mega__name">Wedding</span>',
            $html
        );
        self::assertStringContainsString(
            '<a class="pixonaut-shop-mega__tile" href="https://shop.test/gift-ideas/"><span class="pixonaut-shop-mega__picture is-empty"></span><span class="pixonaut-shop-mega__name">Gift ideas</span>',
            $html
        );
    }

    public function testAGroupWithoutCategoriesListsItsLinks(): void
    {
        $this->header(HeaderVariant::MEGA);

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString(
            '<ul class="pixonaut-shop-mega__links"><li><a href="https://shop.test/contact/">Contact</a></li><li><a href="https://shop.test/returns/">Returns</a></li></ul>',
            $html
        );
    }

    public function testLabelsAreText(): void
    {
        $this->header(HeaderVariant::MEGA);
        $this->menus[self::MENU_ID] = [self::submenu('<strong>Tea &amp; cups</strong>', '', [self::link('Mugs <em>new</em>', '/mugs/')])];

        $html = (string) self::render(self::header_block());

        self::assertStringContainsString('>Tea &amp; cups<', $html);
        self::assertStringContainsString('>Mugs new<', $html);
        self::assertStringNotContainsString('<strong>', $html);
    }

    public function testAMenuPickedInTheSiteEditorIsTheOneShown(): void
    {
        $this->header(HeaderVariant::MEGA);
        $this->menus[self::PICKED_MENU_ID] = [self::submenu('Picked', '', [self::link('Picked link', '/picked/')])];

        $html = (string) self::render(self::header_block(self::PICKED_MENU_ID));

        self::assertSame(['Picked'], self::group_buttons($html));
    }

    public function testWithoutAMenuUnderTheHeadersNameCoreShowsItsFallback(): void
    {
        $this->header(HeaderVariant::MEGA, null);

        self::assertNull(self::render(self::header_block()));
    }

    public function testAMenuWithoutLinksLeavesCoresMenu(): void
    {
        $this->header(HeaderVariant::MEGA);
        $this->menus[self::MENU_ID] = [['blockName' => 'core/page-list', 'attrs' => [], 'innerBlocks' => []]];

        self::assertNull(self::render(self::header_block()));
    }

    public function testLoadsTheMenusScript(): void
    {
        $this->header(HeaderVariant::MEGA);
        $this->theme(self::BUILT_THEME);
        /** @var ArrayObject<int, string> $handles */
        $handles = new ArrayObject();
        Functions\when('wp_enqueue_script')->alias(static function (string $handle) use ($handles): void {
            $handles->append($handle);
        });

        self::render(self::header_block());

        self::assertSame([MegaMenu::SCRIPT_HANDLE], $handles->getArrayCopy());
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function render(array $block, ?string $pre_render = null): ?string
    {
        return (new MegaMenu(new NamedMenus()))->render($pre_render, $block);
    }

    /** The header the option asks for, and the menu saved under the header's name. */
    private function header(string $variant, ?int $named_menu = self::MENU_ID): void
    {
        Functions\when('get_option')->alias(static fn (string $option): mixed => HeaderVariant::OPTION === $option ? $variant : false);
        Functions\when('get_posts')->justReturn(null === $named_menu ? [] : [$named_menu]);
    }

    private function theme(string $path): void
    {
        Functions\when('get_theme_file_path')->alias(static fn (string $file): string => $path . '/' . $file);
        Functions\when('get_theme_file_uri')->alias(static fn (string $file): string => self::SHOP_URL . '/' . $file);
    }

    /**
     * @return list<string>
     */
    private static function group_buttons(string $html): array
    {
        preg_match_all('/<button type="button" class="pixonaut-shop-mega__group"[^>]*>([^<]*)</', $html, $matches);

        return $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private static function header_block(?int $ref = null): array
    {
        $block = self::navigation('pixonaut-shop-header__menu');

        if (null !== $ref) {
            $block['attrs']['ref'] = $ref;
        }

        return $block;
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>, innerBlocks: list<mixed>}
     */
    private static function navigation(string $class_name): array
    {
        return ['blockName' => 'core/navigation', 'attrs' => ['className' => $class_name], 'innerBlocks' => []];
    }

    /**
     * A menu as the Navigation screen saves one: groups of categories, a
     * plain link at the top, and a group of plain links.
     *
     * @return list<array<string, mixed>>
     */
    private static function sample_menu(): array
    {
        return [
            self::submenu('Occasion', self::SHOP_URL . '/occasions/', [
                self::category('Birthday', self::BIRTHDAY),
                self::category('Wedding', self::WEDDING),
                self::link('Gift ideas', self::SHOP_URL . '/gift-ideas/'),
            ]),
            self::submenu('For whom', '', [self::category('For her', self::FOR_HER)]),
            self::link('Sale', self::SHOP_URL . '/sale/'),
            self::submenu('Customer service', '#', [
                self::link('Contact', self::SHOP_URL . '/contact/'),
                self::link('Returns', self::SHOP_URL . '/returns/'),
            ]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private static function submenu(string $label, string $url, array $children): array
    {
        return ['blockName' => 'core/navigation-submenu', 'attrs' => ['label' => $label, 'url' => $url], 'innerBlocks' => $children];
    }

    /**
     * @return array<string, mixed>
     */
    private static function link(string $label, string $url): array
    {
        return ['blockName' => 'core/navigation-link', 'attrs' => ['label' => $label, 'url' => $url, 'kind' => 'custom'], 'innerBlocks' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private static function category(string $label, int $term): array
    {
        return [
            'blockName' => 'core/navigation-link',
            'attrs' => ['label' => $label, 'url' => self::SHOP_URL . '/stale/', 'kind' => 'taxonomy', 'type' => 'product_cat', 'id' => $term],
            'innerBlocks' => [],
        ];
    }
}
