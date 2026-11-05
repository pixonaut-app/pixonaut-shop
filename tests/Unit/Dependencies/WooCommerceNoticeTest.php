<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Dependencies;

use Brain\Monkey\Functions;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Dependencies\WooCommerceNotice;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(WooCommerceNotice::class)]
final class WooCommerceNoticeTest extends TestCase
{
    private const ADMIN_URL = 'https://shop.test/wp-admin/';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        Functions\when('admin_url')->alias(static fn (string $path): string => self::ADMIN_URL . $path);
    }

    public function testRegistersOnAdminNotices(): void
    {
        (new WooCommerceNotice())->register();

        self::assertNotFalse(
            has_action('admin_notices', 'Pixonaut\Shop\Dependencies\WooCommerceNotice->render()')
        );
    }

    public function testAsksSomeoneWhoCanInstallPluginsToInstallWooCommerce(): void
    {
        Functions\expect('current_user_can')
            ->with(WooCommerceNotice::REQUIRED_CAPABILITY)
            ->andReturn(true);

        $output = $this->render(static fn (): bool => false);

        self::assertStringContainsString('notice-warning', $output);
        self::assertStringContainsString('need WooCommerce', $output);
        self::assertStringContainsString(self::ADMIN_URL . WooCommerceNotice::INSTALL_SCREEN, $output);
    }

    public function testSaysNothingWhileWooCommerceIsActive(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        self::assertSame('', $this->render(static fn (): bool => true));
    }

    public function testSaysNothingToSomeoneWhoCannotInstallPlugins(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        self::assertSame('', $this->render(static fn (): bool => false));
    }

    /**
     * @param Closure(): bool $is_active
     */
    private function render(Closure $is_active): string
    {
        ob_start();
        (new WooCommerceNotice($is_active))->render();

        return (string) ob_get_clean();
    }
}
