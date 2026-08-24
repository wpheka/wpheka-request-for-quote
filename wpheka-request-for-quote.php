<?php
/**
 * Plugin Name: Request For Quote
 * Plugin URI: https://www.wpheka.com/product/request-for-quote/
 * Description: The <code><strong>Request For Quote</strong></code> plugin allows your customers to submit quotes for any product and negotiate with you for the best price.
 * Version: 1.8.2
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Text Domain: wpheka-request-for-quote
 * Domain Path: /languages/
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 11.0.1
 * Woo: 10.7.0
 * License: GPLv3 or later
 *
 * @package   WPHEKA_Rfq
 * @author    WPHEKA
 * @link      https://wpheka.com
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define WPHEKA_RFQ_PLUGIN_FILE.
if (! defined('WPHEKA_RFQ_PLUGIN_FILE')) {
    define('WPHEKA_RFQ_PLUGIN_FILE', __FILE__);
}

// Define WPHEKA_RFQ_VERSION.
if (! defined('WPHEKA_RFQ_VERSION')) {
    define('WPHEKA_RFQ_VERSION', '1.8.2');
}

define('WPHEKA_RFQ_MIN_FRAMEWORK', '1.0.0');

/*
 * Loaded at include time so the registry resolves before plugins_loaded. The
 * classes themselves are not available yet -- the autoloader registers on
 * plugins_loaded at -100 -- so nothing may touch them until then.
 */
if (is_readable(__DIR__ . '/framework/register.php')) {
    require_once __DIR__ . '/framework/register.php';
}

/**
 * Whether a usable framework booted.
 *
 * Every framework class this plugin touches is named, not just the version: a
 * version check passes while the module set is still incomplete, because
 * bundling is modular and another plugin's bundle can win.
 *
 * @since 1.8.0
 * @return bool
 */
function wpheka_rfq_framework_ready()
{
    if (! class_exists('WPHEKA_Framework_Versions', false)) {
        return false;
    }

    $active = WPHEKA_Framework_Versions::active_version('1');

    if (! is_string($active) || ! version_compare($active, WPHEKA_RFQ_MIN_FRAMEWORK, '>=')) {
        return false;
    }

    foreach (array(
        '\\WPHEKA\\Framework\\V1\\Core\\Options',
        '\\WPHEKA\\Framework\\V1\\Core\\Lifecycle',
        '\\WPHEKA\\Framework\\V1\\Database\\Schema',
        '\\WPHEKA\\Framework\\V1\\WooCommerce\\Compatibility',
    ) as $class) {
        if (! class_exists($class)) {
            return false;
        }
    }

    return true;
}

/**
 * The plugin's settings row.
 *
 * Reads the option name the plugin has always used, so there is no migration
 * and no window where settings appear empty.
 *
 * @since 1.8.0
 * @return \WPHEKA\Framework\V1\Core\Options
 */
function wpheka_rfq_options()
{
    static $options = null;

    if (null === $options) {
        /*
         * Explicit per-site scope, not for_plugin(). for_plugin() picks network
         * storage when the plugin is network-activated, but WPHEKA_Rfq_Install
         * writes its defaults with add_option() -- per-site, always, as it did
         * before adoption. Auto-detecting here would make runtime read a network
         * row that provisioning never wrote, and the settings would read back
         * empty on every site of a network with nothing reporting why.
         *
         * Per-site is also the right answer on its own terms: quote baskets and
         * the sessions table are per-site (Schema::SCOPE_SITE), so the settings
         * that govern them belong beside them.
         */
        $options = new \WPHEKA\Framework\V1\Core\Options(
            'wpheka_rfq_general_settings',
            array(),
            \WPHEKA\Framework\V1\Core\Options::SCOPE_SITE
        );
    }

    return $options;
}

/**
 * The sessions table.
 *
 * Columns are byte-for-byte what WPHEKA_Rfq_Install has always passed to
 * dbDelta, including the two spaces before (session_id) that dbDelta requires.
 * Any difference here would make dbDelta ALTER a table on every existing
 * install rather than leave it alone.
 *
 * SCOPE_SITE because a session belongs to one site: on a network each site
 * keeps its own quote baskets. The two scopes resolve to the same string on
 * single site, so getting this wrong stays invisible until a customer runs a
 * network.
 *
 * @since 1.8.0
 * @return \WPHEKA\Framework\V1\Database\Schema
 */
function wpheka_rfq_schema()
{
    $schema = '\WPHEKA\Framework\V1\Database\Schema';

    return new $schema(
        array(
            'wpheka_rfq_sessions' => array(
                'scope'   => $schema::SCOPE_SITE,
                'columns' => 'session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_key char(32) NOT NULL,
  session_value longtext NOT NULL,
  session_expiry BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY  (session_id),
  UNIQUE KEY session_key (session_key)',
            ),
        )
    );
}

/**
 * Declares this plugin's WooCommerce feature compatibility.
 *
 * Hooked to plugins_loaded rather than called at include time: the framework
 * autoloader only registers on plugins_loaded at -100, so at include time
 * Compatibility does not exist and a class_exists() guard would skip the
 * declaration silently, leaving WooCommerce to list the plugin as
 * HPOS-incompatible while the code looked correct.
 *
 * **Both features are declared compatible, and the blocks one was checked
 * rather than assumed.** This plugin registers no cart or checkout hooks at all
 * -- only product page, shop loop, price and email hooks -- and its session
 * handler extends WC_Session into its own property via its own
 * `wpheka_rfq_session_handler` filter. It never replaces WooCommerce's handler
 * through `woocommerce_session_handler`, so WC sessions and the Store API the
 * Cart and Checkout blocks run on are untouched.
 *
 * It previously declared `cart_checkout_blocks` **incompatible**, which was
 * inaccurate: WooCommerce warns store owners off block checkout by naming
 * plugins that declare that, and nothing here interacts with those blocks.
 *
 * @since 1.8.0
 * @return void
 */
function wpheka_rfq_declare_compatibility()
{
    if (! wpheka_rfq_framework_ready()) {
        // Declare it by hand, so a bundle that lost the WooCommerce module does
        // not silently drop either verdict.
        add_action(
            'before_woocommerce_init',
            static function () {
                if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WPHEKA_RFQ_PLUGIN_FILE, true);
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WPHEKA_RFQ_PLUGIN_FILE, true);
                }
            }
        );

        return;
    }

    $compatibility = '\WPHEKA\Framework\V1\WooCommerce\Compatibility';

    $compatibility::declare_for(
        WPHEKA_RFQ_PLUGIN_FILE,
        array(
            $compatibility::HPOS   => true,
            $compatibility::BLOCKS => true,
        )
    );
}
add_action('plugins_loaded', 'wpheka_rfq_declare_compatibility');

/**
 * Provisioning for a site added to the network after activation.
 *
 * Wired with plain WordPress functions at include time. A class_exists() guard
 * here would be false on a normal request, because the autoloader only
 * registers on plugins_loaded at -100.
 *
 * Without this hook, a site created later has no sessions table and no default
 * settings, and nothing ever says so (ADR-012). That was the state before
 * adoption -- the plugin registered an activation hook and nothing else.
 *
 * @since 1.8.0
 * @param mixed $site New site.
 * @return void
 */
function wpheka_rfq_new_site($site)
{
    if (! wpheka_rfq_framework_ready()) {
        /*
         * Degrading must not mean skipping provisioning. Returning here left the
         * new site with no sessions table and no settings, and nothing saying
         * so -- the exact ADR-012 failure this hook exists to prevent,
         * reintroduced in the path taken when the framework is unavailable.
         *
         * Not hypothetical: with a stale bundle winning the registry the guard
         * really does return false, and a five-site network then provisioned
         * one site.
         */
        wpheka_rfq_provision_site_natively($site);

        return;
    }

    \WPHEKA\Framework\V1\Core\Lifecycle::on_new_site($site, WPHEKA_RFQ_PLUGIN_FILE, array( 'WPHEKA_Rfq_Install', 'install' ));
}

/**
 * Provision one site without the framework.
 *
 * Only for the degraded path. Checks the plugin is actually network-active
 * before writing to a site, which is what Lifecycle::on_new_site() does and
 * what stops a network activating this plugin on sites that never asked for it.
 *
 * @since 1.8.0
 * @param mixed $site WP_Site, or a blog id.
 * @return void
 */
function wpheka_rfq_provision_site_natively($site)
{
    if (! is_multisite() || ! class_exists('WPHEKA_Rfq_Install')) {
        return;
    }

    if (! function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (! is_plugin_active_for_network(plugin_basename(WPHEKA_RFQ_PLUGIN_FILE))) {
        return;
    }

    $blog_id = is_object($site) && isset($site->blog_id) ? (int) $site->blog_id : (int) $site;

    if ($blog_id <= 0) {
        return;
    }

    switch_to_blog($blog_id);

    try {
        WPHEKA_Rfq_Install::install();
    } finally {
        restore_current_blog();
    }
}
add_action('wp_initialize_site', 'wpheka_rfq_new_site', 100);

register_activation_hook(WPHEKA_RFQ_PLUGIN_FILE, 'wpheka_rfq_activate');

/**
 * Activation.
 *
 * Previously the activation hook called WPHEKA_Rfq_Install::install() directly,
 * which runs once against whichever site handled the request. On a network
 * activation that provisions the one site and leaves every other site in the
 * network without a sessions table or default settings. Lifecycle::activate()
 * is what walks the sites.
 *
 * Provisioning stays idempotent either way: install() already guards on a
 * transient, uses dbDelta, and only writes defaults when the option is empty.
 *
 * @since 1.8.0
 * @param bool $network_wide Whether the plugin is being network-activated.
 * @return void
 */
function wpheka_rfq_activate($network_wide = false)
{
    if (! wpheka_rfq_framework_ready()) {
        /*
         * install() provisions whichever single site handles the request, so on
         * a network activation this used to leave every other site without a
         * sessions table. Observed on a five-site network: one table, four
         * sites silently unprovisioned.
         *
         * Lifecycle::for_each_site() is the proper walk and batches; this is the
         * degraded path, so it iterates directly.
         */
        if ($network_wide && is_multisite()) {
            foreach (get_sites(array( 'fields' => 'ids', 'number' => 0 )) as $blog_id) {
                switch_to_blog((int) $blog_id);

                try {
                    WPHEKA_Rfq_Install::install();
                } finally {
                    restore_current_blog();
                }
            }

            return;
        }

        WPHEKA_Rfq_Install::install();

        return;
    }

    \WPHEKA\Framework\V1\Core\Lifecycle::activate(array( 'WPHEKA_Rfq_Install', 'install' ), (bool) $network_wide);
}

// Include the main WPHEKA_Rfq class.
if (! class_exists('WPHEKA_Rfq')) {
    include_once dirname(__FILE__) . '/includes/class-wpheka-rfq.php';
}

/**
 * Main instance of WPHEKA_Rfq.
 *
 * Returns the main instance of WPHEKA_Rfq to prevent the need to use globals.
 *
 * @since  1.0
 * @return WPHEKA_Rfq
 */
function wpheka_request_for_quote()
{
    return WPHEKA_Rfq::instance();
}

// Global for backwards compatibility.
$GLOBALS['wp_heka_rfq'] = wpheka_request_for_quote();
