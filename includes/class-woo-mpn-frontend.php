<?php
/**
 * Frontend display of MPN on product pages.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Frontend
 */
class Woo_MPN_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_meta_start', array( $this, 'display_mpn' ), 15 );
	}

	/**
	 * Display MPN on the single product page.
	 * Uses the same structure as SKU (sku_wrapper, title, sku) for theme alignment.
	 */
	public function display_mpn(): void {
		if ( ! Woo_MPN_Settings::display_on_product_page() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
		if ( '' === $mpn ) {
			return;
		}

		?>
		<span class="sku_wrapper"><span class="title"><?php esc_html_e( 'MPN', 'woo-mpn' ); ?></span><span class="sku"><?php echo esc_html( $mpn ); ?></span></span>
		<?php
	}
}
