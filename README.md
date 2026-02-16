# Manufacturer Product Number (MPN) for WooCommerce

**By Tyria Tech**

A WooCommerce extension that adds a Manufacturer Product Number (MPN) field to products, displayed in the SKU section and the admin product table.

## Features

- **MPN Field** – Adds an MPN input in the product edit screen alongside the SKU field
- **Product Table Column** – Shows MPN in the admin products list with sortable column
- **Product Page Display** – Optional setting to show MPN alongside SKU and other product details on the frontend
- **Variable Products** – Supports MPN per variation
- **HPOS Compatible** – Declares compatibility with WooCommerce High Performance Order Storage
- **Modern APIs** – Uses WooCommerce CRUD methods (`get_meta`, `update_meta_data`)

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+

## Installation

1. Copy the `woo-mpn` folder to `wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Ensure WooCommerce is active

## Usage

1. Edit a product in WooCommerce
2. Open the **Product Data** panel
3. In the **Inventory** tab (or variation options for variable products), find the **Manufacturer Product Number (MPN)** field
4. Enter the MPN and save

The MPN will appear in the products list table in the admin.

**Display on product page:** Go to WooCommerce → Settings → Products → Manufacturer Product Number (MPN) and enable "Display on product page" to show MPN alongside SKU on single product pages. The MPN value aligns right-justified with other product details (SKU, Categories, Brand).

## Programmatic Access

```php
// Get MPN for a product
$product = wc_get_product( $product_id );
$mpn = $product->get_meta( '_mpn' );

// Or use the helper (handles variable products)
$mpn = Woo_MPN_Product_Fields::get_product_mpn( $product );
```

## File Structure

```
woo-mpn/
├── woo-mpn.php                      # Main plugin file
├── includes/
│   ├── class-woo-mpn-product-fields.php   # MPN field in product edit
│   ├── class-woo-mpn-admin-columns.php    # Admin table column
│   ├── class-woo-mpn-settings.php         # Settings (display on product page)
│   └── class-woo-mpn-frontend.php        # Frontend MPN display
├── uninstall.php
├── readme.txt
└── README.md
```

## License

GPL v2 or later
