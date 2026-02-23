<?php
/**
 * Puter.js MPN lookup - provides product data for client-side AI lookup.
 *
 * MPN lookup runs in the browser via Puter.js (https://docs.puter.com/).
 * No API keys required - uses Puter's user-pays model.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Puter_Lookup
 */
class Woo_MPN_Puter_Lookup {

	/**
	 * Get product data for Puter.js MPN/EAN lookup.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return array Array of { id, title, url }.
	 */
	public static function get_products_for_lookup( array $product_ids ): array {
		$products = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Skip products that already have both MPN and EAN.
			$existing_mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
			$existing_ean = Woo_MPN_Product_Fields::get_product_ean( $product );
			if ( '' !== $existing_mpn && '' !== $existing_ean ) {
				continue;
			}

			$products[] = array(
				'id'    => $product->get_id(),
				'title' => $product->get_name(),
				'url'   => get_permalink( $product->get_id() ),
			);
		}

		return $products;
	}
}
