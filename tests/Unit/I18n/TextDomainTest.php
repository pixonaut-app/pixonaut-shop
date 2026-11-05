<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\I18n;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\I18n\TextDomain;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(TextDomain::class)]
final class TextDomainTest extends TestCase
{
    public function testRegistersOnInit(): void
    {
        (new TextDomain())->register();

        self::assertSame(
            TextDomain::PRIORITY,
            has_action('init', 'Pixonaut\Shop\I18n\TextDomain->load()')
        );
    }

    public function testLoadsTheCatalogueFromTheLanguagesDirectory(): void
    {
        if (! \defined('PIXONAUT_SHOP_THEME_DIR')) {
            \define('PIXONAUT_SHOP_THEME_DIR', '/theme');
        }

        Functions\expect('load_theme_textdomain')
            ->once()
            ->with(TextDomain::DOMAIN, PIXONAUT_SHOP_THEME_DIR . '/languages')
            ->andReturn(true);

        // The Brain Monkey expectation above is the real assertion here: it
        // is verified by Monkey\tearDown() and fails the test if
        // load_theme_textdomain() is not called exactly as expected.
        $this->expectNotToPerformAssertions();

        (new TextDomain())->load();
    }
}
