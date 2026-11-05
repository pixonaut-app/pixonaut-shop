<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Support\ThemeVersion;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(ThemeVersion::class)]
final class ThemeVersionTest extends TestCase
{
    private string $stylesheet;

    private string $second_stylesheet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylesheet = sys_get_temp_dir() . '/pixonaut-shop-style-' . uniqid('', true) . '.css';
        $this->second_stylesheet = sys_get_temp_dir() . '/pixonaut-shop-style-' . uniqid('', true) . '.css';
        ThemeVersion::reset();
    }

    protected function tearDown(): void
    {
        foreach ([$this->stylesheet, $this->second_stylesheet] as $path) {
            if (file_exists($path)) {
                // No WordPress runtime is bootstrapped in a unit test, so
                // WP_Filesystem/wp_delete_file() are unavailable here.
                unlink($path);
            }
        }

        ThemeVersion::reset();
        parent::tearDown();
    }

    public function testReadsTheVersionFromTheStylesheetHeader(): void
    {
        // No WordPress runtime is bootstrapped in a unit test, so
        // WP_Filesystem is unavailable here.
        file_put_contents(
            $this->stylesheet,
            "/*\nTheme Name: Pixonaut Shop\nVersion: 1.2.3\nText Domain: pixonaut-shop\n*/\n"
        );

        self::assertSame('1.2.3', ThemeVersion::get($this->stylesheet));
    }

    public function testFallsBackWhenTheHeaderHasNoVersion(): void
    {
        file_put_contents($this->stylesheet, "/*\nTheme Name: Pixonaut Shop\n*/\n");

        self::assertSame(ThemeVersion::FALLBACK, ThemeVersion::get($this->stylesheet));
    }

    public function testFallsBackWhenTheStylesheetIsMissing(): void
    {
        self::assertSame(ThemeVersion::FALLBACK, ThemeVersion::get($this->stylesheet . '-absent'));
    }

    public function testCachesEachStylesheetPathSeparately(): void
    {
        file_put_contents(
            $this->stylesheet,
            "/*\nTheme Name: Pixonaut Shop\nVersion: 1.2.3\n*/\n"
        );
        file_put_contents(
            $this->second_stylesheet,
            "/*\nTheme Name: Pixonaut Shop\nVersion: 4.5.6\n*/\n"
        );

        self::assertSame('1.2.3', ThemeVersion::get($this->stylesheet));
        self::assertSame('4.5.6', ThemeVersion::get($this->second_stylesheet));
    }
}
