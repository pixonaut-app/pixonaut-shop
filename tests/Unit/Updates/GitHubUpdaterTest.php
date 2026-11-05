<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Updates;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Tests\TestCase;
use Pixonaut\Shop\Updates\GitHubUpdater;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;

#[CoversClass(GitHubUpdater::class)]
final class GitHubUpdaterTest extends TestCase
{
    public function testOffersAStableReleaseWithItsZipAttached(): void
    {
        self::assertTrue(GitHubUpdater::offers('1.2.0', self::release('pixonaut-shop-1.2.0.zip')));
    }

    /**
     * @return iterable<string, array{string, object}>
     */
    public static function releasesNotOffered(): iterable
    {
        yield 'a pre-release' => ['1.3.0-beta.1', self::release('pixonaut-shop-1.3.0-beta.1.zip')];
        yield 'a release whose zip is not attached yet' => ['1.2.0', self::release()];
        yield 'a release with only GitHub\'s source archive' => ['1.2.0', self::release('pixonaut-shop-main.zip')];
        yield 'a release with another project\'s zip' => ['1.2.0', self::release('pixonaut-for-woocommerce-1.2.0.zip')];
        yield 'a release with no asset list' => ['1.2.0', (object) []];
    }

    #[DataProvider('releasesNotOffered')]
    public function testDoesNotOffer(string $version, object $release): void
    {
        self::assertFalse(GitHubUpdater::offers($version, $release));
    }

    public function testDetectsUpdatesFromReleasesAlone(): void
    {
        $strategies = [
            Api::STRATEGY_LATEST_RELEASE => 'release',
            Api::STRATEGY_LATEST_TAG     => 'tag',
            Api::STRATEGY_BRANCH         => 'branch',
        ];

        self::assertSame(
            [Api::STRATEGY_LATEST_RELEASE => 'release'],
            GitHubUpdater::releases_only($strategies)
        );
    }

    private static function release(string ...$asset_names): object
    {
        return (object) [
            'assets' => array_map(static fn (string $name): object => (object) ['name' => $name], $asset_names),
        ];
    }
}
