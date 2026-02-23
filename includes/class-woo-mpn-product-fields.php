<?php
/**
 * Product MPN and EAN fields in the SKU section.
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
	 * Meta key for storing EAN (when using plugin field).
	 */
	public const META_KEY_EAN = '_ean';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_mpn_field' ) );
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_ean_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_mpn_field' ), 10, 2 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_ean_field' ), 10, 2 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_mpn_field_variation' ), 10, 3 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_ean_field_variation' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'save_mpn_field_variation' ), 10, 2 );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'save_ean_field_variation' ), 10, 2 );
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
	 * Add EAN field to the product SKU section (when using plugin field).
	 */
	public function add_ean_field(): void {
		if ( ! $this->use_plugin_ean_field() ) {
			return;
		}

		global $product_object;
		if ( ! $product_object ) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_KEY_EAN,
				'label'       => __( 'EAN / GTIN', 'woo-mpn' ),
				'placeholder' => __( 'Enter EAN', 'woo-mpn' ),
				'desc_tip'    => true,
				'description' => __( 'European Article Number or Global Trade Item Number (barcode).', 'woo-mpn' ),
				'value'       => $product_object->get_meta( self::META_KEY_EAN ),
			)
		);
	}

	/**
	 * Check if we should show/save the plugin's EAN field.
	 *
	 * @return bool
	 */
	private function use_plugin_ean_field(): bool {
		$source = Woo_MPN_Settings::get_ean_source();
		return '_ean' === $source || empty( $source );
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
	 * Save EAN field for simple products (when using plugin field).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_ean_field( int $post_id, $post ): void {
		if ( ! $this->use_plugin_ean_field() ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		$ean = isset( $_POST[ self::META_KEY_EAN ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_KEY_EAN ] ) ) : '';
		$product->update_meta_data( self::META_KEY_EAN, $ean );
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
	 * Add EAN field to variation products (when using plugin field).
	 *
	 * @param int     $loop           Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function add_ean_field_variation( int $loop, array $variation_data, $variation ): void {
		if ( ! $this->use_plugin_ean_field() ) {
			return;
		}

		$variation_product = wc_get_product( $variation->ID );
		if ( ! $variation_product ) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'            => 'variable_ean_' . $loop,
				'name'          => 'variable_ean[' . $loop . ']',
				'label'         => __( 'EAN', 'woo-mpn' ),
				'placeholder'   => __( 'Enter EAN', 'woo-mpn' ),
				'desc_tip'      => true,
				'description'   => __( 'EAN/GTIN for this variation.', 'woo-mpn' ),
				'value'         => $variation_product->get_meta( self::META_KEY_EAN ),
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
	 * Save EAN field for variation products (when using plugin field).
	 *
	 * @param WC_Product_Variation $variation Variation product object.
	 * @param int                  $loop      Loop index.
	 */
	public function save_ean_field_variation( $variation, int $loop ): void {
		if ( ! $this->use_plugin_ean_field() ) {
			return;
		}

		if ( ! $variation instanceof WC_Product_Variation ) {
			return;
		}

		$ean = isset( $_POST['variable_ean'][ $loop ] )
			? sanitize_text_field( wp_unslash( $_POST['variable_ean'][ $loop ] ) )
			: '';
		$variation->update_meta_data( self::META_KEY_EAN, $ean );
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

	/**
	 * Get EAN for a product based on the configured source.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public static function get_product_ean( $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$source = Woo_MPN_Settings::get_ean_source();

		// Plugin field.
		if ( '_ean' === $source || empty( $source ) ) {
			$ean = $product->get_meta( self::META_KEY_EAN );
			if ( '' !== $ean ) {
				return $ean;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $var_id ) {
					$var = wc_get_product( $var_id );
					if ( $var ) {
						$var_ean = $var->get_meta( self::META_KEY_EAN );
						if ( '' !== $var_ean ) {
							return $var_ean;
						}
					}
				}
			}
			return '';
		}

		// Preset meta keys: _gtin, _ts_gtin.
		if ( in_array( $source, array( '_gtin', '_ts_gtin' ), true ) ) {
			$ean = $product->get_meta( $source );
			if ( '' !== (string) $ean ) {
				return (string) $ean;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $var_id ) {
					$var   = wc_get_product( $var_id );
					$var_ean = $var ? $var->get_meta( $source ) : '';
					if ( '' !== (string) $var_ean ) {
						return (string) $var_ean;
					}
				}
			}
			return '';
		}

		// WooCommerce 9.2+ Global Unique ID.
		if ( '_global_unique_id' === $source ) {
			$guid = $product->get_meta( '_global_unique_id' );
			if ( is_array( $guid ) && ! empty( $guid['value'] ) ) {
				return (string) $guid['value'];
			}
			if ( is_string( $guid ) && '' !== $guid ) {
				return $guid;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $var_id ) {
					$var  = wc_get_product( $var_id );
					$guid = $var ? $var->get_meta( '_global_unique_id' ) : null;
					if ( is_array( $guid ) && ! empty( $guid['value'] ) ) {
						return (string) $guid['value'];
					}
					if ( is_string( $guid ) && '' !== $guid ) {
						return $guid;
					}
				}
			}
			return '';
		}

		// Custom meta or attribute.
		$custom = Woo_MPN_Settings::get_ean_custom_source();
		if ( '' !== $custom ) {
			// Product attribute (pa_*).
			if ( strpos( $custom, 'pa_' ) === 0 ) {
				$attr = $product->get_attribute( $custom );
				if ( '' !== $attr ) {
					return $attr;
				}
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $var_id ) {
						$var   = wc_get_product( $var_id );
						$attr  = $var ? $var->get_attribute( $custom ) : '';
						if ( '' !== $attr ) {
							return $attr;
						}
					}
				}
				return '';
			}

			// Meta key.
			$ean = $product->get_meta( $custom );
			if ( '' !== (string) $ean ) {
				return (string) $ean;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $var_id ) {
					$var   = wc_get_product( $var_id );
					$var_ean = $var ? $var->get_meta( $custom ) : '';
					if ( '' !== (string) $var_ean ) {
						return (string) $var_ean;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Get the meta key used for EAN (for filtering). Returns meta key when source is meta-based.
	 *
	 * @return string|null Meta key or null.
	 */
	public static function get_ean_filter_meta_key(): ?string {
		$source = Woo_MPN_Settings::get_ean_source();
		if ( in_array( $source, array( '_ean', '_gtin', '_ts_gtin', '_global_unique_id' ), true ) ) {
			return $source;
		}
		if ( 'custom' === $source ) {
			$custom = Woo_MPN_Settings::get_ean_custom_source();
			if ( '' !== $custom && strpos( $custom, 'pa_' ) !== 0 ) {
				return $custom;
			}
		}
		return null;
	}

	/**
	 * Get the meta key to save EAN to from the admin table, or null if not writable.
	 *
	 * @return string|null Meta key or null.
	 */
	public static function get_ean_save_meta_key(): ?string {
		$source = Woo_MPN_Settings::get_ean_source();
		if ( in_array( $source, array( '_ean', '_gtin', '_ts_gtin' ), true ) ) {
			return $source;
		}
		if ( 'custom' === $source ) {
			$custom = Woo_MPN_Settings::get_ean_custom_source();
			if ( '' !== $custom && strpos( $custom, 'pa_' ) !== 0 ) {
				return $custom;
			}
		}
		return null;
	}

	/**
	 * Save EAN for a product (when source is a writable meta key).
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $ean     EAN value.
	 * @return bool True if saved.
	 */
	public static function save_product_ean( $product, string $ean ): bool {
		$meta_key = self::get_ean_save_meta_key();
		if ( ! $meta_key || ! $product instanceof WC_Product ) {
			return false;
		}
		$product->update_meta_data( $meta_key, $ean );
		$product->save();
		return true;
	}
}
