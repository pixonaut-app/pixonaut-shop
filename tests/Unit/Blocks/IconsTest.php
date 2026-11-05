<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Blocks\Icons;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(Icons::class)]
final class IconsTest extends TestCase
{
    /** The icons a store picks from, in the order the editor lists them. */
    private const NAMES = [
        'account',
        'bag',
        'search',
        'menu',
        'close',
        'chevron',
        'info',
        'check',
        'truck',
        'gift',
        'heart',
        'star',
        'leaf',
        'upload',
        'clock',
        'lock',
        'eye',
    ];

    private const BLOCK_JSON = __DIR__ . '/../../../src/blocks/icon/block.json';

    /**
     * @return iterable<string, array{string}>
     */
    public static function names(): iterable
    {
        foreach (self::NAMES as $name) {
            yield $name => [$name];
        }
    }

    public function testOffersTheFixedSetOfIcons(): void
    {
        self::assertSame(self::NAMES, Icons::names());
    }

    #[DataProvider('names')]
    public function testRendersEveryIconAsALineDrawingInTheTextColour(string $name): void
    {
        $svg = Icons::svg($name);

        self::assertMatchesRegularExpression('/\A<svg [^>]*viewBox="0 0 24 24"[^>]*>.+<\/svg>\z/s', $svg);
        self::assertStringContainsString('aria-hidden="true"', $svg);
        self::assertStringContainsString('stroke="currentColor"', $svg);
        self::assertStringContainsString('stroke-width="1.6"', $svg);
        self::assertStringNotContainsString('#', $svg, 'An icon takes its colour from the text, never its own.');
    }

    public function testRendersNothingForAnUnknownName(): void
    {
        self::assertSame('', Icons::svg('unicorn'));
        self::assertSame('', Icons::svg(''));
    }

    /** The editor offers the names block.json declares; render.php draws them from here. */
    public function testTheBlockDeclaresEveryIconAndNoOther(): void
    {
        $metadata = json_decode((string) file_get_contents(self::BLOCK_JSON), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);

        self::assertSame(self::NAMES, $metadata['attributes']['icon']['enum'] ?? null);
    }
}
