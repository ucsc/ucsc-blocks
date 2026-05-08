<?php
/**
 * Plugin Name:       UCSC Blocks
 * Description:       Blocks for UCSC WordPress websites.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Update URI:        https://github.com/ucsc/ucsc-blocks
 * Author:            UC Santa Cruz, Communications
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
 * Plugin updates via GitHub releases.
 *
 * @see https://github.com/YahnisElsts/plugin-update-checker
 */
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/ucsc/ucsc-blocks/',
	__FILE__,
	'ucsc-blocks'
);

/**
 * Include block-specific PHP files.
 */
$ucsc_block_includes = array(
	'ucsc-events/ucsc-events.php',
	'calendar-feed/calendar-feed.php',
);

foreach ( $ucsc_block_includes as $include ) {
	$file = __DIR__ . '/build/blocks/' . $include;
	if ( file_exists( $file ) ) {
		require_once $file;
	}
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
		'ucsc-events',
		'calendar-feed',
	);

	foreach ($custom_blocks as $block) {
		register_block_type(__DIR__ . '/build/blocks/' . $block);
	}
}
add_action( 'init', 'ucsc_blocks_init' );