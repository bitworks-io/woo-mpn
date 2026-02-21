<?php
/**
 * Register MPN as a selectable product field for feed mappings.
 *
 * Registers the MPN meta field (_mpn) as a selectable source in product
 * feed attribute mapping dropdowns. Supports multiple feed plugins:
 *
 * - Google for WooCommerce / Google Listings & Ads / WooCommerce.com Google Product Feed
 * - WooCommerce Google Product Feed (Lee Willis)
 * - Product Feed Manager (WPMarketingRobot)
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Register_Field
 */
class Woo_MPN_Register_Field {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Google for WooCommerce / Google Listings & Ads / WooCommerce.com Google Product Feed.
		// See: https://woocommerce.com/document/google-product-feed-customizations
		add_filter( 'woocommerce_gla_attribute_mapping_sources_custom_attributes', array( $this, 'add_mpn_to_custom_attributes' ) );
		add_filter( 'woocommerce_gla_attribute_mapping_sources_product_fields', array( $this, 'add_mpn_to_product_fields' ) );

		// WooCommerce Google Product Feed (Lee Willis - woocommerce-gpf).
		// Adds MPN to the custom field dropdown so it can be selected when configuring the feed.
		add_filter( 'woocommerce_gpf_custom_field_list', array( $this, 'gpf_add_mpn_to_custom_field_list' ) );
		// Injects MPN into feed items when mapped.
		add_filter( 'woocommerce_gpf_feed_item_google', array( $this, 'gpf_inject_mpn' ), 10, 2 );

		// Product Feed Manager (WPMarketingRobot - wppfm).
		// See: https://wpmarketingrobot.com/help-item/customizing/wordpress-hook-wppfm_feed_item_value
		add_filter( 'wppfm_feed_item_value', array( $this, 'wppfm_inject_mpn' ), 10, 3 );
	}

	/**
	 * Add _mpn to custom attributes (appears in Custom Attributes section).
	 *
	 * @param array $attribute_keys Array of meta keys.
	 * @return array
	 */
	public function add_mpn_to_custom_attributes( array $attribute_keys ): array {
		$meta_key = Woo_MPN_Product_Fields::META_KEY;
		if ( ! in_array( $meta_key, $attribute_keys, true ) ) {
			$attribute_keys[] = $meta_key;
		}
		return $attribute_keys;
	}

	/**
	 * Add MPN to product fields (appears in Product fields section with friendly label).
	 *
	 * @param array $fields Product field key => label.
	 * @return array
	 */
	public function add_mpn_to_product_fields( array $fields ): array {
		$meta_key = Woo_MPN_Product_Fields::META_KEY;
		$key      = 'product:' . $meta_key;
		if ( ! isset( $fields[ $key ] ) ) {
			$fields[ $key ] = __( 'Manufacturer Product Number (MPN)', 'woo-mpn' );
			asort( $fields );
		}
		return $fields;
	}

	/**
	 * Add MPN to the custom field list (WooCommerce Google Product Feed by Lee Willis).
	 * Makes MPN selectable in the feed configuration dropdown.
	 *
	 * @param array $list Custom field key => label.
	 * @return array
	 */
	public function gpf_add_mpn_to_custom_field_list( array $list ): array {
		$meta_key = Woo_MPN_Product_Fields::META_KEY;
		$key      = 'meta:' . $meta_key;
		if ( ! isset( $list[ $key ] ) ) {
			$list[ $key ] = __( 'Manufacturer Product Number (MPN)', 'woo-mpn' );
		}
		return $list;
	}

	/**
	 * Inject MPN into feed item (WooCommerce Google Product Feed by Lee Willis).
	 *
	 * @param object $feed_item Feed item object.
	 * @return object
	 */
	public function gpf_inject_mpn( $feed_item ) {
		$product_id = $feed_item->ID ?? $feed_item->product_id ?? 0;
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
				if ( '' !== $mpn ) {
					$feed_item->mpn = $mpn;
				}
			}
		}
		return $feed_item;
	}

	/**
	 * Inject MPN into feed attributes (Product Feed Manager by WPMarketingRobot).
	 *
	 * @param array $attributes Feed item attributes.
	 * @param int   $feed_id    Feed ID.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public function wppfm_inject_mpn( array $attributes, $feed_id, $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
			if ( '' !== $mpn ) {
				$attributes['mpn'] = $mpn;
			}
		}
		return $attributes;
	}
}
