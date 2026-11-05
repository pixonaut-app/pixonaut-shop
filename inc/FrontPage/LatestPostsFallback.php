<?php

declare(strict_types=1);

namespace Pixonaut\Shop\FrontPage;

/**
 * The front page template shows the page a store chose as its homepage.
 * WordPress picks it for the latest posts too, where there is no such page,
 * so with "Your latest posts" as the homepage the posts list renders it
 * instead, as `index.html` does.
 */
final class LatestPostsFallback
{
    /** The Reading setting's value when a page is the homepage. */
    private const STATIC_FRONT_PAGE = 'page';

    public function register(): void
    {
        add_filter('frontpage_template_hierarchy', [$this, 'hierarchy']);
    }

    /**
     * @param list<string> $templates
     *
     * @return list<string>
     */
    public function hierarchy(array $templates): array
    {
        return self::STATIC_FRONT_PAGE === get_option('show_on_front') ? $templates : [];
    }
}
