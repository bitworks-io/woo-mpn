<?php
/**
 * Product list table for MPN management.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Woo_MPN_Product_List_Table
 */
class Woo_MPN_Product_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'product',
				'plural'   => 'products',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'    => '<input type="checkbox" />',
			'id'    => __( 'ID', 'woo-mpn' ),
			'title' => __( 'Product', 'woo-mpn' ),
			'sku'   => __( 'SKU', 'woo-mpn' ),
			'price' => __( 'Price', 'woo-mpn' ),
			'stock' => __( 'Stock', 'woo-mpn' ),
			'mpn'   => __( 'MPN', 'woo-mpn' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'id'    => array( 'id', false ),
			'title' => array( 'title', false ),
			'sku'   => array( 'sku', false ),
			'price' => array( 'price', false ),
			'stock' => array( 'stock', false ),
			'mpn'   => array( 'mpn', false ),
		);
	}

	/**
	 * Column default.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		if ( 'price' === $column_name ) {
			return $item->price ?? '—';
		}
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}

	/**
	 * Column checkbox.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		$has_mpn  = ! empty( $item->mpn );
		$disabled = $has_mpn ? ' disabled' : '';
		$title    = $has_mpn ? __( 'Product has MPN - AI lookup skipped', 'woo-mpn' ) : '';
		return sprintf(
			'<input type="checkbox" name="products[]" value="%d" %s title="%s" />',
			(int) $item->id,
			$disabled,
			esc_attr( $title )
		);
	}

	/**
	 * Column ID.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_id( $item ): string {
		$edit_url = get_edit_post_link( $item->id );
		return $edit_url
			? '<a href="' . esc_url( $edit_url ) . '">' . (int) $item->id . '</a>'
			: (string) (int) $item->id;
	}

	/**
	 * Column title.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_title( $item ): string {
		$edit_url = get_edit_post_link( $item->id );
		$title    = $item->title ?: __( '(no title)', 'woo-mpn' );
		return $edit_url
			? '<a href="' . esc_url( $edit_url ) . '" class="row-title"><strong>' . esc_html( $title ) . '</strong></a>'
			: '<strong>' . esc_html( $title ) . '</strong>';
	}

	/**
	 * Column MPN - editable input.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_mpn( $item ): string {
		$mpn   = $item->mpn ?? '';
		$name  = 'mpn[' . (int) $item->id . ']';
		$value = esc_attr( $mpn );
		return '<input type="text" name="' . esc_attr( $name ) . '" value="' . $value . '" class="woo-mpn-input" data-product-id="' . (int) $item->id . '" placeholder="' . esc_attr__( 'Enter MPN', 'woo-mpn' ) . '" />';
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions(): array {
		return array();
	}

	/**
	 * Extend search to include SKU (WordPress 's' only searches title/content).
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  Query object.
	 * @return string
	 */
	public function extend_search_include_sku( string $search, WP_Query $query ): string {
		if ( ! $query->get( 'woo_mpn_search' ) || 'product' !== $query->get( 'post_type' ) ) {
			return $search;
		}
		$term = $query->get( 's' );
		if ( empty( $term ) ) {
			return $search;
		}
		global $wpdb;
		$sku_ids = array();

		// Simple/variable products.
		$sku_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_sku',
						'value'   => $term,
						'compare' => 'LIKE',
					),
				),
			)
		);
		$sku_ids = array_merge( $sku_ids, $sku_query->posts );

		// Variations (SKU on variation, we need parent product ID).
		$var_query = new WP_Query(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_sku',
						'value'   => $term,
						'compare' => 'LIKE',
					),
				),
			)
		);
		foreach ( $var_query->posts as $var_id ) {
			$parent = wp_get_post_parent_id( $var_id );
			if ( $parent ) {
				$sku_ids[] = $parent;
			}
		}

		$sku_ids = array_unique( array_map( 'intval', $sku_ids ) );
		if ( empty( $sku_ids ) ) {
			return $search;
		}
		$ids_sql = implode( ',', $sku_ids );
		$search  = str_replace( 'AND (((', "AND ((({$wpdb->posts}.ID IN ({$ids_sql})) OR (", $search );
		return $search;
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'id';
		$order        = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		$orderby_safe = in_array( $orderby, array( 'id', 'title' ), true ) ? $orderby : 'id';
		$orderby_map  = array(
			'id'    => 'ID',
			'title' => 'title',
		);

		$per_page_options = array( 20, 50, 100, 150, 200 );
		$per_page_request = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 20;
		$per_page         = in_array( $per_page_request, $per_page_options, true ) ? $per_page_request : 20;

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => $orderby_map[ $orderby_safe ] ?? 'ID',
			'order'          => $order,
			'woo_mpn_search' => true, // Flag for SKU search filter.
		);

		if ( ! empty( $_GET['s'] ) ) {
			$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			add_filter( 'posts_search', array( $this, 'extend_search_include_sku' ), 999, 2 );
		}

		if ( ! empty( $_GET['product_cat'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => (int) $_GET['product_cat'],
				),
			);
		}

		$meta_query = array();
		if ( isset( $_GET['stock_status'] ) && '' !== $_GET['stock_status'] ) {
			$meta_query[] = array(
				'key'   => '_stock_status',
				'value' => sanitize_key( $_GET['stock_status'] ),
			);
		}
		if ( isset( $_GET['mpn_status'] ) && '' !== $_GET['mpn_status'] ) {
			$mpn_status   = sanitize_key( $_GET['mpn_status'] );
			$meta_query[] = array(
				'key'     => Woo_MPN_Product_Fields::META_KEY,
				'compare' => 'has' === $mpn_status ? 'EXISTS' : 'NOT EXISTS',
			);
		}
		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$query_args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $query_args );
		if ( ! empty( $_GET['s'] ) ) {
			remove_filter( 'posts_search', array( $this, 'extend_search_include_sku' ), 999 );
		}
		$items = array();

		foreach ( $query->posts as $post ) {
			$product_id = $post->ID;
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );

			$price = $product->get_price();
			$stock = $product->get_stock_quantity();
			if ( '' === $stock && $product->managing_stock() ) {
				$stock = '—';
			} elseif ( '' === $stock ) {
				$stock = $product->get_stock_status();
			}

			$items[] = (object) array(
				'id'           => $product->get_id(),
				'title'        => $product->get_name(),
				'sku'          => $product->get_sku(),
				'price'        => $price ? wc_price( $price ) : '—',
				'stock'        => $stock,
				'stock_status' => $product->get_stock_status(),
				'mpn'          => $mpn,
			);
		}

		$this->items = $items;
		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Get pagination info for custom table render.
	 *
	 * @return array{total_items: int, total_pages: int, per_page: int, current_page: int}
	 */
	public function get_pagination(): array {
		return array(
			'total_items'  => (int) $this->get_pagination_arg( 'total_items' ),
			'total_pages'  => (int) $this->get_pagination_arg( 'total_pages' ),
			'per_page'     => (int) $this->get_pagination_arg( 'per_page' ),
			'current_page' => (int) $this->get_pagenum(),
		);
	}
}
