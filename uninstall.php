<?php
/**
 * Uninstall Manufacturer Product Number (MPN).
 *
 * Removes MPN meta data from products when the plugin is uninstalled.
 *
 * @package WooCommerce_MPN
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete all _mpn meta keys from postmeta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_mpn'" );
