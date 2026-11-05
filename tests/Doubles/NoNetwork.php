<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Doubles;

use WP_Error;

/**
 * Refuses every outbound HTTP request in the integration suite that a test
 * double has not already answered, so no test ever reaches a real service:
 * not GitHub, not WordPress.org, not anything WooCommerce calls home to.
 */
final class NoNetwork
{
    /** Last, so a test double's own `pre_http_request` answer comes first. */
    public const PRIORITY = PHP_INT_MAX;

    /** What a refused request fails with. */
    public const ERROR_CODE = 'pixonaut_shop_tests_offline';

    public static function install(): void
    {
        add_filter('pre_http_request', [self::class, 'refuse'], self::PRIORITY, 3);
    }

    /**
     * @param false|array<mixed>|WP_Error $response An earlier filter's answer, or false.
     * @param array<mixed>                $args     The request's arguments.
     * @param string                      $url      Where it was going.
     *
     * @return array<mixed>|WP_Error
     */
    public static function refuse(false|array|WP_Error $response, array $args, string $url): array|WP_Error
    {
        if (false !== $response) {
            return $response;
        }

        return new WP_Error(self::ERROR_CODE, "The integration suite makes no outbound request: {$url}");
    }
}
