<?php
/**
 * Build plugin zip for WordPress installation.
 * Usage: php build-zip.php
 *
 * @package WooCommerce_MPN
 */

// Run from CLI or include guard
if ( php_sapi_name() !== 'cli' && ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_dir = __DIR__;
$version  = '1.0.0';
if ( file_exists( $base_dir . '/woo-mpn.php' ) ) {
	$content = file_get_contents( $base_dir . '/woo-mpn.php' );
	if ( preg_match( '/Version:\s*(\d+\.\d+\.\d+)/', $content, $m ) ) {
		$version = $m[1];
	}
}

$zip_name = "woo-mpn-{$version}.zip";
$zip_path = $base_dir . '/' . $zip_name;

$exclude = array(
	'.git', '.cursor', 'build', '*.zip', '.DS_Store', '.gitignore',
	'build-zip.sh', 'build-zip.php', 'zip-info.txt',
);

function should_exclude( $path, $rel, $exclude ) {
	$name = basename( $path );
	$parts = explode( '/', $rel );
	foreach ( $exclude as $pattern ) {
		if ( $name === $pattern ) {
			return true;
		}
		if ( $pattern === '*.zip' && preg_match( '/\.zip$/i', $name ) ) {
			return true;
		}
	}
	if ( in_array( '.git', $parts, true ) || in_array( '.cursor', $parts, true ) ) {
		return true;
	}
	return false;
}

$zip = new ZipArchive();
if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
	fprintf( STDERR, "Error: Cannot create zip file %s\n", $zip_path );
	exit( 1 );
}

$iter = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$prefix_len = strlen( $base_dir ) + 1;
$added     = 0;

foreach ( $iter as $item ) {
	$path = $item->getPathname();
	$rel  = substr( $path, $prefix_len );
	$parts = explode( '/', $rel );

	// Skip excluded
	if ( should_exclude( $path, $rel, $exclude ) ) {
		continue;
	}

	$archive_name = 'woo-mpn/' . $rel;
	if ( $item->isDir() ) {
		$zip->addEmptyDir( $archive_name );
	} else {
		$zip->addFile( $path, $archive_name );
		$added++;
	}
}

$zip->close();

echo "Created: {$zip_name} ({$added} files)\n";
exit( 0 );
