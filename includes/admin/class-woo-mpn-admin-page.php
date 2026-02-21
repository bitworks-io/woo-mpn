<?php
/**
 * MPN Products admin page.
 *
 * @package WooCommerce_MPN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_MPN_Admin_Page
 */
class Woo_MPN_Admin_Page {

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'woo-mpn-products';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_woo_mpn_save_mpn', array( $this, 'ajax_save_mpn' ) );
		add_action( 'wp_ajax_woo_mpn_get_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_woo_mpn_puter_popup', array( $this, 'ajax_render_puter_popup' ) );
	}

	/**
	 * Add menu page under WooCommerce.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'MPN Products', 'woo-mpn' ),
			__( 'MPN Products', 'woo-mpn' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle form submissions and bulk actions.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_POST['woo_mpn_save'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woo_mpn_nonce'] ?? '' ) ), 'woo_mpn_save' ) ) {
			$mpns = isset( $_POST['mpn'] ) && is_array( $_POST['mpn'] ) ? wp_unslash( $_POST['mpn'] ) : array();
			foreach ( $mpns as $product_id => $mpn_value ) {
				$product_id = (int) $product_id;
				$product    = wc_get_product( $product_id );
				if ( $product ) {
					$product->update_meta_data( Woo_MPN_Product_Fields::META_KEY, sanitize_text_field( $mpn_value ) );
					$product->save();
				}
			}
			$redirect = add_query_arg( array(
				'page'    => self::PAGE_SLUG,
				'updated' => '1',
			), admin_url( 'admin.php' ) );
			foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'paged', 'orderby', 'order' ) as $param ) {
				if ( ! empty( $_POST[ $param ] ) ) {
					$redirect = add_query_arg( $param, sanitize_text_field( wp_unslash( $_POST[ $param ] ) ), $redirect );
				}
			}
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG === $hook ) {
			$this->enqueue_mpn_page_scripts();
		}
	}

	/**
	 * Enqueue scripts for the MPN Products page (no Puter).
	 */
	private function enqueue_mpn_page_scripts(): void {

		wp_enqueue_style( 'woo-mpn-admin', false );
		wp_add_inline_style( 'woo-mpn-admin', '
			.woo-mpn-filters { margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; }
			.woo-mpn-filters .filter-row { margin-bottom: 10px; }
			.woo-mpn-filters label { display: inline-block; min-width: 100px; }
			.woo-mpn-input { width: 100%; max-width: 200px; }
			.woo-mpn-bulk-actions { margin: 15px 0; }
			.woo-mpn-bulk-actions.woo-mpn-busy .button { opacity: 0.6; pointer-events: none; }
			.woo-mpn-status { margin: 15px 0; padding: 12px 16px; background: #f0f6fc; border-left: 4px solid #2271b1; display: none; }
			.woo-mpn-status.woo-mpn-active { display: block; }
			.woo-mpn-status .spinner { float: none; margin: 0 8px 0 0; vertical-align: middle; }
			.tablenav-pages { margin-top: 10px; }
			.woo-mpn-debug-wrap { margin: 15px 0; padding: 0; background: #1d2327; border: 2px solid #2271b1; border-radius: 6px; display: none; }
			.woo-mpn-debug-wrap.woo-mpn-debug-active { display: block !important; }
			.woo-mpn-debug-header { background: #2271b1; color: #fff; padding: 8px 12px; font-weight: 600; border-radius: 4px 4px 0 0; }
			.woo-mpn-debug { padding: 12px 16px; color: #f0f0f1; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
			.woo-mpn-debug .woo-mpn-debug-line { margin: 2px 0; }
			.woo-mpn-debug .woo-mpn-debug-ts { color: #72aee6; margin-right: 8px; }
			.woo-mpn-debug .woo-mpn-debug-err { color: #f86368; }
		' );

		$script_url = plugins_url( 'includes/admin/js/woo-mpn-admin.js', WOO_MPN_PLUGIN_FILE );
		wp_enqueue_script( 'woo-mpn-admin', $script_url, array( 'jquery' ), WOO_MPN_VERSION, true );
		wp_add_inline_script( 'woo-mpn-admin', $this->get_puter_mpn_script() );
		$is_debug = isset( $_GET['debug'] ) && '1' === $_GET['debug'];
		wp_localize_script( 'woo-mpn-admin', 'wooMpn', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'woo_mpn_admin' ),
			'debug'      => $is_debug,
			'popupUrl'   => add_query_arg( 'action', 'woo_mpn_puter_popup', admin_url( 'admin-ajax.php' ) ),
		) );
	}

	/**
	 * AJAX save single MPN.
	 */
	public function ajax_save_mpn(): void {
		check_ajax_referer( 'woo_mpn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-mpn' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$mpn        = isset( $_POST['mpn'] ) ? sanitize_text_field( wp_unslash( $_POST['mpn'] ) ) : '';

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'woo-mpn' ) ) );
		}

		$product->update_meta_data( Woo_MPN_Product_Fields::META_KEY, $mpn );
		$product->save();

		wp_send_json_success();
	}

	/**
	 * AJAX get product data for Puter.js MPN lookup.
	 */
	public function ajax_get_products(): void {
		check_ajax_referer( 'woo_mpn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-mpn' ) ) );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();
		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'woo-mpn' ) ) );
		}

		$products = Woo_MPN_Puter_Lookup::get_products_for_lookup( $product_ids );
		wp_send_json_success( array( 'products' => $products ) );
	}

	/**
	 * Render the Puter popup via admin-ajax (bypasses menu, works when opened in popup).
	 */
	public function ajax_render_puter_popup(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-mpn' ), '', array( 'response' => 403 ) );
		}
		$popup_script = $this->get_puter_popup_script();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'MPN AI Lookup', 'woo-mpn' ); ?></title>
	<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:20px;}</style>
</head>
<body>
	<div class="wrap">
		<h1><?php esc_html_e( 'MPN AI Lookup', 'woo-mpn' ); ?></h1>
		<div id="woo-mpn-popup-content" style="padding: 20px;">
			<p><?php esc_html_e( 'Loading Puter... If a consent dialog appears, click Continue to agree.', 'woo-mpn' ); ?></p>
			<p id="woo-mpn-popup-status"></p>
		</div>
	</div>
	<script src="https://js.puter.com/v2/"></script>
	<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
	<script>
	<?php echo $popup_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Get JavaScript for the Puter popup (runs MPN lookup in popup).
	 *
	 * @return string
	 */
	private function get_puter_popup_script(): string {
		$prompt_title = esc_js( __( 'Respond with ONLY the Manufacturer Part Number (MPN) for this product. Product: ', 'woo-mpn' ) );
		$prompt_sku   = esc_js( __( ' SKU: ', 'woo-mpn' ) );
		$prompt_url   = esc_js( __( ' Product page: ', 'woo-mpn' ) );
		$prompt_end   = esc_js( __( ' Reply with ONLY the MPN, nothing else. If unknown: UNKNOWN', 'woo-mpn' ) );

		return "
		(function() {
			function setStatus(msg) {
				var el = document.getElementById('woo-mpn-popup-status');
				if (el) el.textContent = msg;
			}
			function parseMpnResponse(text) {
				if (!text) return '';
				var t = String(text).trim().toUpperCase();
				if (t === 'UNKNOWN' || t === 'N/A' || t === 'NONE') return '';
				return String(text).trim();
			}
			function getRespText(resp) {
				if (typeof resp === 'string') return resp;
				if (!resp) return '';
				if (resp.message && resp.message.content) {
					var c = resp.message.content;
					if (Array.isArray(c) && c[0] && c[0].text) return c[0].text;
					if (typeof c === 'string') return c;
				}
				return resp.text || resp.content || '';
			}
			window.addEventListener('message', async function(e) {
				if (e.source !== window.opener || !e.data || e.data.type !== 'woo_mpn_start') return;
				var productIds = e.data.productIds || [];
				var ajaxUrl = e.data.ajaxUrl || '';
				var nonce = e.data.nonce || '';
				if (!productIds.length || !ajaxUrl || !nonce) {
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: 'Invalid data' }, '*');
					return;
				}
				setStatus('" . esc_js( __( 'Loading products...', 'woo-mpn' ) ) . "');
				try {
					var r = await jQuery.post(ajaxUrl, { action: 'woo_mpn_get_products', nonce: nonce, product_ids: productIds });
					if (!r.success || !r.data.products || !r.data.products.length) {
						if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: 'No products', found: 0, total: 0 }, '*');
						return;
					}
					var products = r.data.products;
					var total = products.length;
					var results = [];
					var found = 0;
					for (var i = 0; i < total; i++) {
						var p = products[i];
						setStatus('" . esc_js( __( 'Finding MPN', 'woo-mpn' ) ) . " ' + (i+1) + '/' + total);
						var prompt = '{$prompt_title}' + (p.title || '') + (p.sku ? '{$prompt_sku}' + p.sku : '') + (p.url ? '{$prompt_url}' + p.url : '') + '{$prompt_end}';
						try {
							var resp = await puter.ai.chat(prompt, { model: 'claude-haiku-4-5' });
							var mpn = parseMpnResponse(getRespText(resp));
							if (mpn) { results.push({ id: p.id, mpn: mpn }); found++; }
						} catch (err) { console.warn('MPN failed', p.id, err); }
					}
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', results: results, found: found, total: total }, '*');
				} catch (err) {
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: err.message || String(err), found: 0, total: 0 }, '*');
				}
			});
			if (window.opener) window.opener.postMessage({ type: 'woo_mpn_popup_ready' }, '*');
		})();
		";
	}

	/**
	 * Get JavaScript for main MPN page (opens popup, handles results).
	 *
	 * @return string
	 */
	private function get_puter_mpn_script(): string {
		return "
		(function(){ try { if (typeof wooMpn !== 'undefined' && wooMpn.debug) console.log('[woo-mpn] script loading'); } catch(e) {} })();
		jQuery(function($) {
			try {
			var debug = !!(wooMpn && wooMpn.debug);
			function dbg(m) { try { if (debug) console.log('[woo-mpn]', m); } catch(e) {} }
			function debugLog(msg, isErr) {
				dbg(msg);
				if (!debug) return;
				var \$d = $('#woo-mpn-debug');
				if (!\$d.length) return;
				var ts = new Date().toISOString().substr(11, 12);
				var cls = isErr ? 'woo-mpn-debug-line woo-mpn-debug-err' : 'woo-mpn-debug-line';
				\$d.append($('<div class=\"' + cls + '\"><span class=\"woo-mpn-debug-ts\">[' + ts + ']</span> ' + (msg || '').toString().replace(/</g, '&lt;') + '</div>'));
				\$d[0].scrollTop = \$d[0].scrollHeight;
			}
			function showStatus(msg, isActive) {
				var \$s = $('#woo-mpn-status');
				\$s.html('<span class=\"spinner is-active\"></span> ' + msg).toggleClass('woo-mpn-active', isActive);
				$('#woo-mpn-bulk-actions').toggleClass('woo-mpn-busy', isActive);
			}

			if (debug) debugLog('Script loaded');

			function openPuterPopup(productIds) {
				if (!productIds.length) {
					showStatus('" . esc_js( __( 'No products selected. Select products without MPN to find.', 'woo-mpn' ) ) . "', false);
					setTimeout(function() { showStatus('', false); }, 3000);
					return;
				}
				var popupUrl = wooMpn.popupUrl || '';
				if (!popupUrl) {
					showStatus('" . esc_js( __( 'Popup URL not configured.', 'woo-mpn' ) ) . "', false);
					return;
				}
				showStatus('" . esc_js( __( 'Opening Puter popup...', 'woo-mpn' ) ) . "', true);
				debugLog('Opening popup for ' + productIds.length + ' products');
				var w = window.open(popupUrl, 'woo_mpn_puter', 'width=600,height=500,scrollbars=yes');
				if (!w) {
					showStatus('" . esc_js( __( 'Popup blocked. Please allow popups for this site.', 'woo-mpn' ) ) . "', false);
					setTimeout(function() { showStatus('', false); }, 5000);
					return;
				}
				var handler = function(e) {
					if (e.data && e.data.type === 'woo_mpn_popup_ready') {
						window.removeEventListener('message', handler);
						debugLog('Popup ready, sending product IDs');
						w.postMessage({ type: 'woo_mpn_start', productIds: productIds, ajaxUrl: wooMpn.ajaxUrl, nonce: wooMpn.nonce }, '*');
					}
				};
				window.addEventListener('message', handler);
				var handler2 = function(e) {
					if (e.data && e.data.type === 'woo_mpn_results') {
						window.removeEventListener('message', handler2);
						clearInterval(closeCheck);
						try { w.close(); } catch (x) {}
						if (e.data.error) {
							showStatus('" . esc_js( __( 'Error:', 'woo-mpn' ) ) . " ' + e.data.error, false);
						} else {
							var results = e.data.results || [];
							for (var i = 0; i < results.length; i++) {
								$('input.woo-mpn-input[data-product-id=\"' + results[i].id + '\"]').val(results[i].mpn);
							}
							var found = e.data.found || 0;
							var total = e.data.total || 0;
							showStatus('" . esc_js( __( 'Done! Found', 'woo-mpn' ) ) . " ' + found + '/' + total + ' " . esc_js( __( 'MPNs. Click Save MPNs to store.', 'woo-mpn' ) ) . "', false);
						}
						setTimeout(function() { showStatus('', false); }, 5000);
					}
				};
				window.addEventListener('message', handler2);
				var closeCheck = setInterval(function() {
					if (w.closed) {
						clearInterval(closeCheck);
						window.removeEventListener('message', handler);
						window.removeEventListener('message', handler2);
						showStatus('" . esc_js( __( 'Popup closed.', 'woo-mpn' ) ) . "', false);
						setTimeout(function() { showStatus('', false); }, 3000);
					}
				}, 500);
			}

			$(document).on('click', '#woo-mpn-find-selected', function(e) {
				e.preventDefault();
				e.stopPropagation();
				dbg('Find selected clicked');
				var ids = [];
				$('input[name=\"products[]\"]:checked').each(function() { ids.push($(this).val()); });
				debugLog('Find selected, ids=' + JSON.stringify(ids));
				openPuterPopup(ids);
			});

			$(document).on('click', '#woo-mpn-find-all', function(e) {
				e.preventDefault();
				e.stopPropagation();
				dbg('Find all clicked');
				var ids = [];
				$('input[name=\"products[]\"]').not(':disabled').each(function() { ids.push($(this).val()); });
				debugLog('Find all, ids=' + JSON.stringify(ids));
				openPuterPopup(ids);
			});

			$(document).on('change', '#cb-select-all', function() {
				$('input[name=\"products[]\"]').not(':disabled').prop('checked', $(this).prop('checked'));
			});
			dbg('Ready, handlers bound');
			} catch (err) {
				try { if (debug) console.error('[woo-mpn] init error', err); } catch(e) {}
			}
		});
		";
	}

	/**
	 * Render products table - plain HTML (avoids WP_List_Table display issues).
	 *
	 * @param Woo_MPN_Product_List_Table $list_table List table with prepared items.
	 */
	private function render_products_table( Woo_MPN_Product_List_Table $list_table ): void {
		$items   = $list_table->items;
		$count   = is_array( $items ) ? count( $items ) : 0;
		$pag     = $list_table->get_pagination();
		$total   = $pag['total_items'];
		$pages   = $pag['total_pages'];
		$current = $pag['current_page'];
		$base_url = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
		foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'per_page', 'orderby', 'order', 'debug' ) as $param ) {
			if ( ! empty( $_GET[ $param ] ) ) {
				$base_url = add_query_arg( $param, sanitize_text_field( wp_unslash( $_GET[ $param ] ) ), $base_url );
			}
		}
		$per_page_options = array( 20, 50, 100, 150, 200 );
		$current_per_page = (int) ( $_GET['per_page'] ?? 20 );
		$current_per_page = in_array( $current_per_page, $per_page_options, true ) ? $current_per_page : 20;
		?>
		<?php if ( $count > 0 ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="cb-select-all" /></td>
						<th scope="col" class="column-id"><?php esc_html_e( 'ID', 'woo-mpn' ); ?></th>
						<th scope="col" class="column-title"><?php esc_html_e( 'Product', 'woo-mpn' ); ?></th>
						<th scope="col" class="column-sku"><?php esc_html_e( 'SKU', 'woo-mpn' ); ?></th>
						<th scope="col" class="column-price"><?php esc_html_e( 'Price', 'woo-mpn' ); ?></th>
						<th scope="col" class="column-stock"><?php esc_html_e( 'Stock', 'woo-mpn' ); ?></th>
						<th scope="col" class="column-mpn"><?php esc_html_e( 'MPN', 'woo-mpn' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$has_mpn  = ! empty( $item->mpn );
						$disabled = $has_mpn ? ' disabled' : '';
						$edit_url = get_edit_post_link( $item->id );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="products[]" value="<?php echo (int) $item->id; ?>" <?php echo $disabled; ?> title="<?php echo $has_mpn ? esc_attr__( 'Product has MPN - AI lookup skipped', 'woo-mpn' ) : ''; ?>" />
							</th>
							<td class="column-id"><?php echo $edit_url ? '<a href="' . esc_url( $edit_url ) . '">' . (int) $item->id . '</a>' : (int) $item->id; ?></td>
							<td class="column-title"><?php echo $edit_url ? '<a href="' . esc_url( $edit_url ) . '" class="row-title"><strong>' . esc_html( $item->title ?? '' ) . '</strong></a>' : '<strong>' . esc_html( $item->title ?? '' ) . '</strong>'; ?></td>
							<td class="column-sku"><?php echo esc_html( $item->sku ?? '' ); ?></td>
							<td class="column-price"><?php echo wp_kses_post( $item->price ?? '—' ); ?></td>
							<td class="column-stock"><?php echo esc_html( (string) ( $item->stock ?? '—' ) ); ?></td>
							<td class="column-mpn">
								<input type="text" name="mpn[<?php echo (int) $item->id; ?>]" value="<?php echo esc_attr( $item->mpn ?? '' ); ?>" class="woo-mpn-input" data-product-id="<?php echo (int) $item->id; ?>" data-has-mpn="<?php echo $has_mpn ? '1' : '0'; ?>" placeholder="<?php esc_attr_e( 'Enter MPN', 'woo-mpn' ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'woo-mpn' ), number_format_i18n( $total ) ) ); ?></span>
					<span class="pagination-links">
						<?php
						if ( $pages > 1 ) {
							echo '<a class="first-page button" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">&laquo;</a> ';
							echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $base_url ) ) . '">&lsaquo;</a> ';
							echo '<span class="paging-input"><span class="tablenav-paging-text">' . (int) $current . ' ' . esc_html_x( 'of', 'page of pages', 'woo-mpn' ) . ' <span class="total-pages">' . (int) $pages . '</span></span></span> ';
							echo '<a class="next-page button" href="' . esc_url( add_query_arg( 'paged', min( $pages, $current + 1 ), $base_url ) ) . '">&rsaquo;</a> ';
							echo '<a class="last-page button" href="' . esc_url( add_query_arg( 'paged', $pages, $base_url ) ) . '">&raquo;</a>';
						}
						?>
					</span>
					<span class="pagination-links" style="margin-left: 15px;">
						<label for="per-page-select" class="screen-reader-text"><?php esc_html_e( 'Number of items per page:', 'woo-mpn' ); ?></label>
						<select id="per-page-select" onchange="window.location.href=this.value">
							<?php foreach ( $per_page_options as $opt ) : ?>
								<option value="<?php echo esc_url( add_query_arg( array( 'per_page' => $opt, 'paged' => 1 ), $base_url ) ); ?>" <?php selected( $current_per_page, $opt ); ?>>
									<?php echo esc_html( sprintf( _n( '%s item', '%s items', $opt, 'woo-mpn' ), number_format_i18n( $opt ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</span>
				</div>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No products found.', 'woo-mpn' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		$list_table = new Woo_MPN_Product_List_Table();
		$list_table->prepare_items();

		$product_categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $product_categories ) || ! is_array( $product_categories ) ) {
			$product_categories = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MPN Products', 'woo-mpn' ); ?></h1>

			<?php
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'MPNs saved successfully.', 'woo-mpn' ) . '</p></div>';
		}
		?>

			<form method="get" id="woo-mpn-filter-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php if ( isset( $_GET['debug'] ) && '1' === $_GET['debug'] ) : ?>
					<input type="hidden" name="debug" value="1" />
				<?php endif; ?>
				<?php if ( ! empty( $_GET['per_page'] ) ) : ?>
					<input type="hidden" name="per_page" value="<?php echo esc_attr( (int) $_GET['per_page'] ); ?>" />
				<?php endif; ?>
				<div class="woo-mpn-filters">
					<h2><?php esc_html_e( 'Filters', 'woo-mpn' ); ?></h2>
					<div class="filter-row">
						<label for="s"><?php esc_html_e( 'Search', 'woo-mpn' ); ?></label>
						<input type="search" id="s" name="s" value="<?php echo esc_attr( wp_unslash( $_GET['s'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Product name or SKU', 'woo-mpn' ); ?>" />
					</div>
					<div class="filter-row">
						<label for="product_cat"><?php esc_html_e( 'Category', 'woo-mpn' ); ?></label>
						<select id="product_cat" name="product_cat">
							<option value=""><?php esc_html_e( 'All categories', 'woo-mpn' ); ?></option>
							<?php foreach ( $product_categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( isset( $_GET['product_cat'] ) && (int) $_GET['product_cat'] === $cat->term_id ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="filter-row">
						<label for="stock_status"><?php esc_html_e( 'Stock', 'woo-mpn' ); ?></label>
						<select id="stock_status" name="stock_status">
							<option value=""><?php esc_html_e( 'All', 'woo-mpn' ); ?></option>
							<option value="instock" <?php selected( isset( $_GET['stock_status'] ) && 'instock' === $_GET['stock_status'] ); ?>><?php esc_html_e( 'In stock', 'woo-mpn' ); ?></option>
							<option value="outofstock" <?php selected( isset( $_GET['stock_status'] ) && 'outofstock' === $_GET['stock_status'] ); ?>><?php esc_html_e( 'Out of stock', 'woo-mpn' ); ?></option>
						</select>
					</div>
					<div class="filter-row">
						<label for="mpn_status"><?php esc_html_e( 'MPN Status', 'woo-mpn' ); ?></label>
						<select id="mpn_status" name="mpn_status">
							<option value=""><?php esc_html_e( 'All', 'woo-mpn' ); ?></option>
							<option value="has" <?php selected( isset( $_GET['mpn_status'] ) && 'has' === $_GET['mpn_status'] ); ?>><?php esc_html_e( 'Has MPN', 'woo-mpn' ); ?></option>
							<option value="empty" <?php selected( isset( $_GET['mpn_status'] ) && 'empty' === $_GET['mpn_status'] ); ?>><?php esc_html_e( 'No MPN', 'woo-mpn' ); ?></option>
						</select>
					</div>
					<div class="filter-row">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'woo-mpn' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'woo-mpn' ); ?></a>
					</div>
				</div>
			</form>

			<form method="post" id="woo-mpn-products-form">
				<?php wp_nonce_field( 'woo_mpn_save', 'woo_mpn_nonce' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php
				foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'per_page', 'paged', 'orderby', 'order', 'debug' ) as $param ) {
					if ( ! empty( $_GET[ $param ] ) ) {
						echo '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( wp_unslash( $_GET[ $param ] ) ) . '" />';
					}
				}
				?>
				<div class="woo-mpn-bulk-actions" id="woo-mpn-bulk-actions">
					<button type="button" id="woo-mpn-find-selected" class="button" onclick="var s=document.getElementById('woo-mpn-status');if(s){s.className='woo-mpn-status woo-mpn-active';s.innerHTML='<span class=\'spinner is-active\'></span> <?php echo esc_js( __( 'Starting...', 'woo-mpn' ) ); ?>';}"><?php esc_html_e( 'Find MPN via AI (selected)', 'woo-mpn' ); ?></button>
					<button type="button" id="woo-mpn-find-all" class="button" onclick="var s=document.getElementById('woo-mpn-status');if(s){s.className='woo-mpn-status woo-mpn-active';s.innerHTML='<span class=\'spinner is-active\'></span> <?php echo esc_js( __( 'Starting...', 'woo-mpn' ) ); ?>';}"><?php echo esc_html__( 'Find MPN via AI (all without MPN)', 'woo-mpn' ); ?></button>
					<button type="submit" name="woo_mpn_save" class="button button-primary"><?php esc_html_e( 'Save MPNs', 'woo-mpn' ); ?></button>
				</div>
				<div class="woo-mpn-status" id="woo-mpn-status" role="status" aria-live="polite"></div>
				<?php if ( isset( $_GET['debug'] ) && '1' === $_GET['debug'] ) : ?>
				<div class="woo-mpn-debug-wrap woo-mpn-debug-active" id="woo-mpn-debug-wrap" aria-live="polite">
					<div class="woo-mpn-debug-header" id="woo-mpn-debug-header"><?php esc_html_e( 'MPN Debug', 'woo-mpn' ); ?></div>
					<div class="woo-mpn-debug" id="woo-mpn-debug"><div class="woo-mpn-debug-line"><span class="woo-mpn-debug-ts">[--]</span> <?php esc_html_e( 'Debug mode. Click Find MPN via AI to see flow.', 'woo-mpn' ); ?></div></div>
				</div>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Find MPN via AI opens a popup where Puter loads. Click Continue in the consent dialog if prompted. Products with existing MPN are excluded.', 'woo-mpn' ); ?></p>
				<?php $this->render_products_table( $list_table ); ?>
			</form>
		</div>
		<?php
	}
}
