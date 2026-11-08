<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

/**
 * Which header a store shows, saved in the `pixonaut_shop_header` option by
 * a tool that sets up the store: `simple`, the one row of logo, menu,
 * search, account and cart, or `mega`, whose menu button opens a panel of
 * the store's categories.
 *
 * The mega header is not built yet, so a store that asks for it shows the
 * simple one until it is; so does a store whose option is unset or holds
 * anything else.
 */
final class HeaderVariant
{
    /** The option the header variant is saved in. */
    public const OPTION = 'pixonaut_shop_header';

    public const SIMPLE = 'simple';

    public const MEGA = 'mega';

    /** The variants the theme has a header for. */
    private const BUILT = [self::SIMPLE];

    /** The header the store shows. */
    public static function current(): string
    {
        $variant = get_option(self::OPTION);

        return \is_string($variant) && \in_array($variant, self::BUILT, true) ? $variant : self::SIMPLE;
    }
}
