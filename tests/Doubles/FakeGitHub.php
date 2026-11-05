<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Doubles;

use WP_Error;

/**
 * Answers plugin-update-checker's calls to GitHub's API for this theme's
 * repository from WordPress's own `pre_http_request` filter: the release
 * list, and style.css at a tag. Anything else under the repository answers
 * 404; anything outside it is left to NoNetwork, which refuses it.
 */
final class FakeGitHub
{
    public const REPOSITORY_API = 'https://api.github.com/repos/pixonaut-app/pixonaut-shop';

    public const REPOSITORY_PAGE = 'https://github.com/pixonaut-app/pixonaut-shop';

    private const OK = 200;

    private const NOT_FOUND = 404;

    /** @var list<array{url: string, headers: array<mixed>}> */
    private array $requests = [];

    /**
     * @param list<object>          $releases    The release list, in GitHub's order: newest publication first.
     * @param array<string, string> $stylesheets style.css's contents at each tag.
     */
    public function __construct(
        private readonly array $releases,
        private readonly array $stylesheets,
    ) {
    }

    /**
     * A release as GitHub's API lists it.
     *
     * @param list<string> $asset_names The files attached to it.
     */
    public static function release(string $tag, array $asset_names, bool $prerelease = false): object
    {
        return (object) [
            'tag_name'    => $tag,
            'draft'       => false,
            'prerelease'  => $prerelease,
            'zipball_url' => self::REPOSITORY_API . "/zipball/{$tag}",
            'created_at'  => '2026-10-01T12:00:00Z',
            'body'        => '',
            'assets'      => array_map(
                static fn (string $name): object => (object) [
                    'name'                 => $name,
                    'url'                  => self::asset_url($name),
                    'browser_download_url' => self::REPOSITORY_PAGE . "/releases/download/{$tag}/{$name}",
                    'download_count'       => 0,
                ],
                $asset_names
            ),
        ];
    }

    /** The API address of an attached file, which an authenticated checker downloads from. */
    public static function asset_url(string $name): string
    {
        return self::REPOSITORY_API . '/releases/assets/' . md5($name);
    }

    public function install(): void
    {
        add_filter('pre_http_request', [$this, 'answer'], 10, 3);
    }

    public function uninstall(): void
    {
        remove_filter('pre_http_request', [$this, 'answer']);
    }

    /**
     * @return list<array{url: string, headers: array<mixed>}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @param false|array<mixed>|WP_Error $response An earlier filter's answer, or false.
     * @param array<mixed>                 $args     The request's arguments.
     * @param string                       $url      Where it was going.
     *
     * @return false|array<mixed>|WP_Error
     */
    public function answer(false|array|WP_Error $response, array $args, string $url): false|array|WP_Error
    {
        if (! str_starts_with($url, self::REPOSITORY_API . '/')) {
            return $response;
        }

        $headers          = $args['headers'] ?? [];
        $this->requests[] = ['url' => $url, 'headers' => \is_array($headers) ? $headers : []];

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $repository_path = (string) wp_parse_url(self::REPOSITORY_API, PHP_URL_PATH);
        $ref             = \is_string($query['ref'] ?? null) ? $query['ref'] : '';

        if ($repository_path . '/releases' === $path) {
            return self::json(self::OK, $this->releases);
        }

        if ($repository_path . '/contents/style.css' === $path && isset($this->stylesheets[$ref])) {
            return self::json(self::OK, [
                'encoding' => 'base64',
                // GitHub's contents API sends a file base64-encoded.
                'content'  => base64_encode($this->stylesheets[$ref]), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            ]);
        }

        return self::json(self::NOT_FOUND, ['message' => 'Not Found']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(int $status, mixed $body): array
    {
        return [
            'headers'  => ['content-type' => 'application/json'],
            'body'     => (string) wp_json_encode($body),
            'response' => ['code' => $status, 'message' => ''],
            'cookies'  => [],
            'filename' => null,
        ];
    }
}
