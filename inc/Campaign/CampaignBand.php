<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Campaign;

use Closure;
use DateTimeImmutable;
use Exception;

/**
 * A store's campaign (Black Friday, a sale), saved in the
 * `pixonaut_shop_campaign` option by a tool that sets up the store, and shown
 * as a band in the header's announcement part while it runs.
 *
 * The option is an array with the string keys `text`, `url`, `starts` and
 * `ends`. Both dates are ISO 8601 in the site's time zone, a date
 * (`2026-11-27`, midnight) or a date and time (`2026-11-27T08:00:00`), and the
 * band shows while `starts <= now < ends`. A campaign missing either date, or
 * holding one that is not a real date, never shows, so one prepared ahead
 * stays hidden until its owner sets the dates. A page cache can keep serving
 * the band after its end, until the cache is purged.
 *
 * While a campaign shows, its band takes the announcement part's place. At
 * any other time the part shows what the store wrote in it, if anything.
 * A band that links to a reduced product shows that product's lowest price
 * of the 30 days before its reduction after its text, as a reduced price
 * does everywhere else (`Product\LowestPrice`).
 */
final class CampaignBand
{
    /** The option a store's campaign is saved in. */
    public const OPTION = 'pixonaut_shop_campaign';

    /** The slug of the template part the band is shown in. */
    private const PART_SLUG = 'announcement';

    private const TEXT_KEY = 'text';

    private const URL_KEY = 'url';

    private const STARTS_KEY = 'starts';

    private const ENDS_KEY = 'ends';

    /** A date, or a date and a time with an optional offset, as ISO 8601 writes them. */
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * The band's markup: the announcement pattern's group, on the `highlight`
     * ground, around one centred line. `%s` is the line.
     */
    private const BAND = '<div class="wp-block-group alignfull pixonaut-shop-announcement has-highlight-contrast-color has-highlight-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><p class="has-text-align-center has-small-font-size" style="font-weight:700">%s</p></div>';

    /** @var Closure(): DateTimeImmutable */
    private Closure $now;

    /** @var (Closure(string): ?string)|null */
    private ?Closure $price_note;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now        The site's current time; defaults to WordPress's clock in the site's time zone.
     * @param (Closure(string): ?string)|null     $price_note The escaped lowest-price line for the reduced product an address links to, or null for none.
     */
    public function __construct(?Closure $now = null, ?Closure $price_note = null)
    {
        $this->now = $now ?? static fn (): DateTimeImmutable => current_datetime();
        $this->price_note = $price_note;
    }

    public function register(): void
    {
        add_filter('render_block_core/template-part', [$this, 'render'], 10, 2);
    }

    /**
     * The announcement part with the campaign band in it while the campaign
     * runs; any other part, or the part with no campaign running, as it is.
     *
     * @param array<string, mixed> $block
     */
    public function render(string $content, array $block): string
    {
        $attributes = $block['attrs'] ?? null;

        if (! \is_array($attributes) || self::PART_SLUG !== ($attributes['slug'] ?? null)) {
            return $content;
        }

        $line = $this->line();
        $inner_start = strpos($content, '>');
        $inner_end = strrpos($content, '</');

        if (null === $line || false === $inner_start || false === $inner_end || $inner_end <= $inner_start) {
            return $content;
        }

        return substr($content, 0, $inner_start + 1) . \sprintf(self::BAND, $line) . substr($content, $inner_end);
    }

    /**
     * The band's escaped line, linked where the campaign has an address and
     * followed by the linked product's lowest price when it is reduced, or
     * null while no campaign runs.
     */
    private function line(): ?string
    {
        $option = get_option(self::OPTION);

        if (! \is_array($option) || ! \is_string($option[self::TEXT_KEY] ?? null) || '' === trim($option[self::TEXT_KEY])) {
            return null;
        }

        $starts = self::date($option[self::STARTS_KEY] ?? null);
        $ends = self::date($option[self::ENDS_KEY] ?? null);
        $now = ($this->now)();

        if (null === $starts || null === $ends || $now < $starts || $now >= $ends) {
            return null;
        }

        $text = esc_html($option[self::TEXT_KEY]);
        $url = \is_string($option[self::URL_KEY] ?? null) ? esc_url_raw($option[self::URL_KEY]) : '';

        if ('' === $url) {
            return $text;
        }

        $note = null === $this->price_note ? null : ($this->price_note)($url);

        return '<a href="' . esc_url($url) . '">' . $text . '</a>' . (null === $note ? '' : ' ' . $note);
    }

    /** The moment an ISO 8601 date in the site's time zone names, or null when it is not one. */
    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (! \is_string($value) || 1 !== preg_match(self::DATE_PATTERN, $value)) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, wp_timezone());
        } catch (Exception) {
            return null;
        }

        // A date PHP rolled over, such as the 31st of February, is not a real one.
        return false === DateTimeImmutable::getLastErrors() ? $date : null;
    }
}
