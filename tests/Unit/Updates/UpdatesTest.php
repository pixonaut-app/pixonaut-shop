<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Updates;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Tests\TestCase;
use Pixonaut\Shop\Updates\Updates;

#[CoversClass(Updates::class)]
final class UpdatesTest extends TestCase
{
    public function testHasNoTokenUntilWpConfigDefinesOne(): void
    {
        // The unit suite never defines the constant: a token reaches a site
        // only through its own wp-config.php.
        self::assertNull(Updates::configured_token());
    }
}
