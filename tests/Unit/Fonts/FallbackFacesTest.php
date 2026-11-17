<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Fonts;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Fonts\FallbackFaces;
use Pixonaut\Shop\Fonts\FontMetrics;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(FallbackFaces::class)]
#[CoversClass(FontMetrics::class)]
final class FallbackFacesTest extends TestCase
{
    private const STORE_STACK = '"Nunito", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';

    private const NAMED_STACK = '"Nunito", "Nunito Fallback Arial", "Nunito Fallback Roboto", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';

    /**
     * A store's family as the Font Library and a store's Styles write it.
     *
     * @param list<string> $weights
     * @return array<string, mixed>
     */
    private static function family(string $stack = self::STORE_STACK, array $weights = ['400', '800']): array
    {
        return [
            'slug'       => 'body',
            'name'       => 'Nunito',
            'fontFamily' => $stack,
            'fontFace'   => array_map(
                static fn (string $weight): array => [
                    'fontFamily' => 'Nunito',
                    'fontStyle'  => 'normal',
                    'fontWeight' => $weight,
                    'src'        => ['https://example.test/wp-content/uploads/fonts/nunito-' . $weight . '.woff2'],
                ],
                $weights
            ),
        ];
    }

    public function testAStoreFamilyNamesItsFallbacksRightAfterTheTypeface(): void
    {
        $families = (new FallbackFaces())->with_fallbacks([self::family()]);

        self::assertIsArray($families[0]);
        self::assertSame(self::NAMED_STACK, $families[0]['fontFamily']);
    }

    public function testFamiliesKeyedByOriginAreEachNamed(): void
    {
        $families = (new FallbackFaces())->with_fallbacks(['custom' => [self::family()]]);

        self::assertSame(self::NAMED_STACK, $families['custom'][0]['fontFamily'] ?? null);
    }

    public function testAStackThatNamesItsOwnFallbackIsLeftAlone(): void
    {
        $stack    = '"Inter", "Inter Fallback", system-ui, sans-serif';
        $families = (new FallbackFaces())->with_fallbacks(
            [['fontFamily' => $stack, 'fontFace' => [['fontFamily' => 'Inter', 'fontWeight' => '400']]]]
        );

        self::assertSame($stack, $families[0]['fontFamily'] ?? null);
    }

    public function testASystemFontWithNoFacesToLoadIsLeftAlone(): void
    {
        $stack    = 'Arial, sans-serif';
        $families = (new FallbackFaces())->with_fallbacks([['fontFamily' => $stack]]);

        self::assertSame($stack, $families[0]['fontFamily'] ?? null);
    }

    public function testATypefaceTheTableDoesNotKnowIsLeftAlone(): void
    {
        $stack    = '"Store Brand Sans", sans-serif';
        $families = (new FallbackFaces())->with_fallbacks(
            [['fontFamily' => $stack, 'fontFace' => [['fontFamily' => 'Store Brand Sans', 'fontWeight' => '400']]]]
        );

        self::assertSame($stack, $families[0]['fontFamily'] ?? null);
    }

    public function testAHeadingFamilyWithoutFacesIsNamedWhenAnotherFamilyLoadsTheTypeface(): void
    {
        $heading  = ['slug' => 'heading', 'fontFamily' => self::STORE_STACK];
        $families = (new FallbackFaces())->with_fallbacks([self::family(), $heading]);

        self::assertSame(self::NAMED_STACK, $families[1]['fontFamily'] ?? null);
    }

    public function testEachWeightGetsAFaceOnEachLocalFont(): void
    {
        $css = (new FallbackFaces())->css([self::family(self::NAMED_STACK)]);

        self::assertSame(4, substr_count($css, '@font-face'));
        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:400;src:local("Arial"),local("Liberation Sans"),local("Arimo");', $css);
        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:800;src:local("Arial Bold"),local("Liberation Sans Bold"),local("Arimo Bold");', $css);
        self::assertStringContainsString('font-family:"Nunito Fallback Roboto";font-style:normal;font-weight:800;src:local("Roboto Bold"),local("Roboto-Bold");', $css);
    }

    public function testAFaceTakesTheTypefacesRoom(): void
    {
        // Nunito ExtraBold against Arial Bold: average widths 481/1000 and
        // 983/2048, ascent 1011 and descent 353 of 1000, no line gap.
        $css = (new FallbackFaces())->css([self::family(self::NAMED_STACK, ['800'])]);

        self::assertStringContainsString(
            'font-weight:800;src:local("Arial Bold"),local("Liberation Sans Bold"),local("Arimo Bold");size-adjust:100.21%;ascent-override:100.89%;descent-override:35.23%;line-gap-override:0%}',
            $css
        );
    }

    public function testTheLocalFaceIsMeasuredAtTheWeightItIs(): void
    {
        // Nunito ExtraBold (481/1000) on Roboto Bold, the 700 face local()
        // loads (926/2048), not on Roboto's own 800 (930/2048).
        $css = (new FallbackFaces())->css([self::family(self::NAMED_STACK, ['800'])]);

        self::assertStringContainsString('src:local("Roboto Bold"),local("Roboto-Bold");size-adjust:106.38%;', $css);
    }

    public function testAWeightKeywordIsTheWeightCssGivesIt(): void
    {
        $css = (new FallbackFaces())->css([self::family(self::NAMED_STACK, ['bold'])]);

        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:700;src:local("Arial Bold")', $css);
    }

    public function testAVariableFaceGetsARegularAndABoldFallback(): void
    {
        $css = (new FallbackFaces())->css([self::family(self::NAMED_STACK, ['200 900'])]);

        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:200 500;src:local("Arial")', $css);
        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:600 900;src:local("Arial Bold")', $css);
    }

    public function testASerifTypefaceFallsBackOnSerifFonts(): void
    {
        $stack    = '"Lora", serif';
        $face     = ['fontFamily' => 'Lora', 'fontStyle' => 'normal', 'fontWeight' => '400'];
        $faces    = new FallbackFaces();
        $families = $faces->with_fallbacks([['fontFamily' => $stack, 'fontFace' => [$face]]]);
        $named    = $families[0]['fontFamily'] ?? '';

        self::assertSame('"Lora", "Lora Fallback Times New Roman", "Lora Fallback Noto Serif", serif', $named);
        self::assertStringContainsString('src:local("Times New Roman"),local("Liberation Serif"),local("Tinos")', $faces->css([['fontFamily' => $named, 'fontFace' => [$face]]]));
    }

    public function testItalicFacesGetNoFallbackOfTheirOwn(): void
    {
        $family                           = self::family(self::NAMED_STACK, ['400']);
        $family['fontFace'][0]['fontStyle'] = 'italic';

        self::assertSame('', (new FallbackFaces())->css([$family]));
    }

    public function testFamiliesThatNameNoGeneratedFallbackGetNoFaces(): void
    {
        self::assertSame('', (new FallbackFaces())->css([self::family()]));
    }

    public function testTheThemesOwnFamiliesAreLeftAsTheyAre(): void
    {
        $theme    = json_decode((string) file_get_contents(__DIR__ . '/../../../theme.json'), true);
        $families = $theme['settings']['typography']['fontFamilies'] ?? null;

        self::assertIsArray($families);
        self::assertSame($families, (new FallbackFaces())->with_fallbacks($families));
    }
}
