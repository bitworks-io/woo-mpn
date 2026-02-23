=== Manufacturer Product Number (MPN) ===

Contributors: tyriatech
Tags: woocommerce, product, mpn, manufacturer, sku
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Manufacturer Product Number (MPN) and EAN/GTIN fields to WooCommerce products with admin table display.

== Description ==

Manufacturer Product Number (MPN) by Tyria Tech extends WooCommerce to store and display manufacturer part numbers for your products.

**Features:**

* Adds MPN and EAN/GTIN fields in the product edit screen (SKU section)
* Supports both simple and variable products
* Displays MPN and EAN in the admin product list table
* Sortable MPN and EAN columns
* Optional display of MPN on the product page (alongside SKU, etc.) - enable in Settings
* MPN Products admin page with product table (ID, title, SKU, price, stock, MPN, EAN)
* Filters: search, category, stock status, MPN status, EAN status
* Puter.js AI chat for automatic MPN and EAN lookup (products missing either, no API keys)
* HPOS (High Performance Order Storage) compatible
* Uses WooCommerce CRUD APIs for modern data handling

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woo-mpn/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and active
4. Edit any product to add an MPN in the Product Data > Inventory section (with SKU)
5. To show MPN on the product page: WooCommerce > Settings > Products > Manufacturer Product Number (MPN) > Enable "Display on product page"

== Frequently Asked Questions ==

= Where is the MPN field displayed? =

The MPN field appears in the product edit screen within the Product Data panel, in the same section as the SKU field (Inventory tab for simple products, or in the variation options for variable products).

= Can I display MPN on the frontend? =

Yes! Enable "Display on product page" in WooCommerce > Settings > Products > Manufacturer Product Number (MPN). The MPN will appear alongside SKU and other product details. You can also access it programmatically via `$product->get_meta('_mpn')`.

= Product feed compatibility =

MPN is automatically available in product feed mappings for these plugins:

* **Google for WooCommerce / Google Listings & Ads** – MPN appears in Product fields and Custom attributes dropdowns (Marketing > Google for WooCommerce > Attribute mapping)
* **WooCommerce.com Google Product Feed** – Uses the same attribute mapping; MPN is selectable when creating mapping rules
* **WooCommerce Google Product Feed** (Lee Willis, woocommerce-gpf) – MPN is injected into the feed via the woocommerce_gpf_feed_item_google filter
* **Product Feed Manager** (WPMarketingRobot) – MPN is injected via the wppfm_feed_item_value filter

For other feed plugins (CTX Feed, WebToffee, AdTribes Product Feed PRO, etc.): map the custom field or product meta `_mpn` to the MPN attribute in your feed configuration. Many plugins support "Custom fields and post meta" or "Third-party attributes" where you can select or enter `_mpn`.

== Changelog ==

= 1.5.0 =
* Add Google Product Feed condition bulk action (new, refurbished, used) for selected products
* Allow selecting products with MPN+EAN for GPF condition (checkboxes no longer disabled)
* Fix per_page persistence after save
* Debug mode: only show lookup log and popup output when debug=1; auto-close popup when not in debug
* Fix ampersand display in popup status (Finding MPN and EAN)
* Use claude-haiku-4-5 for all lookups (lower cost)
* Add usage note for free search limitations

= 1.4.0 =
* EAN/GTIN support: configurable source (plugin field, WooCommerce Global Unique ID, _gtin, _ts_gtin, or custom meta/attribute)
* EAN column and filter on MPN Products page
* AI lookup finds both MPN and EAN
* EAN registered for feed plugins (GLA, WooCommerce Google Product Feed, Product Feed Manager)
* EAN column in Products list (sortable when meta-based source)

= 1.3.26 =
* Add redirect to map WooCommerce Product Feed IDs to WooCommerce product IDs in checkout URLs
* Enables sharable checkout URL generation from Google (products=woocommerce_gpf_123:1 redirects to products=123:1)
* Remove feed modification filters; use redirect approach instead

= 1.3.25 =
* Add setting: Use WooCommerce product ID in feed (guid) for automated checkout URL generation
* WooCommerce Google Product Feed: set guid and item_group_id to match product IDs when enabled

= 1.3.24 =
* Product feed integration: MPN selectable in feed mapping dropdowns
* Google for WooCommerce / WooCommerce.com Google Product Feed: MPN in Product fields and Custom attributes
* WooCommerce Google Product Feed (Lee Willis): woocommerce_gpf_custom_field_list adds MPN to custom field dropdown; woocommerce_gpf_feed_item_google injects MPN into feed
* Product Feed Manager (WPMarketingRobot): wppfm_feed_item_value injects MPN into feed
* Load plugin earlier (priority 5) so feed plugins pick up attribute mapping filters
* Document feed compatibility in readme

= 1.3.22 =
* MPN column in Products table: responsive width (10%, min 80px, max 180px), word-wrap for long values

= 1.3.21 =
* Find MPN via AI: serve popup via admin-ajax instead of menu page (fixes "Sorry, you are not allowed to access this page" when popup opens)

= 1.3.20 =
* Find MPN via AI: load Puter only in popup on button click (fixes 401 whoami at page load)

= 1.3.19 =
* Find MPN via AI: remove Puter script dependency so handler loads when Puter fails

= 1.3.18 =
* Find MPN via AI: use Puter.js native consent dialog (removed custom modal)

= 1.3.17 =
* Find MPN via AI: add Puter as script dependency for correct load order

= 1.3.16 =
* Find MPN via AI: Puter.js per tutorial - no sign-in, Claude Haiku 4.5, response parsing

= 1.3.15 =
* Debug: revert to inline MPN Debug panel (1.3.13 style), add console.log fallback

= 1.3.14 =
* Debug popup: create via JavaScript and append to body (no PHP render)

= 1.3.13 =
* Debug popup: append to body for reliable fixed positioning in admin

= 1.3.12 =
* Find MPN via AI: no sign-in required (Puter creates temp access automatically)
* Debug mode: floating popup window, draggable

= 1.3.11 =
* Find MPN via AI: debug mode with ?debug=1 in URL to show background debug panel

= 1.3.10 =
* Find MPN via AI: restore immediate status on click (inline onclick), remove Puter script dependency

= 1.3.9 =
* Find MPN via AI: fix "stuck on Starting..." - Puter sign-in flow, 60s timeout, better response parsing
* Puter.js now a script dependency for correct load order

= 1.3.8 =
* Find MPN via AI: fix "nothing happens" - script now loads without waiting for Puter
* Immediate feedback: inline onclick shows "Starting..." the moment you click
* Improved Puter API response parsing for different response formats

= 1.3.7 =
* Find MPN via AI: add visible progress status (Loading... Finding 1/2: Product name... Done!)
* Status box with spinner, disables buttons during lookup
* Summary when complete: "Found X/Y MPNs"

= 1.3.6 =
* Fix search: include SKU in search (WordPress 's' only searches title/content by default)
* Add per-page selector: 20, 50, 100, 150, 200 items per page
* Search now finds products by SKU and variation SKU

= 1.3.5 =
* Fix empty product list: replace WP_List_Table display with plain HTML table (same data, works)
* Keep Woo_MPN_Product_List_Table for query/prepare_items, use custom render for display

= 1.3.4 =
* Add debug view to troubleshoot empty list (led to fix in 1.3.5)

= 1.3.3 =
* Revert to 1.3.0 structure: custom MPN Products page with list table (fixes WooCommerce crash)
* Keep get_terms() WP_Error guard in admin page
* MPN column in Products list: plain text (no inline editing there)

= 1.3.2 =
* Fix blank page: guard get_terms() result (WP_Error) in restrict_manage_posts

= 1.3.1 =
* Use WooCommerce Products list (edit.php?post_type=product) - reverted in 1.3.3
* MPN Products menu redirects to Products > All Products
* Inline editable MPN column, filters (category, stock, MPN status), Save MPNs, Find MPN via AI

= 1.3.0 =
* Replace Google with Puter.js AI chat for MPN lookup
* Minimal prompts for MPN-only response
* Primary: product title; backup: product page URL

= 1.2.0 =
* MPN Products admin page with product table
* Filters: search, category, stock, MPN status
* Puter.js AI for automatic MPN lookup
* Products with existing MPN: editable only, no AI lookup

= 1.1.5 =
* Fix MPN alignment: use theme's span class "title" for label (matches SKU structure)

= 1.1.4 =
* Fix MPN alignment: use dedicated CSS file, add sku_wrapper class to inherit theme styling, stronger flexbox rules

= 1.1.3 =
* Fix MPN alignment on product page to match SKU, Categories, and Brand (right-justified value)

= 1.1.2 =
* Version bump

= 1.0.0 =
* Initial release
* MPN field in SKU section
* Admin product table column
* Variable product support
* HPOS compatibility
* Setting to display MPN on product page (optional)
