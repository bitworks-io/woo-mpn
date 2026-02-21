<?php
/**
 * Plugin Name: Manufacturer Product Number (MPN)
 * Plugin URI: https://tyriatech.com/woo-mpn
 * Description: Adds a Manufacturer Product Number (MPN) field to WooCommerce products, displayed in the SKU section and product table.
 * Version: 1.3.24
 * Author: Tyria Tech
 * Author URI: https://tyriatech.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-mpn
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * Requires Plugins: woocommerce
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

// Declare HPOS (High Performance Order Storage) compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

define( 'WOO_MPN_VERSION', '1.3.24' );
define( 'WOO_MPN_PLUGIN_FILE', __FILE__ );
define( 'WOO_MPN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function woo_mpn_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Initialize the plugin.
 */
function woo_mpn_init() {
	if ( ! woo_mpn_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'woo_mpn_woocommerce_missing_notice' );
		return;
	}

	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-product-fields.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-admin-columns.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-settings.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-frontend.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-puter-lookup.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/admin/class-woo-mpn-product-list-table.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/admin/class-woo-mpn-admin-page.php';
	require_once WOO_MPN_PLUGIN_PATH . 'includes/class-woo-mpn-register-field.php';

	new Woo_MPN_Product_Fields();
	new Woo_MPN_Admin_Columns();
	new Woo_MPN_Settings();
	new Woo_MPN_Frontend();
	new Woo_MPN_Admin_Page();
	new Woo_MPN_Register_Field();

	add_filter( 'plugin_action_links_' . plugin_basename( WOO_MPN_PLUGIN_FILE ), 'woo_mpn_plugin_action_links' );
}
// Load early (priority 5) so feed plugins pick up our attribute mapping filters.
add_action( 'plugins_loaded', 'woo_mpn_init', 5 );

/**
 * Add settings link to plugin actions.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function woo_mpn_plugin_action_links( array $links ): array {
	$products_url = admin_url( 'admin.php?page=woo-mpn-products' );
	$settings_url = admin_url( 'admin.php?page=wc-settings&tab=products&section=woo_mpn' );
	array_unshift( $links, '<a href="' . esc_url( $products_url ) . '">' . esc_html__( 'MPN Products', 'woo-mpn' ) . '</a>' );
	$links[] = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woo-mpn' ) . '</a>';
	return $links;
}

/**
 * Admin notice when WooCommerce is not active.
 */
function woo_mpn_woocommerce_missing_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( 'Manufacturer Product Number (MPN) requires %s to be installed and active.', 'woo-mpn' ),
				'<strong>WooCommerce</strong>'
			);
			?>
		</p>
	</div>
	<?php
}
