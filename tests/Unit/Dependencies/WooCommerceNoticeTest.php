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

    private const USER = 7;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        Functions\when('admin_url')->alias(static fn (string $path): string => self::ADMIN_URL . $path);
        Functions\when('get_current_user_id')->justReturn(self::USER);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('add_query_arg')->alias(static fn (string $key, string $value): string => self::ADMIN_URL . "?{$key}={$value}");
        Functions\when('wp_nonce_url')->alias(static fn (string $url, string $action): string => "{$url}&_wpnonce={$action}");
    }

    public function testRegistersOnAdminNotices(): void
    {
        (new WooCommerceNotice())->register();

        self::assertNotFalse(
            has_action('admin_notices', 'Pixonaut\Shop\Dependencies\WooCommerceNotice->render()')
        );
    }

    public function testDismissesOnAdminInit(): void
    {
        (new WooCommerceNotice())->register();

        self::assertNotFalse(
            has_action('admin_init', 'Pixonaut\Shop\Dependencies\WooCommerceNotice->dismiss()')
        );
    }

    public function testIsADismissibleCoreNotice(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $output = $this->render(static fn (): bool => false);

        self::assertStringContainsString('class="notice notice-warning is-dismissible"', $output);
    }

    public function testOffersALinkThatDismissesItForGood(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $output = $this->render(static fn (): bool => false);

        self::assertStringContainsString(
            self::ADMIN_URL . '?' . WooCommerceNotice::DISMISS_ARG . '=1&_wpnonce=' . WooCommerceNotice::DISMISS_ARG,
            $output
        );
    }

    public function testSaysNothingToSomeoneWhoDismissedIt(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_user_meta')->alias(
            static fn (int $user, string $key, bool $single): string => self::USER === $user && WooCommerceNotice::DISMISSED_META === $key && $single ? '1' : ''
        );

        self::assertSame('', $this->render(static fn (): bool => false));
    }

    public function testRemembersADismissalWithAValidNonce(): void
    {
        $_GET[WooCommerceNotice::DISMISS_ARG] = '1';
        Functions\expect('check_admin_referer')->once()->with(WooCommerceNotice::DISMISS_ARG)->andReturn(1);
        Functions\expect('update_user_meta')->once()->with(self::USER, WooCommerceNotice::DISMISSED_META, '1');
        $this->expectNotToPerformAssertions();

        try {
            (new WooCommerceNotice())->dismiss();
        } finally {
            unset($_GET[WooCommerceNotice::DISMISS_ARG]);
        }
    }

    public function testRemembersNothingWithoutTheDismissLink(): void
    {
        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_user_meta')->never();
        $this->expectNotToPerformAssertions();

        (new WooCommerceNotice())->dismiss();
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
