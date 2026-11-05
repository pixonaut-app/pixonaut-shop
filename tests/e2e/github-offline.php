<?php

/**
 * Plugin Name: GitHub offline
 * Description: Answers GitHub's API with no releases on wp-env's tests site, so the theme's update checker never reaches the real service from a test.
 *
 * Mapped into wp-env's tests site only (.wp-env.json). A test double's own
 * answer, added after this one, replaces it.
 */

declare(strict_types=1);

/** The host the theme's update checker asks for releases. */
const PIXONAUT_SHOP_TESTS_GITHUB_API_HOST = 'api.github.com';

add_filter(
    'pre_http_request',
    static function ($response, array $args, string $url) {
        if (false !== $response || PIXONAUT_SHOP_TESTS_GITHUB_API_HOST !== wp_parse_url($url, PHP_URL_HOST)) {
            return $response;
        }

        return [
            'headers'  => ['content-type' => 'application/json'],
            'body'     => '[]',
            'response' => ['code' => WP_Http::OK, 'message' => 'OK'],
            'cookies'  => [],
            'filename' => null,
        ];
    },
    10,
    3
);
