<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Updates;

use WP_Error;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi;

/**
 * The repository's GitHub releases, highest version first.
 *
 * plugin-update-checker's GitHub client takes the first release in the API's
 * list that passes the filter, and GitHub lists releases by publication date.
 * A maintenance `1.4.1` published after `2.0.0` would then hide `2.0.0`. This
 * client sorts the list by version first, so the first match is the highest
 * eligible version.
 */
final class GitHubReleases extends GitHubApi
{
    /** The release list's API path, as the parent class requests it. */
    private const RELEASES_PATH = '/repos/:user/:repo/releases';

    /**
     * Releases sorted highest version first; anything else unchanged.
     *
     * @param array<mixed> $releases Releases from the API.
     *
     * @return array<mixed>
     */
    public static function by_version(array $releases): array
    {
        usort(
            $releases,
            static fn (mixed $a, mixed $b): int => version_compare(self::version_of($b), self::version_of($a))
        );

        return $releases;
    }

    /**
     * GitHub API request; the release list comes back highest version first.
     *
     * @param string               $url          API path with `:user` and `:repo` placeholders.
     * @param array<string, mixed> $query_params Query arguments.
     *
     * @return mixed|WP_Error
     */
    protected function api($url, $query_params = [])
    {
        $result = parent::api($url, $query_params);

        if (self::RELEASES_PATH === $url && \is_array($result)) {
            return self::by_version($result);
        }

        return $result;
    }

    private static function version_of(mixed $release): string
    {
        $tag = \is_object($release) && isset($release->tag_name) && \is_string($release->tag_name)
            ? $release->tag_name
            : '';

        return ltrim($tag, 'v');
    }
}
