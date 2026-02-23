<?php
/**
 * Register MPN and EAN as selectable product fields for feed mappings.
 *
 * Registers the MPN meta field (_mpn) and EAN (from configured source) as
 * selectable sources in product feed attribute mapping dropdowns.
 * Supports multiple feed plugins:
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
		add_filter( 'woocommerce_gla_attribute_mapping_sources_custom_attributes', array( $this, 'add_ean_to_custom_attributes' ) );
		add_filter( 'woocommerce_gla_attribute_mapping_sources_product_fields', array( $this, 'add_ean_to_product_fields' ) );

		// WooCommerce Google Product Feed (Lee Willis - woocommerce-gpf).
		// Adds MPN and EAN to the custom field dropdown so they can be selected when configuring the feed.
		add_filter( 'woocommerce_gpf_custom_field_list', array( $this, 'gpf_add_mpn_to_custom_field_list' ) );
		add_filter( 'woocommerce_gpf_custom_field_list', array( $this, 'gpf_add_ean_to_custom_field_list' ) );
		// Injects MPN and EAN into feed items.
		add_filter( 'woocommerce_gpf_feed_item_google', array( $this, 'gpf_inject_mpn' ), 10, 2 );
		add_filter( 'woocommerce_gpf_feed_item_google', array( $this, 'gpf_inject_ean' ), 10, 2 );

		// Product Feed Manager (WPMarketingRobot - wppfm).
		// See: https://wpmarketingrobot.com/help-item/customizing/wordpress-hook-wppfm_feed_item_value
		add_filter( 'wppfm_feed_item_value', array( $this, 'wppfm_inject_mpn' ), 10, 3 );
		add_filter( 'wppfm_feed_item_value', array( $this, 'wppfm_inject_ean' ), 10, 3 );
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
	 * Add EAN meta keys to custom attributes (GLA).
	 * Adds plugin field and common alternatives so users can map EAN from their source.
	 *
	 * @param array $attribute_keys Array of meta keys.
	 * @return array
	 */
	public function add_ean_to_custom_attributes( array $attribute_keys ): array {
		$keys = array( Woo_MPN_Product_Fields::META_KEY_EAN, '_gtin', '_ts_gtin' );
		$source = Woo_MPN_Settings::get_ean_source();
		if ( 'custom' === $source ) {
			$custom = Woo_MPN_Settings::get_ean_custom_source();
			if ( '' !== $custom && strpos( $custom, 'pa_' ) !== 0 ) {
				$keys[] = $custom;
			}
		}
		foreach ( $keys as $key ) {
			if ( ! in_array( $key, $attribute_keys, true ) ) {
				$attribute_keys[] = $key;
			}
		}
		return $attribute_keys;
	}

	/**
	 * Add EAN to product fields (GLA).
	 *
	 * @param array $fields Product field key => label.
	 * @return array
	 */
	public function add_ean_to_product_fields( array $fields ): array {
		$meta_key = Woo_MPN_Product_Fields::META_KEY_EAN;
		$key      = 'product:' . $meta_key;
		if ( ! isset( $fields[ $key ] ) ) {
			$fields[ $key ] = __( 'EAN / GTIN', 'woo-mpn' );
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
	 * Add EAN to the custom field list (WooCommerce Google Product Feed by Lee Willis).
	 *
	 * @param array $list Custom field key => label.
	 * @return array
	 */
	public function gpf_add_ean_to_custom_field_list( array $list ): array {
		$meta_key = Woo_MPN_Product_Fields::META_KEY_EAN;
		$key      = 'meta:' . $meta_key;
		if ( ! isset( $list[ $key ] ) ) {
			$list[ $key ] = __( 'EAN / GTIN', 'woo-mpn' );
		}
		return $list;
	}

	/**
	 * Inject MPN into feed item (WooCommerce Google Product Feed by Lee Willis).
	 *
	 * @param object        $feed_item Feed item object.
	 * @param WC_Product|null $product  WooCommerce product (when provided by filter).
	 * @return object
	 */
	public function gpf_inject_mpn( $feed_item, $product = null ) {
		if ( ! $product ) {
			$product_id = $feed_item->ID ?? $feed_item->product_id ?? 0;
			$product    = $product_id ? wc_get_product( $product_id ) : null;
		}
		if ( $product ) {
			$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
			if ( '' !== $mpn ) {
				$feed_item->mpn = $mpn;
			}
		}
		return $feed_item;
	}

	/**
	 * Inject EAN/GTIN into feed item (WooCommerce Google Product Feed by Lee Willis).
	 *
	 * @param object          $feed_item Feed item object.
	 * @param WC_Product|null $product   WooCommerce product (when provided by filter).
	 * @return object
	 */
	public function gpf_inject_ean( $feed_item, $product = null ) {
		if ( ! $product ) {
			$product_id = $feed_item->ID ?? $feed_item->product_id ?? 0;
			$product    = $product_id ? wc_get_product( $product_id ) : null;
		}
		if ( $product ) {
			$ean = Woo_MPN_Product_Fields::get_product_ean( $product );
			if ( '' !== $ean ) {
				$feed_item->gtin = $ean;
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

	/**
	 * Inject EAN/GTIN into feed attributes (Product Feed Manager by WPMarketingRobot).
	 *
	 * @param array $attributes Feed item attributes.
	 * @param int   $feed_id    Feed ID.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public function wppfm_inject_ean( array $attributes, $feed_id, $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$ean = Woo_MPN_Product_Fields::get_product_ean( $product );
			if ( '' !== $ean ) {
				$attributes['gtin'] = $ean;
			}
		}
		return $attributes;
	}
}
