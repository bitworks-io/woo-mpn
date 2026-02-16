<?php
/**
 * MPN column in the admin product table.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Admin_Columns
 */
class Woo_MPN_Admin_Columns {

	/**
	 * Column ID.
	 */
	public const COLUMN_ID = 'mpn';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_mpn_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_mpn_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'make_mpn_sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_mpn' ) );
	}

	/**
	 * Add MPN column to the product list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_mpn_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Insert MPN column after SKU.
			if ( 'sku' === $key ) {
				$new_columns[ self::COLUMN_ID ] = __( 'MPN', 'woo-mpn' );
			}
		}

		// If SKU column wasn't found, append at the end.
		if ( ! isset( $new_columns[ self::COLUMN_ID ] ) ) {
			$new_columns[ self::COLUMN_ID ] = __( 'MPN', 'woo-mpn' );
		}

		return $new_columns;
	}

	/**
	 * Render MPN column content.
	 *
	 * @param string $column  Column ID.
	 * @param int    $post_id Post ID.
	 */
	public function render_mpn_column( string $column, int $post_id ): void {
		if ( self::COLUMN_ID !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			echo '—';
			return;
		}

		$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
		echo $mpn ? esc_html( $mpn ) : '—';
	}

	/**
	 * Make MPN column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function make_mpn_sortable( array $columns ): array {
		$columns[ self::COLUMN_ID ] = self::COLUMN_ID;
		return $columns;
	}

	/**
	 * Handle sorting by MPN.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function sort_by_mpn( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( self::COLUMN_ID !== $orderby ) {
			return;
		}

		$query->set( 'meta_key', Woo_MPN_Product_Fields::META_KEY );
		$query->set( 'orderby', 'meta_value' );
	}
}
