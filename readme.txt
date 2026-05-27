=== Request For Quote ===
Contributors: wpheka, akshayaswaroop
Tags: request a quote button, woocommerce request for quote, woocommerce request a quote shortcode, request a quote, quote, WPHEKA, quotations, request for quote, rfq, raq, proposal, ask an estimate, budget, email quote
Requires at least: 4.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: paypal.me/AKSHAYASWAROOP

Request For Quote plugin allows your customers to submit quotes for any product and negotiate with you for the best price. This extension can be a great tool to generate customer leads for future engagement.

== Description ==
**Request For Quote** allows your customers to add products to a quote basket and negotiate with you for the best price. This Extension helps both sides to reach a price agreement, which decreases cart abandonment with price reason and increase purchases.

= Features List: =

* Display **Add to Quote** on products
* Option to show **Add to quote** as button or link
* Option to **hide price** from product pages
* Option to **add to cart** button from product pages
* Option to enable **request quote** button in shop and other archive/category pages
* Allow your customers to request a **list of products** or a **single product** for each quote
* Quote list page with a basic form (name, email, message)
* Email alerts to admin/site owners on RFQ
* Configure email notification for the users and admin

If you enjoyed this plugin then please put a review, that will encourage me to bring some more …

== Installation ==

= Minimum Requirements =

* WooCommerce 3.0 or later

1. Upload 'wpheka-request-for-quote' to the '/wp-content/plugins/' directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Admin area -> WPHEKA -> Request For Quote Settings
4. Done!

== Frequently Asked Questions ==

= How It Works? =
*check installation*

== Screenshots ==

1. Plugin settings link.
2. Plugin settings screen
3. Configure email notifications

== Changelog ==

= 1.7.2 - 2026-05-27 =
* Feature - Added a polite 5-star review request notice for store admins, shown after 7 days and at least one quote submission. Includes "Sure, you deserve it", "I already did", "Maybe later" (14-day snooze) and "Don't ask again" options.
* Fix - Made the "Your request has been sent successfully" and corresponding error message translatable via Loco Translate (previously hardcoded in the AJAX response).
* Fix - Added missing text domain to three admin settings labels (Select a page, Button, Link) so they appear in translation tools.
* Enhancement - Added "Requires PHP: 7.4" header.
* Enhancement - WordPress version 7.0 compatibility added.
* Enhancement - WooCommerce version 10.7.0 compatibility added.

= 1.7.1 - 2026-02-13 =
* Fix - Fixed "unexpected output during activation" error by removing get_plugin_data() call.

= 1.7.0 - 2026-02-13 =
* Feature - Added user type restrictions (show button to all/logged/guest users).
* Feature - Added out of stock product options (show on all/only out of stock/hide on out of stock).
* Feature - Added button position option (before/after add to cart button).
* Feature - Added after-click behavior (show link or auto-redirect to quote page).
* Feature - Added additional form fields (phone number and company name).
* Fix - Fixed "Hide Price" and "Hide Add to Cart" settings not working on product pages.
* Security - Fixed improper sanitization in plugin settings save handler.
* Security - Added proper output escaping for page dropdown in settings form.
* Security - Added missing wp_unslash() call in remove item AJAX handler.
* Enhancement - WordPress version 6.9.1 compatibility added.
* Enhancement - WooCommerce version 10.5.1 compatibility added.

= 1.6.1 - 2025-11-09 =
* Enhancement - WooCommerce HPOS (High Performance Order Storage) compatibility added.
* Enhancement - WooCommerce Version 10.3.4 compatibility added.
* Security - Fixed missing nonce verification in shop AJAX handler.
* Security - Improved data sanitization with proper wp_unslash() calls.
* Enhancement - Externalized inline JavaScript for better security practices.
* Enhancement - Added proper error handling for session class instantiation.
* Enhancement - Improved code organization and separation of concerns.

= 1.6 - 2024-12-03 =
* Enhancement - WooCommerce Version 9.4.2 compatibility added.

= 1.5 - 2022-06-09 =
* Enhancement - WooCommerce Version 6.5.1 compatibility added.

= 1.4 - 2021-09-02 =
* Enhancement - Used 'wp or 'wordpress' prefixed cookies to fix caching issue with multiple hosting providers.

= 1.3 - 2021-08-13 =
* Enhancement - CSRF issues fixed.

= 1.2 - 2020-06-06 =
* Enhancement - Session cache fix added.

= 1.1 - 2020-06-05 =
* Tweak - Session class updated.

= 1.0 - 2020-05-03 =
* Initial release