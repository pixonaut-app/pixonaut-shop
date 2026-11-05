<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * The title of a search's no-results notice, which names the query.
 *
 * `patterns/search-no-results.php` prints it when WordPress includes the
 * pattern, but the Site Editor saves a template with its patterns' blocks
 * in place, and so with the query of the request that opened it. The
 * heading is therefore printed again as it renders, with this request's
 * query.
 */
final class NoResultsTitle
{
    /** The class of the heading in `patterns/search-no-results.php`. */
    public const CLASS_NAME = 'pixonaut-shop-search-no-results-title';

    /** A heading's opening and closing tags around its content. */
    private const HEADING = '/\A(\s*<h[1-6]\b[^>]*>).*(<\/h[1-6]>\s*)\z/s';

    public function register(): void
    {
        add_filter('render_block_core/heading', [$this, 'render'], 10, 2);
    }

    /** The title for this request's query, as plain text. */
    public static function text(): string
    {
        /* translators: %s: what the customer searched for. */
        return \sprintf(__('No results for “%s”', 'pixonaut-shop'), get_search_query(false));
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function render(string $block_content, array $block): string
    {
        $attributes = $block['attrs'] ?? [];
        $class_name = \is_array($attributes) ? ($attributes['className'] ?? '') : '';

        if (! \is_string($class_name) || ! \in_array(self::CLASS_NAME, explode(' ', $class_name), true)) {
            return $block_content;
        }

        return (string) preg_replace_callback(
            self::HEADING,
            static fn (array $tags): string => $tags[1] . esc_html(self::text()) . $tags[2],
            $block_content
        );
    }
}
