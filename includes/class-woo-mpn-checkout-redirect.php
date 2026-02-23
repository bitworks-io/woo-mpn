<?php
/**
 * Redirect checkout URLs with feed IDs to use WooCommerce product IDs.
 *
 * Maps products=woocommerce_gpf_123:1 to products=123:1 so sharable checkout
 * URLs from Google (using feed IDs) work. See:
 * https://woocommerce.com/document/creating-sharable-checkout-urls-in-woocommerce/
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Checkout_Redirect
 */
class Woo_MPN_Checkout_Redirect {

	/**
	 * Prefix used by WooCommerce Google Product Feed for item IDs.
	 */
	private const FEED_ID_PREFIX = 'woocommerce_gpf_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 5 );
	}

	/**
	 * Redirect if products param contains feed IDs and setting is enabled.
	 */
	public function maybe_redirect(): void {
		if ( ! Woo_MPN_Settings::redirect_map_feed_ids() ) {
			return;
		}

		$products = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';
		if ( '' === $products || strpos( $products, self::FEED_ID_PREFIX ) === false ) {
			return;
		}

		$mapped = $this->map_feed_ids_to_product_ids( $products );
		if ( $mapped === $products ) {
			return;
		}

		$url = add_query_arg( 'products', $mapped );
		wp_safe_redirect( $url, 302 );
		exit;
	}

	/**
	 * Replace feed IDs (woocommerce_gpf_123) with WooCommerce product IDs (123).
	 *
	 * Format: PRODUCT_ID:QUANTITY,PRODUCT_ID:QUANTITY
	 *
	 * @param string $products Products parameter value.
	 * @return string Mapped value.
	 */
	private function map_feed_ids_to_product_ids( string $products ): string {
		$pairs = explode( ',', $products );
		$mapped = array();

		foreach ( $pairs as $pair ) {
			$parts = explode( ':', $pair, 2 );
			$id    = trim( $parts[0] ?? '' );
			$qty   = isset( $parts[1] ) ? ':' . trim( $parts[1] ) : '';

			if ( strpos( $id, self::FEED_ID_PREFIX ) === 0 ) {
				$id = substr( $id, strlen( self::FEED_ID_PREFIX ) );
			}

			$mapped[] = $id . $qty;
		}

		return implode( ',', $mapped );
	}
}
