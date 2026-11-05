<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Updates;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Tests\TestCase;
use Pixonaut\Shop\Updates\GitHubReleases;

#[CoversClass(GitHubReleases::class)]
final class GitHubReleasesTest extends TestCase
{
    public function testSortsReleasesByVersionRatherThanPublicationDate(): void
    {
        // GitHub's order: newest publication first. A maintenance release of
        // an older line was published after the newer release.
        $releases = [
            (object) ['tag_name' => 'v1.4.1'],
            (object) ['tag_name' => 'v2.0.0'],
            (object) ['tag_name' => 'v1.10.0'],
        ];

        self::assertSame(
            ['v2.0.0', 'v1.10.0', 'v1.4.1'],
            array_map(static fn (object $release): string => $release->tag_name, GitHubReleases::by_version($releases))
        );
    }

    public function testPutsAnEntryWithoutATagLast(): void
    {
        $sorted = GitHubReleases::by_version([(object) ['name' => 'draft'], (object) ['tag_name' => 'v0.1.0']]);

        self::assertEquals((object) ['tag_name' => 'v0.1.0'], $sorted[0]);
    }
}
