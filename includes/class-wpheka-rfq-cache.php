<?php
/**
 * Page-cache co-operation.
 *
 * @package WPHEKA_Rfq
 * @since   1.8.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPHEKA_Rfq_Cache', false ) ) :

	/**
	 * WPHEKA_Rfq_Cache Class.
	 *
	 * Two separate problems, both of which look to a shop owner like "the
	 * plugin is broken", and neither of which the plugin used to address:
	 *
	 * 1. Saving settings changed nothing on the front end, because
	 *    `hide_price`, `hide_add_to_cart`, `button_type`, `button_position`,
	 *    `button_link_text` and the rest all alter product and shop page
	 *    markup -- and those pages were already sitting in a full-page cache.
	 *    The settings really were saved; the visitor was served yesterday's
	 *    HTML. See purge_all().
	 *
	 * 2. The quote page renders one visitor's basket. Full-page caches leave
	 *    WooCommerce's cart, checkout and my-account pages alone because
	 *    WooCommerce marks them, but nothing marked *this* plugin's quote
	 *    page -- so it was cacheable, and the first visitor's quote list could
	 *    be served to the next visitor. See prevent_caching().
	 *
	 * @class WPHEKA_Rfq_Cache
	 */
	class WPHEKA_Rfq_Cache {


		/**
		 * Hook in.
		 *
		 * @since 1.8.1
		 * @return void
		 */
		public static function init() {
			/*
			 * Priority 5 on `wp_headers`, which is what WC_Cache_Helper uses
			 * for cart and checkout, and for the same reason: low enough that a
			 * plugin wanting to enforce `no-store` on top still can.
			 *
			 * `wp_headers` is late enough to ask which page this is --
			 * WP::main() runs query_posts() and register_globals() before
			 * send_headers() -- and early enough that the page cache has not
			 * yet decided to store the response.
			 */
			add_filter( 'wp_headers', array( __CLASS__, 'prevent_caching' ), 5 );
		}

		/**
		 * Keep the quote page out of page caches.
		 *
		 * The template already called wc_nocache_headers(), which was too late
		 * and too little: too late because a page cache has started buffering
		 * output by the time a template renders, and too little because it only
		 * ran in request-for-quote.php -- never in request-for-quote-empty.php,
		 * so an empty basket was cached and then served to a visitor whose
		 * basket was not empty.
		 *
		 * @since 1.8.1
		 * @param array $headers Headers WordPress is about to send.
		 * @return array
		 */
		public static function prevent_caching( $headers ) {
			if ( ! is_array( $headers ) || ! self::is_visitor_specific_page() ) {
				return $headers;
			}

			self::set_nocache_constants();

			/*
			 * LiteSpeed does not read DONOTCACHEPAGE; it has its own switch, and
			 * on LiteSpeed hosting it is the cache that is actually in front of
			 * the site.
			 */
			do_action( 'litespeed_control_set_nocache', 'wpheka-request-for-quote: per-visitor quote basket' );

			/*
			 * Merge Cache-Control directives rather than overwrite them. Another
			 * plugin may already have set something stricter than ours, and
			 * replacing the header wholesale would quietly relax it.
			 */
			$nocache = wp_get_nocache_headers();

			unset( $nocache['Last-Modified'] );

			$directives = array_merge(
				self::split_directives( isset( $headers['Cache-Control'] ) ? $headers['Cache-Control'] : '' ),
				self::split_directives( isset( $nocache['Cache-Control'] ) ? $nocache['Cache-Control'] : '' )
			);

			$headers = array_merge( $headers, $nocache );

			if ( ! empty( $directives ) ) {
				$headers['Cache-Control'] = implode( ', ', array_unique( $directives ) );
			}

			return $headers;
		}

		/**
		 * Whether this request renders something specific to one visitor.
		 *
		 * @since 1.8.1
		 * @return bool
		 */
		private static function is_visitor_specific_page() {
			if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
				return false;
			}

			/*
			 * REST responses, feeds and robots.txt never render the quote page,
			 * and marking them uncacheable would throw away caching that has
			 * nothing to do with this plugin.
			 */
			if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_robots() || is_trackback() ) {
				return false;
			}

			/*
			 * is_rfq_page() calls wc_post_content_has_shortcode(). This filter
			 * runs on every front-end request, so a WooCommerce that is not
			 * there -- deactivated, or fatal before this point -- would take the
			 * whole site down rather than just this plugin. Cheap insurance for
			 * a hook this hot.
			 *
			 * The check is on the function, not on a class or a version: that is
			 * the exact thing about to be called.
			 */
			if ( ! function_exists( 'wpheka_request_for_quote' ) || ! function_exists( 'wc_post_content_has_shortcode' ) ) {
				return false;
			}

			return (bool) apply_filters( 'wpheka_rfq_prevent_page_caching', wpheka_request_for_quote()->is_rfq_page() );
		}

		/**
		 * Split a Cache-Control field value into directives.
		 *
		 * @since 1.8.1
		 * @param string $value Header field value.
		 * @return array
		 */
		private static function split_directives( $value ) {
			$value = trim( (string) $value );

			if ( '' === $value ) {
				return array();
			}

			return array_filter( preg_split( '/\s*,\s*/', $value ) );
		}

		/**
		 * Define the constants page-cache plugins look for.
		 *
		 * @since 1.8.1
		 * @return void
		 */
		public static function set_nocache_constants() {
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				WC_Cache_Helper::set_nocache_constants();

				return;
			}

			foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
				if ( ! defined( $constant ) ) {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- these three names are defined by the cache plugins that read them, not by this plugin.
					define( $constant, true );
				}
			}
		}

		/**
		 * Purge front-end page caches.
		 *
		 * Called when settings are saved. A settings save is rare and always
		 * deliberate, so a full purge is the right trade: the alternative is
		 * guessing which product, shop and taxonomy URLs a given setting
		 * touches, and being wrong in a way nobody can debug from the admin.
		 *
		 * Every branch is guarded, so this is a no-op on a site with no page
		 * cache at all.
		 *
		 * @since 1.8.1
		 * @return void
		 */
		public static function purge_all() {
			// WP Rocket.
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}

			// W3 Total Cache.
			if ( function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
			} elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
				w3tc_pgcache_flush();
			}

			// WP Super Cache.
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
			}

			// WP Fastest Cache.
			if ( isset( $GLOBALS['wp_fastest_cache'] ) && is_callable( array( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) ) {
				$GLOBALS['wp_fastest_cache']->deleteCache( true );
			}

			// Comet Cache.
			if ( class_exists( 'comet_cache' ) && is_callable( array( 'comet_cache', 'clear' ) ) ) {
				comet_cache::clear();
			}

			// Autoptimize -- CSS/JS only, but the quote button carries its own styles.
			if ( class_exists( 'autoptimizeCache' ) && is_callable( array( 'autoptimizeCache', 'clearall' ) ) ) {
				autoptimizeCache::clearall();
			}

			/*
			 * Action-based purges. Each of these is the documented entry point
			 * for its plugin; firing an action nobody listens to costs nothing,
			 * so they are not guarded individually.
			 */
			do_action( 'litespeed_purge_all' );                  // LiteSpeed Cache.
			do_action( 'cache_enabler_clear_complete_cache' );   // Cache Enabler.
			do_action( 'siteground_optimizer_flush_cache' );     // SiteGround Optimizer.
			do_action( 'breeze_clear_all_cache' );               // Breeze (Cloudways).
			do_action( 'wphb_clear_page_cache' );                // Hummingbird.
			do_action( 'swift_performance_clear_all_cache' );    // Swift Performance.
			do_action( 'rt_nginx_helper_purge_all' );            // Nginx Helper.
			do_action( 'kinsta_cache_purge_all' );               // Kinsta.
			do_action( 'nginx_cache_purge_all' );                // Various Nginx front ends.

			/**
			 * Fires after the plugin has asked every page cache it knows about
			 * to purge.
			 *
			 * For a host or cache this plugin has never heard of.
			 *
			 * @since 1.8.1
			 */
			do_action( 'wpheka_rfq_purge_caches' );
		}
	}

endif;
