<?php
/**
 * Plugin settings for MPN display.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Settings
 */
class Woo_MPN_Settings {

	/**
	 * Option key for display on product page setting.
	 */
	public const OPTION_DISPLAY_ON_PRODUCT = 'woo_mpn_display_on_product';

	/**
	 * Option key for redirecting feed IDs to WooCommerce product IDs in checkout URLs.
	 */
	public const OPTION_REDIRECT_FEED_IDS = 'woo_mpn_redirect_feed_ids';

	/**
	 * Option key for EAN source (which field/attribute to use for EAN).
	 */
	public const OPTION_EAN_SOURCE = 'woo_mpn_ean_source';

	/**
	 * Option key for custom EAN source (meta key or attribute slug when source is 'custom').
	 */
	public const OPTION_EAN_CUSTOM_SOURCE = 'woo_mpn_ean_custom_source';

	/**
	 * Section ID for WooCommerce settings.
	 */
	public const SECTION_ID = 'woo_mpn';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_update_options_products_woo_mpn', array( $this, 'save_redirect_option' ) );
		add_action( 'woocommerce_update_options_products_woo_mpn', array( $this, 'save_ean_options' ) );
	}

	/**
	 * Add MPN section to Products settings.
	 *
	 * @param array $sections Existing sections.
	 * @return array
	 */
	public function add_section( array $sections ): array {
		$sections[ self::SECTION_ID ] = __( 'Manufacturer Product Number (MPN)', 'woo-mpn' );
		return $sections;
	}

	/**
	 * Add settings to the MPN section.
	 *
	 * @param array  $settings       Existing settings.
	 * @param string $current_section Current section ID.
	 * @return array
	 */
	public function add_settings( array $settings, string $current_section ): array {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		$mpn_settings = array(
			array(
				'name' => __( 'MPN Display Settings', 'woo-mpn' ),
				'type' => 'title',
				'desc' => __( 'Configure how the Manufacturer Product Number is displayed on your store.', 'woo-mpn' ),
				'id'   => 'woo_mpn_options',
			),
			array(
				'name'     => __( 'Display on product page', 'woo-mpn' ),
				'desc'     => __( 'Show MPN alongside SKU and other product details on the single product page.', 'woo-mpn' ),
				'id'       => self::OPTION_DISPLAY_ON_PRODUCT,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			),
			array(
				'name'     => __( 'Add redirect to map product IDs to WooCommerce Product Feed automatically generated IDs', 'woo-mpn' ),
				'desc'     => __( 'Allows checkout URL generation from Google. When enabled, URLs using the products parameter with feed IDs (e.g. products=woocommerce_gpf_123:1) are redirected to use WooCommerce product IDs (e.g. products=123:1) so sharable checkout links work.', 'woo-mpn' ),
				'id'       => self::OPTION_REDIRECT_FEED_IDS,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_mpn_options',
			),
			array(
				'name' => __( 'EAN / GTIN Source', 'woo-mpn' ),
				'type' => 'title',
				'desc' => __( 'Choose where EAN/GTIN values come from. If another plugin provides EAN, select its field. Otherwise use the plugin field.', 'woo-mpn' ),
				'id'   => 'woo_mpn_ean_options',
			),
			array(
				'name'     => __( 'EAN source', 'woo-mpn' ),
				'id'       => self::OPTION_EAN_SOURCE,
				'type'     => 'select',
				'options'  => array(
					'_ean'               => __( 'Plugin EAN field (_ean)', 'woo-mpn' ),
					'_global_unique_id'  => __( 'WooCommerce Global Unique ID (9.2+)', 'woo-mpn' ),
					'_gtin'              => __( 'Custom: _gtin', 'woo-mpn' ),
					'_ts_gtin'           => __( 'Custom: _ts_gtin (Germanized)', 'woo-mpn' ),
					'custom'             => __( 'Custom field or attribute (specify below)', 'woo-mpn' ),
				),
				'default'  => '_ean',
				'autoload' => false,
			),
			array(
				'name'        => __( 'Custom EAN source', 'woo-mpn' ),
				'desc'        => __( 'When "Custom field or attribute" is selected: enter a meta key (e.g. _gtin) or product attribute slug (e.g. pa_ean).', 'woo-mpn' ),
				'id'          => self::OPTION_EAN_CUSTOM_SOURCE,
				'type'        => 'text',
				'default'     => '',
				'autoload'    => false,
				'placeholder' => '_gtin or pa_ean',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'woo_mpn_ean_options',
			),
		);

		return $mpn_settings;
	}

	/**
	 * Explicitly save redirect option on section save (fallback for React settings UI).
	 */
	public function save_redirect_option(): void {
		if ( isset( $_POST[ self::OPTION_REDIRECT_FEED_IDS ] ) ) {
			update_option( self::OPTION_REDIRECT_FEED_IDS, 'yes', false );
		} else {
			update_option( self::OPTION_REDIRECT_FEED_IDS, 'no', false );
		}
	}

	/**
	 * Save EAN options.
	 */
	public function save_ean_options(): void {
		if ( isset( $_POST[ self::OPTION_EAN_SOURCE ] ) ) {
			update_option( self::OPTION_EAN_SOURCE, sanitize_text_field( wp_unslash( $_POST[ self::OPTION_EAN_SOURCE ] ) ), false );
		}
		if ( isset( $_POST[ self::OPTION_EAN_CUSTOM_SOURCE ] ) ) {
			update_option( self::OPTION_EAN_CUSTOM_SOURCE, sanitize_text_field( wp_unslash( $_POST[ self::OPTION_EAN_CUSTOM_SOURCE ] ) ), false );
		}
	}

	/**
	 * Check if MPN should be displayed on the product page.
	 *
	 * @return bool
	 */
	public static function display_on_product_page(): bool {
		return 'yes' === get_option( self::OPTION_DISPLAY_ON_PRODUCT, 'no' );
	}

	/**
	 * Check if redirect should map feed IDs to product IDs in checkout URLs.
	 *
	 * @return bool
	 */
	public static function redirect_map_feed_ids(): bool {
		return 'yes' === get_option( self::OPTION_REDIRECT_FEED_IDS, 'no' );
	}

	/**
	 * Get the configured EAN source.
	 *
	 * @return string One of: _ean, _global_unique_id, _gtin, _ts_gtin, custom.
	 */
	public static function get_ean_source(): string {
		return get_option( self::OPTION_EAN_SOURCE, '_ean' );
	}

	/**
	 * Get the custom EAN source (meta key or attribute slug) when source is 'custom'.
	 *
	 * @return string
	 */
	public static function get_ean_custom_source(): string {
		return get_option( self::OPTION_EAN_CUSTOM_SOURCE, '' );
	}
}
