<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The update checker runs without a token, so the tests site itself must keep
 * it from the real GitHub: an end-to-end run has no test double in the request.
 */
final class GitHubOfflineTest extends TestCase
{
    public function testTheTestsSiteAnswersGitHubWithNoReleases(): void
    {
        $response = wp_remote_get('https://api.github.com/repos/pixonaut-app/pixonaut-shop/releases');

        self::assertFalse(is_wp_error($response));
        self::assertSame('[]', wp_remote_retrieve_body($response));
    }
}
