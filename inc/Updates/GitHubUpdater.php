<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Updates;

use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\ThemeUpdateChecker;

/**
 * Offers the release zips attached to the repository named in style.css's
 * `Update URI` header, through plugin-update-checker.
 */
final class GitHubUpdater
{
    /**
     * The release zip attached to each GitHub release, as release.yml names
     * it. Its top-level directory is the theme's slug.
     */
    public const ASSET_PATTERN = '/^pixonaut-shop-\d+\.\d+\.\d+\.zip$/';

    /** A stable release's version: `1.2.3`. Pre-releases are never offered. */
    public const STABLE_VERSION = '/^\d+\.\d+\.\d+$/';

    /** How many of the most recently published releases are searched for the highest one. */
    public const RELEASES_SEARCHED = 20;

    /** Hours between two automatic checks. */
    public const CHECK_PERIOD_HOURS = 12;

    /** The site option the checker keeps its last check and the update it found in. */
    public const STATE_OPTION = 'pixonaut_shop_update_state';

    private ?ThemeUpdateChecker $checker = null;

    /**
     * @param string      $stylesheet_file Absolute path of the theme's style.css.
     * @param string|null $token           GitHub token that may read the repository's releases; null for a public repository.
     */
    public function __construct(
        private readonly string $stylesheet_file,
        private readonly ?string $token,
    ) {
    }

    /**
     * Starts checking for updates; null when style.css names no repository.
     */
    public function register(): ?ThemeUpdateChecker
    {
        $repository = get_file_data($this->stylesheet_file, ['update_uri' => 'Update URI'])['update_uri'];

        if ('' === $repository) {
            return null;
        }

        $api = new GitHubReleases($repository);
        $api->enableReleaseAssets(self::ASSET_PATTERN, Api::REQUIRE_RELEASE_ASSETS);
        // A release whose zip is not attached yet (still building, or a failed
        // build) is skipped rather than hiding the older releases behind it:
        // the library gives up at the first release without a matching asset.
        $api->setReleaseFilter(
            static fn (string $version, object $release): bool => self::offers($version, $release),
            Api::RELEASE_FILTER_SKIP_PRERELEASE,
            self::RELEASES_SEARCHED
        );

        // The library documents the stylesheet as `null` only, its default
        // for "the active theme"; it takes the theme's directory name too,
        // which keeps the check on this theme while a child theme is active.
        $checker = new ThemeUpdateChecker(
            $api,
            // @phpstan-ignore argument.type
            basename(\dirname($this->stylesheet_file)),
            null,
            self::CHECK_PERIOD_HOURS,
            self::STATE_OPTION
        );
        // Sent as an authorization header, never in a URL, so it is not
        // stored with the update the checker caches. Without a token the
        // checks and the download use GitHub's public addresses.
        if (null !== $this->token) {
            $checker->setAuthentication($this->token);
        }

        // Releases only: the checker would otherwise fall back to the highest
        // tag, and then to the default branch.
        add_filter(
            $checker->getUniqueName('vcs_update_detection_strategies'),
            [self::class, 'releases_only']
        );

        $this->checker = $checker;

        return $checker;
    }

    /**
     * Whether a release is offered: a stable version with its zip attached.
     *
     * @param string $version The release's version, without a leading `v`.
     * @param object $release A release from the GitHub API.
     */
    public static function offers(string $version, object $release): bool
    {
        return 1 === preg_match(self::STABLE_VERSION, $version) && self::has_zip($release);
    }

    /**
     * Keeps only the latest-release strategy.
     *
     * @param array<string, mixed> $strategies The library's strategies, by name.
     *
     * @return array<string, mixed>
     */
    public static function releases_only(array $strategies): array
    {
        return array_intersect_key($strategies, [Api::STRATEGY_LATEST_RELEASE => true]);
    }

    /**
     * The update checker, once register() started one.
     */
    public function checker(): ?ThemeUpdateChecker
    {
        return $this->checker;
    }

    /**
     * Whether a release has the zip the checker installs attached.
     *
     * @param object $release A release from the GitHub API.
     */
    private static function has_zip(object $release): bool
    {
        $assets = isset($release->assets) && \is_array($release->assets) ? $release->assets : [];

        foreach ($assets as $asset) {
            if (
                \is_object($asset)
                && isset($asset->name)
                && \is_string($asset->name)
                && 1 === preg_match(self::ASSET_PATTERN, $asset->name)
            ) {
                return true;
            }
        }

        return false;
    }
}
