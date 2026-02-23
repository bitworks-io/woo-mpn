# Changelog

All notable changes since the last release (1.3.24).

## [1.5.0]

### Added
- **Google Product Feed condition bulk action** – Set product condition (new, refurbished, used) for selected products on the MPN Products page. Updates `_woocommerce_gpf_data` for WooCommerce Google Product Feed compatibility.
- **Usage note** – Note about free search limitations (~few hundred searches/month) on the MPN Products page.

### Changed
- **Product selection** – Products with both MPN and EAN can now be selected for the GPF condition bulk action.

### Fixed
- **per_page persistence** – Per-page and filter params (e.g. `ean_status`, `debug`) now persist correctly after save.
- **Debug mode** – Lookup log and popup output only shown when `debug=1`; popup auto-closes when not in debug.
- **Ampersand display** – Fixed "Finding MPN and EAN" display in popup status.

### Technical
- **AI model** – Switched to `claude-haiku-4-5` for all lookups (lower cost).
- **Checkbox logic** – Replaced `disabled` with `data-has-both` attribute; "Find all" excludes products with both MPN and EAN via selector.

---

## [1.4.0]

### Added
- **EAN/GTIN support** – Configurable source: plugin field (`_ean`), WooCommerce Global Unique ID, `_gtin`, `_ts_gtin`, or custom meta/attribute.
- **EAN column** – On MPN Products page (editable when writable source).
- **EAN filter** – Filter by Has EAN / No EAN on MPN Products page.
- **EAN column in Products list** – Sortable when meta-based source.
- **AI lookup** – Finds both MPN and EAN; skips products that already have both.
- **EAN in feed plugins** – Registered for GLA, WooCommerce Google Product Feed, Product Feed Manager.

### Changed
- **Product fields** – EAN field added to product edit screen (SKU section) and variations when using plugin field.
- **Settings** – New EAN source options in WooCommerce > Settings > Products > MPN.

---

## [1.3.26]

### Added
- **Checkout redirect** – Maps `products=woocommerce_gpf_123:1` to `products=123:1` in checkout URLs for sharable links from Google.

### Changed
- **Feed approach** – Removed feed modification filters; use redirect for checkout URL generation.

---

## [1.3.25]

### Added
- **Setting** – Use WooCommerce product ID in feed (guid) for automated checkout URL generation
- **WooCommerce Google Product Feed** – Sets `guid` and `item_group_id` to match product IDs when enabled

---

## [1.3.24] (last pushed release)

- Product feed integration: MPN selectable in feed mapping dropdowns
- Google for WooCommerce / WooCommerce.com Google Product Feed: MPN in Product fields and Custom attributes
- WooCommerce Google Product Feed (Lee Willis): MPN in custom field dropdown and feed injection
- Product Feed Manager: MPN injected via `wppfm_feed_item_value`
- Load plugin earlier (priority 5) for feed plugins
