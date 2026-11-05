<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Tests\Doubles\FakeGitHub;
use Pixonaut\Shop\Updates\GitHubUpdater;
use Pixonaut\Shop\Updates\Updates;

/**
 * The update checker against a fake GitHub: which release a store
 * is offered, and that the token travels in a header only.
 */
final class UpdatesTest extends TestCase
{
    private const TOKEN = 'github_pat_integration_dummy';

    private FakeGitHub $github;

    protected function setUp(): void
    {
        parent::setUp();

        // GitHub's order, newest publication first: a maintenance release of
        // an older line, a beta, a release still building, the newest stable.
        $this->github = new FakeGitHub(
            [
                FakeGitHub::release('v0.2.1', ['pixonaut-shop-0.2.1.zip']),
                FakeGitHub::release('v0.4.0-beta.1', ['pixonaut-shop-0.4.0-beta.1.zip'], true),
                FakeGitHub::release('v0.5.0', []),
                FakeGitHub::release('v0.3.0', ['pixonaut-shop-0.3.0.zip']),
            ],
            [
                'v0.2.1' => "/*\nTheme Name: Pixonaut Shop\nVersion: 0.2.1\n*/\n",
                'v0.3.0' => "/*\nTheme Name: Pixonaut Shop\nVersion: 0.3.0\n*/\n",
            ]
        );
        $this->github->install();
    }

    protected function tearDown(): void
    {
        $this->github->uninstall();
        parent::tearDown();
    }

    public function testOffersTheHighestStableReleaseWhoseZipIsAttached(): void
    {
        $update = $this->checker()->requestUpdate();

        self::assertNotNull($update);
        self::assertSame('0.3.0', $update->version);
        self::assertSame(FakeGitHub::asset_url('pixonaut-shop-0.3.0.zip'), $update->download_url);
    }

    public function testSendsTheTokenInAHeaderAndNeverInTheAddress(): void
    {
        $this->checker()->requestUpdate();

        self::assertNotSame([], $this->github->requests());

        foreach ($this->github->requests() as $request) {
            self::assertStringNotContainsString(self::TOKEN, $request['url']);
            self::assertArrayHasKey('Authorization', $request['headers']);
        }
    }

    public function testOffersAReleaseWithNoTokenFromItsPublicAddress(): void
    {
        $updates = new Updates(PIXONAUT_SHOP_THEME_DIR . '/style.css', null);
        $updates->register();

        $checker = $updates->updater()?->checker();
        self::assertNotNull($checker);

        $update = $checker->requestUpdate();

        self::assertNotNull($update);
        self::assertSame('0.3.0', $update->version);
        self::assertStringStartsWith(FakeGitHub::REPOSITORY_PAGE . '/releases/download/', (string) $update->download_url);
    }

    public function testSendsNoAuthorizationHeaderWithoutAToken(): void
    {
        $checker = (new GitHubUpdater(PIXONAUT_SHOP_THEME_DIR . '/style.css', null))->register();

        self::assertNotNull($checker);
        $checker->requestUpdate();

        self::assertNotSame([], $this->github->requests());

        foreach ($this->github->requests() as $request) {
            self::assertArrayNotHasKey('Authorization', $request['headers']);
        }
    }

    private function checker(): \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\ThemeUpdateChecker
    {
        $checker = (new GitHubUpdater(PIXONAUT_SHOP_THEME_DIR . '/style.css', self::TOKEN))->register();

        self::assertNotNull($checker);

        return $checker;
    }
}
