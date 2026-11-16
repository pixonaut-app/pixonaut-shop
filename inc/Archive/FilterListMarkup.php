<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use WP_HTML_Tag_Processor;
use WP_HTML_Text_Replacement;

/**
 * Markup the tag processor can add to after a tag, which it cannot do alone.
 * WordPress's own Interactivity API extends it the same way to add markup
 * after a template.
 *
 * The added markup goes in once the whole markup has been read, so the
 * processor never reads, and changes, what was added.
 */
final class FilterListMarkup extends WP_HTML_Tag_Processor
{
    private const BOOKMARK = 'pixonaut-shop-after';

    /** @var list<array{int, string}> Where each addition goes, and what it is. */
    private array $additions = [];

    /** Notes markup to add right after the current tag: after a closer, outside its element. */
    public function add_after_current_tag(string $markup): bool
    {
        if ('' === $markup || null === $this->get_tag() || ! $this->set_bookmark(self::BOOKMARK)) {
            return false;
        }

        $tag               = $this->bookmarks[self::BOOKMARK];
        $this->additions[] = [$tag->start + $tag->length, $markup];
        $this->release_bookmark(self::BOOKMARK);

        return true;
    }

    /** The markup with every change, the additions included. Call it once, when done reading. */
    public function with_additions(): string
    {
        foreach ($this->additions as [$at, $markup]) {
            $this->lexical_updates[] = new WP_HTML_Text_Replacement($at, 0, $markup);
        }

        $this->additions = [];

        return $this->get_updated_html();
    }
}
