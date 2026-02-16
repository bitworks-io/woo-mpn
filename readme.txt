=== Manufacturer Product Number (MPN) ===

Contributors: tyriatech
Tags: woocommerce, product, mpn, manufacturer, sku
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a Manufacturer Product Number (MPN) field to WooCommerce products with admin table display.

== Description ==

Manufacturer Product Number (MPN) by Tyria Tech extends WooCommerce to store and display manufacturer part numbers for your products.

**Features:**

* Adds an MPN field in the product edit screen (SKU section)
* Supports both simple and variable products
* Displays MPN in the admin product list table
* Sortable MPN column
* Optional display of MPN on the product page (alongside SKU, etc.) - enable in Settings
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

== Changelog ==

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
