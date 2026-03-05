<?php
/**
 * Plugin Name:       UCSC Blocks
 * Description:       Blocks for UCSC WordPress websites.
 * Version: 1.2.0
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Author:            University of California, Santa Cruz
 * Author URI:        https://github.com/ucsc
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ucsc-blocks
 *
 * @package UcscBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function ucsc_blocks_init() {
	$custom_blocks = array(
		'ucsc-events'
	);

	foreach ($custom_blocks as $block) {
		register_block_type(__DIR__ . '/build/blocks/' . $block);
	}
}
add_action( 'init', 'ucsc_blocks_init' );

/**
 * Load block-specific server-side PHP files.
 */
$ucsc_blocks_dir = __DIR__ . '/build/blocks/';
$ucsc_block_includes = array(
	'ucsc-events/ucsc-events.php',
);

foreach ( $ucsc_block_includes as $include ) {
	$file = $ucsc_blocks_dir . $include;
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}