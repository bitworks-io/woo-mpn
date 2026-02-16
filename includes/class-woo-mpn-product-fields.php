<?php
/**
 * Product MPN field in the SKU section.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Product_Fields
 */
class Woo_MPN_Product_Fields {

	/**
	 * Meta key for storing MPN.
	 */
	public const META_KEY = '_mpn';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_mpn_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_mpn_field' ), 10, 2 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_mpn_field_variation' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'save_mpn_field_variation' ), 10, 2 );
	}

	/**
	 * Add MPN field to the product SKU section.
	 */
	public function add_mpn_field(): void {
		global $product_object;

		if ( ! $product_object ) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_KEY,
				'label'       => __( 'Manufacturer Product Number (MPN)', 'woo-mpn' ),
				'placeholder' => __( 'Enter MPN', 'woo-mpn' ),
				'desc_tip'    => true,
				'description' => __( 'The manufacturer\'s part number or product identifier.', 'woo-mpn' ),
				'value'       => $product_object->get_meta( self::META_KEY ),
			)
		);
	}

	/**
	 * Save MPN field for simple products.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_mpn_field( int $post_id, $post ): void {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		$mpn = isset( $_POST[ self::META_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';
		$product->update_meta_data( self::META_KEY, $mpn );
		$product->save();
	}

	/**
	 * Add MPN field to variation products.
	 *
	 * @param int     $loop           Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function add_mpn_field_variation( int $loop, array $variation_data, $variation ): void {
		$variation_product = wc_get_product( $variation->ID );
		if ( ! $variation_product ) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'            => 'variable_mpn_' . $loop,
				'name'          => 'variable_mpn[' . $loop . ']',
				'label'         => __( 'MPN', 'woo-mpn' ),
				'placeholder'   => __( 'Enter MPN', 'woo-mpn' ),
				'desc_tip'      => true,
				'description'   => __( 'Manufacturer Product Number for this variation.', 'woo-mpn' ),
				'value'         => $variation_product->get_meta( self::META_KEY ),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Save MPN field for variation products.
	 *
	 * @param WC_Product_Variation $variation Variation product object.
	 * @param int                  $loop      Loop index.
	 */
	public function save_mpn_field_variation( $variation, int $loop ): void {
		if ( ! $variation instanceof WC_Product_Variation ) {
			return;
		}

		$mpn = isset( $_POST['variable_mpn'][ $loop ] )
			? sanitize_text_field( wp_unslash( $_POST['variable_mpn'][ $loop ] ) )
			: '';
		$variation->update_meta_data( self::META_KEY, $mpn );
		$variation->save();
	}

	/**
	 * Get MPN for a product (handles variable products).
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public static function get_product_mpn( $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$mpn = $product->get_meta( self::META_KEY );
		if ( '' !== $mpn ) {
			return $mpn;
		}

		// For variable products, get the first variation's MPN if no parent MPN.
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_children();
			foreach ( $variations as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$var_mpn = $variation->get_meta( self::META_KEY );
					if ( '' !== $var_mpn ) {
						return $var_mpn;
					}
				}
			}
		}

		return '';
	}
}
