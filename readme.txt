=== Pixonaut Shop ===
Contributors: pixonaut
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A calm WooCommerce block theme for shops that sell personalised garments and prints, styled entirely from the Site Editor.

== Description ==

Pixonaut Shop is a block theme for WooCommerce shops that sell personalised products: garments, mugs, prints and gifts. It gives the shop, the product page, the cart, the checkout, the order received page and My account a plain, calm layout, so the products are what a visitor looks at.

Everything a shop changes lives in the Site Editor. Colours, fonts, spacing and the logo are set under Styles, and the Nature style variation is a second starting point. The header, footer, announcement bar and the shop's promises are template parts, and the front page, hero and product grid are block patterns, so a shop writes its own words and pictures over them without touching code.

The theme works without WooCommerce too: pages, posts and comments render as they would in any block theme, and a dismissible notice in the dashboard says what the shop pages need.

Its product page leaves room for a product designer from a plugin: the product gallery and the add-to-cart form stay where WooCommerce puts them, so a personalisation plugin that prints into them fits in without changes to the theme.

== Frequently Asked Questions ==

= Does the theme need WooCommerce? =

The shop, product, cart and checkout templates use WooCommerce's blocks, so WooCommerce is needed for a shop. Without it, the theme shows your pages and posts, and asks administrators who can install plugins to install WooCommerce. That notice can be dismissed for good.

= How do I change the colours and fonts? =

Open Appearance, Editor, Styles. Pick the Nature variation or change the colours and fonts yourself; your changes are kept when the theme updates. Fonts you add through the Font Library are served from your own site.

= Does the theme collect any data? =

No. The theme stores nothing about visitors, sets no cookies of its own and makes no requests to other sites. The only data it saves is one user setting, when an administrator dismisses its WooCommerce notice for good.

= Where are the theme's sources? =

The stylesheets and scripts in build/ are compiled from the files in src/ with @wordpress/scripts (`npm install` then `npm run build`). The theme's development happens at https://github.com/pixonaut-app/pixonaut-shop.

== Changelog ==

= 1.1.0 =
* Added: a template for single posts, with the post's date, featured image, categories, tags and comments.
* Added: the date on each post in the posts list, and the archive's title above it.
* Changed: the WooCommerce notice can be dismissed for good.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.1.0 =
Posts now show their whole content and comments on a single-post template.

== Copyright ==

Pixonaut Shop WordPress Theme, (C) 2026 Pixonaut.
Pixonaut Shop is distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

== Resources ==

Inter
Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter)
License: SIL Open Font License, Version 1.1, https://openfontlicense.org
Source: https://rsms.me/inter/ (assets/fonts/inter/, with its OFL.txt)

screenshot.png
Copyright 2026 Pixonaut
License: GPL-2.0-or-later, https://www.gnu.org/licenses/gpl-2.0.html
The theme on a demonstration shop; the product pictures in it were drawn for this theme.
