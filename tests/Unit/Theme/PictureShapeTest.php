<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A product's picture takes one shape wherever the theme shows it: the one
 * the store picks for its product cards, which `Product\CardShape` prints as
 * a property. No stylesheet gives a picture a shape of its own, and no
 * template or pattern shapes a card's picture in the store's place.
 */
final class PictureShapeTest extends TestCase
{
    /** The shapes a rule may read: the product cards', and the product page's preview frame (`Product\PreviewFrame`). */
    private const SHAPE_PROPERTIES = ['--pixonaut-shop-card-aspect', '--ps-frame-aspect'];

    /**
     * Rules that shape something other than a product's picture, each with
     * why it keeps a shape of its own.
     */
    private const NOT_PRODUCT_PICTURES = [
        '.wp-block-pixonaut-shop-product-categories__picture'                  => "A category's picture.",
        '.wp-block-pixonaut-shop-category-carousel__picture'                   => "A category's picture.",
        '.wp-block-pixonaut-shop-group-tiles__picture'                         => "A group's picture.",
        '.pixonaut-shop-category-tiles .wc-block-product-categories-list-item__image' => "A category's picture.",
        '.pixonaut-shop-mega__picture'                                         => "A category's picture in the mega menu.",
    ];

    /** One rule with no rule inside it: its selector and its declarations. */
    private const RULE = '/([^{}]+)\{([^{}]*)\}/';

    private const ASPECT_RATIO = '/(?:^|;)\s*aspect-ratio\s*:\s*([^;]+)/';

    private const CSS_COMMENT = '#/\*.*?\*/#s';

    private const ROOT = __DIR__ . '/../../..';

    /** Where the theme's markup is: every block a store starts from. */
    private const MARKUP_DIRECTORIES = ['templates', 'parts', 'patterns'];

    /** A Product Image block's opening comment, with its attributes. */
    private const PICTURE_BLOCK = '/<!-- wp:woocommerce\/product-image (\{.*?\}) \/?-->/';

    public function testNoStylesheetGivesAProductPictureAShapeOfItsOwn(): void
    {
        $own = [];

        foreach ($this->stylesheets() as $file) {
            $css = (string) preg_replace(self::CSS_COMMENT, '', (string) file_get_contents($file));
            preg_match_all(self::RULE, $css, $rules, PREG_SET_ORDER);

            foreach ($rules as [, $selector, $declarations]) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
                preg_match_all(self::ASPECT_RATIO, $declarations, $values);

                foreach ($values[1] as $value) {
                    if (! self::reads_a_shape($value) && ! \array_key_exists($selector, self::NOT_PRODUCT_PICTURES)) {
                        $own[] = basename(\dirname($file)) . '/' . basename($file) . ': ' . $selector . ' { aspect-ratio: ' . trim($value) . ' }';
                    }
                }
            }
        }

        self::assertSame([], $own, 'A product picture takes the cards\' shape (`var(--pixonaut-shop-card-aspect)`); list a rule for another picture in NOT_PRODUCT_PICTURES with why.');
    }

    public function testEveryRuleListedAsAnotherPictureStillExists(): void
    {
        $css = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $this->stylesheets()));

        foreach (array_keys(self::NOT_PRODUCT_PICTURES) as $selector) {
            self::assertStringContainsString($selector, (string) preg_replace('/\s+/', ' ', $css), "{$selector} is gone; take it off the list.");
        }
    }

    public function testNoTemplateOrPatternShapesACardsPictureInTheStoresPlace(): void
    {
        $pictures = 0;

        foreach (self::MARKUP_DIRECTORIES as $directory) {
            foreach ((array) glob(self::ROOT . "/{$directory}/*.{html,php}", GLOB_BRACE) as $file) {
                preg_match_all(self::PICTURE_BLOCK, (string) file_get_contents((string) $file), $blocks);

                foreach ($blocks[1] as $attributes) {
                    ++$pictures;
                    $decoded = json_decode($attributes, true);
                    self::assertIsArray($decoded, (string) $file);
                    self::assertArrayNotHasKey('aspectRatio', $decoded, basename((string) $file) . ' shapes a card\'s picture.');
                    self::assertArrayNotHasKey('aspectRatio', \is_array($decoded['style']['dimensions'] ?? null) ? $decoded['style']['dimensions'] : [], basename((string) $file) . ' shapes a card\'s picture.');
                }
            }
        }

        self::assertGreaterThan(0, $pictures, 'No Product Image block was found to check.');
    }

    private static function reads_a_shape(string $value): bool
    {
        foreach (self::SHAPE_PROPERTIES as $property) {
            if (1 === preg_match('/^var\(\s*' . preg_quote($property, '/') . '\b/', trim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function stylesheets(): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/src')) as $file) {
            if ($file->isFile() && 'css' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
