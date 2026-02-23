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
			$ean_meta_key = Woo_MPN_Product_Fields::get_ean_save_meta_key();
			if ( $ean_meta_key ) {
				$eans = isset( $_POST['ean'] ) && is_array( $_POST['ean'] ) ? wp_unslash( $_POST['ean'] ) : array();
				foreach ( $eans as $product_id => $ean_value ) {
					$product_id = (int) $product_id;
					$product    = wc_get_product( $product_id );
					if ( $product ) {
						Woo_MPN_Product_Fields::save_product_ean( $product, sanitize_text_field( $ean_value ) );
					}
				}
			}
			$redirect = add_query_arg( array(
				'page'    => self::PAGE_SLUG,
				'updated' => '1',
			), admin_url( 'admin.php' ) );
			foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'ean_status', 'per_page', 'paged', 'orderby', 'order', 'debug' ) as $param ) {
				if ( ! empty( $_POST[ $param ] ) ) {
					$redirect = add_query_arg( $param, sanitize_text_field( wp_unslash( $_POST[ $param ] ) ), $redirect );
				}
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		// Apply Google Product Feed condition to selected products.
		if ( isset( $_POST['woo_mpn_apply_condition'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woo_mpn_nonce'] ?? '' ) ), 'woo_mpn_save' ) ) {
			$product_ids = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? array_map( 'intval', $_POST['products'] ) : array();
			$condition   = isset( $_POST['gpf_condition'] ) ? sanitize_text_field( wp_unslash( $_POST['gpf_condition'] ) ) : '';
			$allowed     = array( 'new', 'refurbished', 'used' );
			if ( ! empty( $product_ids ) && in_array( $condition, $allowed, true ) ) {
				$updated = 0;
				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}
					$gpf_data = $product->get_meta( '_woocommerce_gpf_data' );
					if ( ! is_array( $gpf_data ) ) {
						$gpf_data = is_string( $gpf_data ) ? maybe_unserialize( $gpf_data ) : array();
						$gpf_data = is_array( $gpf_data ) ? $gpf_data : array();
					}
					$current = $gpf_data['condition'] ?? '';
					if ( $current !== $condition ) {
						$gpf_data['condition'] = $condition;
						$product->update_meta_data( '_woocommerce_gpf_data', $gpf_data );
						$product->save();
						$updated++;
					}
				}
				$redirect = add_query_arg( array(
					'page'     => self::PAGE_SLUG,
					'updated'  => '1',
					'gpf_done' => (string) $updated,
				), admin_url( 'admin.php' ) );
			} else {
				$redirect = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
			}
			foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'ean_status', 'per_page', 'paged', 'orderby', 'order', 'debug' ) as $param ) {
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
			.woo-mpn-input, .woo-mpn-ean-input { width: 100%; max-width: 200px; }
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
			.woo-mpn-lookup-log { margin: 15px 0; padding: 12px 16px; background: #1d2327; border: 2px solid #2271b1; border-radius: 6px; color: #f0f0f1; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
			.woo-mpn-lookup-log h4 { color: #72aee6; margin: 12px 0 4px 0; }
			.woo-mpn-lookup-log h4:first-child { margin-top: 0; }
			.woo-mpn-lookup-log .log-block { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #3c434a; }
			.woo-mpn-lookup-log .log-block:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
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
	<style>
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:20px;}
		#woo-mpn-popup-log{margin-top:20px;padding:16px;background:#1d2327;color:#f0f0f1;font-family:monospace;font-size:12px;max-height:500px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;border:2px solid #2271b1;border-radius:6px;min-height:100px;}
		#woo-mpn-popup-close{display:none;margin-top:16px;padding:10px 20px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;}
		#woo-mpn-popup-log h4{color:#72aee6;margin:12px 0 4px 0;}
		#woo-mpn-popup-log .log-block{margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #3c434a;}
		#woo-mpn-popup-log .log-block:last-child{border-bottom:none;}
	</style>
</head>
<body>
	<div class="wrap">
		<h1><?php esc_html_e( 'MPN AI Lookup', 'woo-mpn' ); ?></h1>
		<div id="woo-mpn-popup-content" style="padding: 20px;">
			<p><?php esc_html_e( 'Loading Puter... If a consent dialog appears, click Continue to agree.', 'woo-mpn' ); ?></p>
			<p id="woo-mpn-popup-status"></p>
		</div>
		<div id="woo-mpn-popup-log" style="display:none;">
			<p style="color:#72aee6;margin:0;"><?php esc_html_e( 'Lookup log will appear below as products are processed...', 'woo-mpn' ); ?></p>
		</div>
		<button type="button" id="woo-mpn-popup-close"><?php esc_html_e( 'Close window', 'woo-mpn' ); ?></button>
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
		$prompt_intro = esc_js( __( 'Reply with exactly two lines. First line: MPN=value. Second line: EAN=value. EAN is the 13-digit barcode. Use UNKNOWN if not found. Product: ', 'woo-mpn' ) );
		$prompt_url  = esc_js( __( ' URL: ', 'woo-mpn' ) );

		return "
		(function() {
			var popupDebug = false;
			function setStatus(msg) {
				var el = document.getElementById('woo-mpn-popup-status');
				if (el) el.textContent = msg;
			}
			function appendLog(productId, prompt, response, parsed) {
				if (!popupDebug) return;
				var logEl = document.getElementById('woo-mpn-popup-log');
				if (!logEl) return;
				if (logEl.querySelectorAll('.log-block').length === 0) {
					logEl.innerHTML = '';
					var h = document.createElement('h3');
					h.textContent = '" . esc_js( __( 'Lookup log (search + result)', 'woo-mpn' ) ) . "';
					h.style.cssText = 'margin:0 0 12px 0; color:#2271b1;';
					logEl.appendChild(h);
				}
				var block = document.createElement('div');
				block.className = 'log-block';
				var h4a = document.createElement('h4');
				h4a.textContent = 'Product ' + productId + ' - " . esc_js( __( 'Search', 'woo-mpn' ) ) . ":';
				block.appendChild(h4a);
				var pre1 = document.createElement('pre');
				pre1.textContent = prompt || '';
				block.appendChild(pre1);
				var h4b = document.createElement('h4');
				h4b.textContent = '" . esc_js( __( 'Result', 'woo-mpn' ) ) . ":';
				block.appendChild(h4b);
				var pre2 = document.createElement('pre');
				pre2.textContent = response || '';
				block.appendChild(pre2);
				if (parsed && (parsed.mpn || parsed.ean)) {
					var h4c = document.createElement('h4');
					h4c.textContent = '" . esc_js( __( 'Parsed', 'woo-mpn' ) ) . ":';
					block.appendChild(h4c);
					var p = document.createElement('p');
					p.textContent = 'MPN: ' + (parsed.mpn || '') + ', EAN: ' + (parsed.ean || '');
					p.style.margin = '4px 0';
					block.appendChild(p);
				}
				logEl.appendChild(block);
				logEl.scrollTop = logEl.scrollHeight;
			}
			function parseValue(val) {
				if (!val) return '';
				var t = String(val).trim().toUpperCase();
				if (t === 'UNKNOWN' || t === 'N/A' || t === 'NONE' || t === '') return '';
				return String(val).trim();
			}
			function isValidEan(val) {
				if (!val) return false;
				var s = String(val).trim().replace(/\s/g, '');
				return /^[0-9]{8}$|^[0-9]{12}$|^[0-9]{13}$|^[0-9]{14}$/.test(s);
			}
			function parseMpnEanResponse(text) {
				var mpn = '', ean = '';
				if (!text) return { mpn: '', ean: '' };
				var raw = String(text).trim();
				var lines = raw.split(/[\\r\\n]+/);
				for (var i = 0; i < lines.length; i++) {
					var line = lines[i].trim();
					var m = line.match(/^MPN\\s*[=:\\-]\\s*(.+)$/i) || line.match(/MPN\\s*[=:\\-]\\s*([^,\\n]+)/i);
					if (m) mpn = parseValue(m[1]);
					var e = line.match(/^(?:EAN|GTIN|EAN\\/GTIN)\\s*[=:\\-]\\s*(.+)$/i) || line.match(/(?:EAN|GTIN)[^0-9]*([0-9]{8,14})/i);
					if (e) ean = parseValue(e[1]);
				}
				if (!ean && /(?:EAN|GTIN)[^0-9]*([0-9]{8}|[0-9]{13})/i.test(raw)) {
					var match = raw.match(/(?:EAN|GTIN)[^0-9]*([0-9]{8}|[0-9]{13})/i);
					if (match) ean = match[1];
				}
				if (!ean && lines.length >= 2) {
					var second = lines[1].trim().replace(/^[^0-9]*/, '');
					if (/^[0-9]{8}$|^[0-9]{13}$/.test(second)) ean = second;
					else ean = parseValue(lines[1].trim());
				}
				if (!mpn && !ean && lines.length >= 1) {
					mpn = parseValue(lines[0].trim());
				}
				if (!ean && /^[0-9]{8}$|^[0-9]{13}$/.test(raw)) {
					ean = raw;
				}
				if (!ean) {
					var digitMatch = raw.match(/\\b([0-9]{8})\\b|\\b([0-9]{12})\\b|\\b([0-9]{13})\\b|\\b([0-9]{14})\\b/);
					if (digitMatch) ean = (digitMatch[1] || digitMatch[2] || digitMatch[3] || digitMatch[4] || '');
				}
				if (ean && !isValidEan(ean)) ean = '';
				return { mpn: mpn, ean: ean };
			}
			function stripSkuFromTitle(t) {
				if (!t) return '';
				return String(t).replace(/\s*SKU:\s*\S+/gi, '').trim();
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
				var products = e.data.products || [];
				var ajaxUrl = e.data.ajaxUrl || '';
				var nonce = e.data.nonce || '';
				popupDebug = !!(e.data.debug);
				var logEl = document.getElementById('woo-mpn-popup-log');
				if (logEl) logEl.style.display = popupDebug ? 'block' : 'none';
				var currentValues = e.data.currentValues || {};
				if (!productIds.length || !ajaxUrl || !nonce) {
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: 'Invalid data' }, '*');
					return;
				}
				if (!products.length) {
					try {
						var r = await jQuery.post(ajaxUrl, { action: 'woo_mpn_get_products', nonce: nonce, product_ids: productIds });
						if (r.success && r.data.products && r.data.products.length) products = r.data.products;
					} catch (err) {}
				}
				if (!products.length) {
					appendLog(0, 'No products', 'Server returned no products (all may have both MPN and EAN already).');
					setStatus('" . esc_js( __( 'No products to look up.', 'woo-mpn' ) ) . "');
					var closeBtn = document.getElementById('woo-mpn-popup-close');
					if (closeBtn) { closeBtn.style.display = popupDebug ? 'inline-block' : 'none'; closeBtn.onclick = function() { window.close(); }; }
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: 'No products', found: 0, total: 0 }, '*');
					if (!popupDebug) setTimeout(function() { window.close(); }, 300);
					return;
				}
				setStatus('" . esc_js( __( 'Finding MPN and EAN', 'woo-mpn' ) ) . " (0/' + total + ')');
				try {
					var total = products.length;
					var results = [];
					var found = 0;
					for (var i = 0; i < total; i++) {
						var p = products[i];
						var cv = currentValues[p.id] || {};
						var hasMpn = !!(cv.mpn && cv.mpn.length > 0);
						var hasEan = !!(cv.ean && cv.ean.length > 0);
						if (hasMpn && hasEan) {
							results.push({ id: p.id, mpn: cv.mpn, ean: cv.ean, skipped: true });
							found++;
							continue;
						}
						setStatus('" . esc_js( __( 'Finding MPN and EAN', 'woo-mpn' ) ) . " ' + (i+1) + '/' + total);
						var prompt;
						if (hasMpn) {
							prompt = 'Search the web for the EAN/GTIN barcode. MPN is ' + cv.mpn + '. Product: ' + stripSkuFromTitle(p.title) + '. Find ONLY the EAN or GTIN (8, 12, or 13 digit barcode). Reply with exactly: EAN=value (digits only) or EAN=UNKNOWN';
						} else {
							prompt = '{$prompt_intro}' + stripSkuFromTitle(p.title) + (p.url ? '{$prompt_url}' + p.url : '');
						}
						if (window.opener) window.opener.postMessage({ type: 'woo_mpn_debug', kind: 'input', productId: p.id, data: prompt }, '*');
						try {
							var resp = await puter.ai.chat(prompt, { model: 'claude-haiku-4-5' });
							var respText = getRespText(resp);
							var parsed = parseMpnEanResponse(respText);
							appendLog(p.id, prompt, respText, parsed);
							if (debug && window.opener) window.opener.postMessage({ type: 'woo_mpn_debug', kind: 'output', productId: p.id, data: { text: respText, parsed: parsed } }, '*');
							var mpn = hasMpn ? cv.mpn : (parsed.mpn || '');
							var ean = hasEan ? cv.ean : (parsed.ean || '');
							results.push({ id: p.id, mpn: mpn, ean: ean, prompt: prompt, response: respText });
							if (mpn || ean) found++;
						} catch (err) {
							appendLog(p.id, prompt, 'Error: ' + (err.message || String(err)));
							results.push({ id: p.id, mpn: '', ean: '', prompt: prompt, response: 'Error: ' + (err.message || String(err)) });
						}
					}
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', results: results, found: found, total: total, debug: popupDebug }, '*');
					setStatus(popupDebug ? '" . esc_js( __( 'Done. See log above. Click Close window when finished.', 'woo-mpn' ) ) . "' : '" . esc_js( __( 'Done.', 'woo-mpn' ) ) . "');
					var closeBtn = document.getElementById('woo-mpn-popup-close');
					if (closeBtn) { closeBtn.style.display = popupDebug ? 'inline-block' : 'none'; closeBtn.onclick = function() { window.close(); }; }
					if (!popupDebug) setTimeout(function() { window.close(); }, 500);
				} catch (err) {
					appendLog(0, 'Error', err.message || String(err));
					setStatus('" . esc_js( __( 'Error:', 'woo-mpn' ) ) . " ' + (err.message || err));
					var closeBtn = document.getElementById('woo-mpn-popup-close');
					if (closeBtn) { closeBtn.style.display = popupDebug ? 'inline-block' : 'none'; closeBtn.onclick = function() { window.close(); }; }
					if (window.opener) window.opener.postMessage({ type: 'woo_mpn_results', error: err.message || String(err), found: 0, total: 0 }, '*');
					if (!popupDebug) setTimeout(function() { window.close(); }, 500);
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

			var promptIntro = '" . esc_js( __( 'Reply with exactly two lines. First line: MPN=value. Second line: EAN=value. EAN is the 13-digit barcode. Use UNKNOWN if not found. Product: ', 'woo-mpn' ) ) . "';
			var promptUrl = '" . esc_js( __( ' URL: ', 'woo-mpn' ) ) . "';
			function stripSkuFromTitle(t) {
				if (!t) return '';
				return String(t).replace(/\\s*SKU:\\s*\\S+/gi, '').trim();
			}
			function openPuterPopup(productIds) {
				if (!productIds.length) {
					showStatus('" . esc_js( __( 'No products selected. Select products missing MPN or EAN to find.', 'woo-mpn' ) ) . "', false);
					setTimeout(function() { showStatus('', false); }, 3000);
					return;
				}
				var popupUrl = wooMpn.popupUrl || '';
				if (!popupUrl) {
					showStatus('" . esc_js( __( 'Popup URL not configured.', 'woo-mpn' ) ) . "', false);
					return;
				}
				var currentValues = {};
				productIds.forEach(function(id) {
					var mpn = ($('input.woo-mpn-input[data-product-id=\"' + id + '\"]').val() || '').trim();
					var eanEl = $('input.woo-mpn-ean-input[data-product-id=\"' + id + '\"]');
					if (!eanEl.length) eanEl = $('input[name=\"ean[' + id + ']\"]');
					var ean = (eanEl.val() || '').trim();
					if (!ean && !eanEl.length) {
						var \$td = $('tr').has('input.woo-mpn-input[data-product-id=\"' + id + '\"]').find('td.column-ean');
						if (\$td.length && !\$td.find('input').length) ean = (\$td.text() || '').trim();
					}
					currentValues[id] = { mpn: mpn, ean: ean };
				});
				showStatus('" . esc_js( __( 'Loading products...', 'woo-mpn' ) ) . "', true);
				debugLog('Fetching products for ' + productIds.length + ' IDs');
				jQuery.post(wooMpn.ajaxUrl, { action: 'woo_mpn_get_products', nonce: wooMpn.nonce, product_ids: productIds }).done(function(r) {
					if (!r.success || !r.data.products || !r.data.products.length) {
						console.log('[woo-mpn] No products returned from server');
						showStatus('" . esc_js( __( 'No products to look up.', 'woo-mpn' ) ) . "', false);
						setTimeout(function() { showStatus('', false); }, 3000);
						return;
					}
					var products = r.data.products;
					console.log('[woo-mpn] Got ' + products.length + ' products, building prompts');
					var firstPrompt = '';
					products.forEach(function(p) {
						var cv = currentValues[p.id] || {};
						var hasMpn = !!(cv.mpn && cv.mpn.length > 0);
						var hasEan = !!(cv.ean && cv.ean.length > 0);
						var prompt;
						if (hasMpn && hasEan) return;
						if (hasMpn) {
							prompt = 'Search the web for the EAN/GTIN barcode. MPN is ' + cv.mpn + '. Product: ' + stripSkuFromTitle(p.title) + '. Reply EAN=value or UNKNOWN';
						} else {
							prompt = promptIntro + stripSkuFromTitle(p.title) + (p.url ? promptUrl + p.url : '');
						}
						if (!firstPrompt) firstPrompt = prompt;
						console.log('[woo-mpn] Puter search (product ' + p.id + '):', prompt);
						debugLog('[woo-mpn] Search product ' + p.id + ': ' + prompt.substring(0, 150) + '...');
					});
					showStatus('" . esc_js( __( 'Opening Puter popup...', 'woo-mpn' ) ) . " ' + (firstPrompt ? '(" . esc_js( __( 'Search:', 'woo-mpn' ) ) . " ' + firstPrompt.substring(0, 80) + '...)' : ''), true);
					var w = window.open(popupUrl, 'woo_mpn_puter', 'width=600,height=500,scrollbars=yes');
					if (!w) {
						showStatus('" . esc_js( __( 'Popup blocked. Please allow popups for this site.', 'woo-mpn' ) ) . "', false);
						setTimeout(function() { showStatus('', false); }, 5000);
						return;
					}
					var handler = function(e) {
						if (e.data && e.data.type === 'woo_mpn_popup_ready') {
							window.removeEventListener('message', handler);
							debugLog('Popup ready, sending product data');
							w.postMessage({ type: 'woo_mpn_start', productIds: productIds, products: products, ajaxUrl: wooMpn.ajaxUrl, nonce: wooMpn.nonce, debug: wooMpn.debug, currentValues: currentValues }, '*');
						}
					};
					window.addEventListener('message', handler);
					var debugHandler = function(e) {
						if (!e.data || e.data.type !== 'woo_mpn_debug') return;
						var msg = '[woo-mpn] Puter ' + e.data.kind + ' (product ' + e.data.productId + '):';
						console.log(msg, e.data.data);
						if (e.data.kind === 'output' && debug) {
							var d = e.data.data;
							debugLog(msg + ' text: ' + (d.text || '').substring(0, 300) + ' parsed: ' + JSON.stringify(d.parsed || {}));
						}
					};
					window.addEventListener('message', debugHandler);
					var handler2 = function(e) {
						if (e.data && e.data.type === 'woo_mpn_results') {
							window.removeEventListener('message', debugHandler);
							window.removeEventListener('message', handler2);
							clearInterval(closeCheck);
							showStatus(debug ? '" . esc_js( __( 'Done. Close the popup window when you have finished reviewing the log.', 'woo-mpn' ) ) . "' : '" . esc_js( __( 'Done.', 'woo-mpn' ) ) . "', false);
							if (e.data.error) {
								showStatus('" . esc_js( __( 'Error:', 'woo-mpn' ) ) . " ' + e.data.error, false);
							} else {
							var results = e.data.results || [];
							if (debug) {
								var \$log = $('#woo-mpn-lookup-log');
								if (!\$log.length) {
									\$log = $('<div id=\"woo-mpn-lookup-log\" class=\"woo-mpn-lookup-log\"></div>');
									$('#woo-mpn-status').after(\$log);
								}
								\$log.empty().append($('<h4>').text('" . esc_js( __( 'Lookup log', 'woo-mpn' ) ) . "'));
								for (var i = 0; i < results.length; i++) {
									var r = results[i];
									var pid = r.id;
									if (r.skipped) {
										var block = $('<div class=\"log-block\"></div>');
										block.append($('<strong>').text('Product ' + pid + ' - " . esc_js( __( 'Skipped', 'woo-mpn' ) ) . ":'));
										block.append($('<p>').css({margin:'4px 0'}).text('" . esc_js( __( 'Already has MPN and EAN.', 'woo-mpn' ) ) . "'));
										\$log.append(block);
									} else if (r.prompt !== undefined) {
										console.log('[woo-mpn] Product ' + pid + ' SEARCH:', r.prompt);
										console.log('[woo-mpn] Product ' + pid + ' RESULT:', r.response || '');
										var block = $('<div class=\"log-block\"></div>');
										block.append($('<strong>').text('Product ' + pid + ' - " . esc_js( __( 'Search', 'woo-mpn' ) ) . ":'));
										block.append($('<pre>').css({margin:'4px 0',whiteSpace:'pre-wrap'}).text(r.prompt || ''));
										block.append($('<strong>').text('" . esc_js( __( 'Result', 'woo-mpn' ) ) . ":'));
										block.append($('<pre>').css({margin:'4px 0',whiteSpace:'pre-wrap'}).text(r.response || ''));
										if (r.mpn || r.ean) {
											block.append($('<strong>').text('" . esc_js( __( 'Parsed', 'woo-mpn' ) ) . ":'));
											block.append($('<p>').css({margin:'4px 0'}).text('MPN: ' + (r.mpn || '') + ', EAN: ' + (r.ean || '')));
										}
										\$log.append(block);
									}
								}
								\$log.show();
							}
							for (var i = 0; i < results.length; i++) {
								var r = results[i];
								var pid = r.id;
								var \$mpnInput = $('input.woo-mpn-input[data-product-id=\"' + pid + '\"]');
								var \$eanInput = $('input.woo-mpn-ean-input[data-product-id=\"' + pid + '\"]');
								if (!\$eanInput.length) \$eanInput = $('input[name=\"ean[' + pid + ']\"]');
								var curMpn = (\$mpnInput.val() || '').trim();
								var curEan = (\$eanInput.val() || '').trim();
								if (!\$eanInput.length) {
									var \$td = $('tr').has('input.woo-mpn-input[data-product-id=\"' + pid + '\"]').find('td.column-ean');
									if (\$td.length && !\$td.find('input').length) curEan = (\$td.text() || '').trim();
								}
								if (\$mpnInput.length) \$mpnInput.val(curMpn || r.mpn || '');
								if (\$eanInput.length) \$eanInput.val(curEan || r.ean || '');
								else if ((curEan || r.ean) && !\$eanInput.length) {
									var \$td = $('tr').has('input.woo-mpn-input[data-product-id=\"' + pid + '\"]').find('td.column-ean');
									if (\$td.length && !\$td.find('input').length) 									\$td.text(curEan || r.ean || '');
								}
							}
								var found = e.data.found || 0;
								var total = e.data.total || 0;
								showStatus('" . esc_js( __( 'Done! Found', 'woo-mpn' ) ) . " ' + found + '/' + total + ' " . esc_js( __( 'products. Click Save to store.', 'woo-mpn' ) ) . "', false);
							}
							setTimeout(function() { showStatus('', false); }, 5000);
						}
					};
					window.addEventListener('message', handler2);
					var closeCheck = setInterval(function() {
						if (w.closed) {
							clearInterval(closeCheck);
							window.removeEventListener('message', debugHandler);
							window.removeEventListener('message', handler);
							window.removeEventListener('message', handler2);
							showStatus('" . esc_js( __( 'Popup closed.', 'woo-mpn' ) ) . "', false);
							setTimeout(function() { showStatus('', false); }, 3000);
						}
					}, 500);
				}).fail(function() {
					showStatus('" . esc_js( __( 'Failed to load products.', 'woo-mpn' ) ) . "', false);
					setTimeout(function() { showStatus('', false); }, 5000);
				});
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
				$('input[name=\"products[]\"]').not('[data-has-both=\"1\"]').each(function() { ids.push($(this).val()); });
				debugLog('Find all, ids=' + JSON.stringify(ids));
				openPuterPopup(ids);
			});

			$(document).on('change', '#cb-select-all', function() {
				$('input[name=\"products[]\"]').prop('checked', $(this).prop('checked'));
			});
			$(document).on('click', '#woo-mpn-apply-condition', function(e) {
				e.preventDefault();
				var cond = $('#gpf-condition-select').val();
				var checked = $('input[name=\"products[]\"]:checked').length;
				if (!cond) {
					alert('" . esc_js( __( 'Please select a condition (New, Refurbished, or Used).', 'woo-mpn' ) ) . "');
					return;
				}
				if (checked === 0) {
					alert('" . esc_js( __( 'Please select at least one product.', 'woo-mpn' ) ) . "');
					return;
				}
				$('#woo-mpn-products-form').append($('<input>').attr({type:'hidden',name:'woo_mpn_apply_condition',value:'1'})).submit();
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
		foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'ean_status', 'per_page', 'orderby', 'order', 'debug' ) as $param ) {
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
						<th scope="col" class="column-ean"><?php esc_html_e( 'EAN', 'woo-mpn' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$has_mpn  = ! empty( $item->mpn );
						$has_ean  = ! empty( $item->ean );
						$has_both = $has_mpn && $has_ean;
						$edit_url = get_edit_post_link( $item->id );
						$ean_writable = Woo_MPN_Product_Fields::get_ean_save_meta_key();
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="products[]" value="<?php echo (int) $item->id; ?>" <?php echo $has_both ? 'data-has-both="1" ' : ''; ?>title="<?php echo $has_both ? esc_attr__( 'Product has MPN and EAN - AI lookup skipped', 'woo-mpn' ) : ''; ?>" />
							</th>
							<td class="column-id"><?php echo $edit_url ? '<a href="' . esc_url( $edit_url ) . '">' . (int) $item->id . '</a>' : (int) $item->id; ?></td>
							<td class="column-title"><?php echo $edit_url ? '<a href="' . esc_url( $edit_url ) . '" class="row-title"><strong>' . esc_html( $item->title ?? '' ) . '</strong></a>' : '<strong>' . esc_html( $item->title ?? '' ) . '</strong>'; ?></td>
							<td class="column-sku"><?php echo esc_html( $item->sku ?? '' ); ?></td>
							<td class="column-price"><?php echo wp_kses_post( $item->price ?? '—' ); ?></td>
							<td class="column-stock"><?php echo esc_html( (string) ( $item->stock ?? '—' ) ); ?></td>
							<td class="column-mpn">
								<input type="text" name="mpn[<?php echo (int) $item->id; ?>]" value="<?php echo esc_attr( $item->mpn ?? '' ); ?>" class="woo-mpn-input" data-product-id="<?php echo (int) $item->id; ?>" data-has-mpn="<?php echo $has_mpn ? '1' : '0'; ?>" placeholder="<?php esc_attr_e( 'Enter MPN', 'woo-mpn' ); ?>" />
							</td>
							<td class="column-ean">
								<?php if ( $ean_writable ) : ?>
									<input type="text" name="ean[<?php echo (int) $item->id; ?>]" value="<?php echo esc_attr( $item->ean ?? '' ); ?>" class="woo-mpn-ean-input" data-product-id="<?php echo (int) $item->id; ?>" placeholder="<?php esc_attr_e( 'Enter EAN', 'woo-mpn' ); ?>" />
								<?php else : ?>
									<?php echo esc_html( $item->ean ?? '' ); ?>
								<?php endif; ?>
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
			<h1>
				<?php esc_html_e( 'MPN Products', 'woo-mpn' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=woo_mpn' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Settings', 'woo-mpn' ); ?></a>
			</h1>

			<?php
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			if ( isset( $_GET['gpf_done'] ) && is_numeric( $_GET['gpf_done'] ) ) {
				$n = (int) $_GET['gpf_done'];
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Google Feed condition applied to %d product.', 'Google Feed condition applied to %d products.', $n, 'woo-mpn' ), $n ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'MPNs and EANs saved successfully.', 'woo-mpn' ) . '</p></div>';
			}
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
					<?php if ( Woo_MPN_Product_Fields::get_ean_filter_meta_key() ) : ?>
					<div class="filter-row">
						<label for="ean_status"><?php esc_html_e( 'EAN Status', 'woo-mpn' ); ?></label>
						<select id="ean_status" name="ean_status">
							<option value=""><?php esc_html_e( 'All', 'woo-mpn' ); ?></option>
							<option value="has" <?php selected( isset( $_GET['ean_status'] ) && 'has' === $_GET['ean_status'] ); ?>><?php esc_html_e( 'Has EAN', 'woo-mpn' ); ?></option>
							<option value="empty" <?php selected( isset( $_GET['ean_status'] ) && 'empty' === $_GET['ean_status'] ); ?>><?php esc_html_e( 'No EAN', 'woo-mpn' ); ?></option>
						</select>
					</div>
					<?php endif; ?>
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
				foreach ( array( 's', 'product_cat', 'stock_status', 'mpn_status', 'ean_status', 'per_page', 'paged', 'orderby', 'order', 'debug' ) as $param ) {
					if ( ! empty( $_GET[ $param ] ) ) {
						echo '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( wp_unslash( $_GET[ $param ] ) ) . '" />';
					}
				}
				?>
				<div class="woo-mpn-bulk-actions" id="woo-mpn-bulk-actions">
					<button type="button" id="woo-mpn-find-selected" class="button" onclick="var s=document.getElementById('woo-mpn-status');if(s){s.className='woo-mpn-status woo-mpn-active';s.innerHTML='<span class=\'spinner is-active\'></span> <?php echo esc_js( __( 'Starting...', 'woo-mpn' ) ); ?>';}"><?php esc_html_e( 'Find MPN & EAN via AI (selected)', 'woo-mpn' ); ?></button>
					<button type="button" id="woo-mpn-find-all" class="button" onclick="var s=document.getElementById('woo-mpn-status');if(s){s.className='woo-mpn-status woo-mpn-active';s.innerHTML='<span class=\'spinner is-active\'></span> <?php echo esc_js( __( 'Starting...', 'woo-mpn' ) ); ?>';}"><?php esc_html_e( 'Find MPN & EAN via AI (all without both)', 'woo-mpn' ); ?></button>
					<button type="submit" name="woo_mpn_save" class="button button-primary"><?php esc_html_e( 'Save MPNs & EANs', 'woo-mpn' ); ?></button>
					<span class="woo-mpn-bulk-sep" style="margin: 0 8px; color: #c3c4c7;">|</span>
					<label for="gpf-condition-select" class="screen-reader-text"><?php esc_html_e( 'Google Feed condition', 'woo-mpn' ); ?></label>
					<select id="gpf-condition-select" name="gpf_condition" style="margin-right: 4px;">
						<option value=""><?php esc_html_e( 'Set condition (selected)', 'woo-mpn' ); ?></option>
						<option value="new"><?php esc_html_e( 'New', 'woo-mpn' ); ?></option>
						<option value="refurbished"><?php esc_html_e( 'Refurbished', 'woo-mpn' ); ?></option>
						<option value="used"><?php esc_html_e( 'Used', 'woo-mpn' ); ?></option>
					</select>
					<button type="button" id="woo-mpn-apply-condition" class="button"><?php esc_html_e( 'Apply to selected', 'woo-mpn' ); ?></button>
					<span class="description" style="margin-left: 8px;"><?php esc_html_e( 'Sets product condition for Google Product Feed (new, refurbished, used).', 'woo-mpn' ); ?></span>
				</div>
				<div class="woo-mpn-status" id="woo-mpn-status" role="status" aria-live="polite"></div>
				<?php if ( isset( $_GET['debug'] ) && '1' === $_GET['debug'] ) : ?>
				<div class="woo-mpn-debug-wrap woo-mpn-debug-active" id="woo-mpn-debug-wrap" aria-live="polite">
					<div class="woo-mpn-debug-header" id="woo-mpn-debug-header"><?php esc_html_e( 'MPN Debug', 'woo-mpn' ); ?></div>
					<div class="woo-mpn-debug" id="woo-mpn-debug"><div class="woo-mpn-debug-line"><span class="woo-mpn-debug-ts">[--]</span> <?php esc_html_e( 'Debug mode. Click Find MPN via AI to see flow.', 'woo-mpn' ); ?></div></div>
				</div>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Find MPN & EAN via AI opens a popup where Puter loads. Click Continue in the consent dialog if prompted. Products with both MPN and EAN are excluded.', 'woo-mpn' ); ?></p>
				<p class="description" style="margin-top: 8px; color: #646970;"><?php esc_html_e( 'Note: The free search has usage limitations and will support around a few hundred searches per month. This is a general estimate based on testing; the exact limit may vary.', 'woo-mpn' ); ?></p>
				<?php $this->render_products_table( $list_table ); ?>
			</form>
		</div>
		<?php
	}
}
