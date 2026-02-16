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
	 * Section ID for WooCommerce settings.
	 */
	public const SECTION_ID = 'woo_mpn';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'add_settings' ), 10, 2 );
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
				'type' => 'sectionend',
				'id'   => 'woo_mpn_options',
			),
		);

		return $mpn_settings;
	}

	/**
	 * Check if MPN should be displayed on the product page.
	 *
	 * @return bool
	 */
	public static function display_on_product_page(): bool {
		return 'yes' === get_option( self::OPTION_DISPLAY_ON_PRODUCT, 'no' );
	}
}
