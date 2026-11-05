<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\I18n\TextDomain;

/**
 * The theme's blocks' editor scripts in a Swedish store, translated from the
 * JSON catalogues `pnpm i18n:mo` writes beside sv_SE.mo. Like
 * ThemeSetupTest, it sets the site's own locale, since the tests site has no
 * Swedish core pack for switch_to_locale(); the scripts' catalogues are read
 * when a script loads, so the theme's MO catalogue is left as it is.
 */
final class BlockTranslationsTest extends TestCase
{
    /** @var callable(): string */
    private $swedish;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swedish = static fn (): string => 'sv_SE';
        add_filter('locale', $this->swedish);
    }

    protected function tearDown(): void
    {
        remove_filter('locale', $this->swedish);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function editorStrings(): iterable
    {
        yield 'the icon block' => ['pixonaut-shop/icon', 'Lastbil'];
        yield 'the product categories block' => ['pixonaut-shop/product-categories', 'Antal kategorier'];
    }

    #[DataProvider('editorStrings')]
    public function testTheEditorScriptReadsItsSwedishStrings(string $block, string $translation): void
    {
        $handle = generate_block_asset_handle($block, 'editorScript');
        self::assertTrue(wp_script_is($handle, 'registered'), "{$block} has no editor script; run pnpm build.");

        $catalogue = load_script_textdomain($handle, TextDomain::DOMAIN);

        self::assertIsString($catalogue, "No Swedish catalogue for {$block}'s editor script.");
        self::assertStringContainsString(
            (string) wp_json_encode($translation),
            $catalogue
        );
    }
}
