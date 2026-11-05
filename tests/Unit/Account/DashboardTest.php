<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Account;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Account\Dashboard;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(Dashboard::class)]
final class DashboardTest extends TestCase
{
    private const CUSTOMER_ID = 7;

    public function testRegistersOnTheAccountDashboard(): void
    {
        (new Dashboard())->register();

        self::assertNotFalse(
            has_action('woocommerce_account_dashboard', 'Pixonaut\Shop\Account\Dashboard->render()')
        );
    }

    public function testRendersNothingForACustomerWithoutOrders(): void
    {
        Functions\when('get_current_user_id')->justReturn(self::CUSTOMER_ID);
        Functions\expect('wc_get_orders')
            ->once()
            ->with(Mockery::subset(['customer' => self::CUSTOMER_ID, 'limit' => Dashboard::RECENT_ORDERS]))
            ->andReturn([]);

        self::assertSame('', $this->render());
    }

    public function testRendersNothingForAVisitorWhoIsNotSignedIn(): void
    {
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\expect('wc_get_orders')->never();

        self::assertSame('', $this->render());
    }

    private function render(): string
    {
        ob_start();
        (new Dashboard())->render();

        return (string) ob_get_clean();
    }
}
