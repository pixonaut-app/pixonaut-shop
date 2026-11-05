<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Tests\Unit\Theme\TemplatesTest;
use WP_Block_Patterns_Registry;
use WP_Block_Styles_Registry;
use WP_Theme_JSON_Resolver;

/**
 * WordPress, with WooCommerce active, finds the theme's templates, parts and
 * style variations: the files the unit suite checks are the ones a store
 * actually renders.
 */
final class BlockTemplatesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function templateSlugs(): iterable
    {
        foreach (TemplatesTest::TEMPLATES as $slug) {
            yield $slug => [$slug];
        }
    }

    #[DataProvider('templateSlugs')]
    public function testWordPressUsesTheThemesTemplate(string $slug): void
    {
        $template = get_block_template(get_stylesheet() . '//' . $slug);

        self::assertNotNull($template, "No {$slug} template.");
        self::assertSame('theme', $template->source);
        self::assertNotSame('', $template->content);
    }

    public function testWordPressUsesTheThemesParts(): void
    {
        foreach (TemplatesTest::PARTS as $slug) {
            $part = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');

            self::assertNotNull($part, "No {$slug} part.");
            self::assertSame('theme', $part->source);
        }
    }

    public function testNatureIsTheOnlyStyleVariationOffered(): void
    {
        $titles = array_map(
            static fn (array $variation): mixed => $variation['title'] ?? null,
            WP_Theme_JSON_Resolver::get_style_variations()
        );

        self::assertSame(['Nature'], $titles);
    }

    public function testThePatternsTemplatesUseAreRegistered(): void
    {
        $registry = WP_Block_Patterns_Registry::get_instance();

        foreach (array_keys(TemplatesTest::PATTERNS) as $slug) {
            self::assertTrue($registry->is_registered($slug), "{$slug} is not registered.");
        }
    }

    public function testOffersThePanelBandNoticeAndPillStyles(): void
    {
        $registry = WP_Block_Styles_Registry::get_instance();

        foreach (['panel', 'band-tint-1', 'band-tint-2', 'notice'] as $style) {
            self::assertTrue($registry->is_registered('core/group', $style), "core/group has no {$style} style.");
        }

        self::assertTrue($registry->is_registered('core/paragraph', 'pill'), 'core/paragraph has no pill style.');
        self::assertTrue($registry->is_registered('core/button', 'text'), 'core/button has no text style.');
    }
}
