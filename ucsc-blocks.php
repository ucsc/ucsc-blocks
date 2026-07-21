<?php
/**
 * Plugin Name:       UCSC Blocks
 * Description:       Blocks for UCSC WordPress websites.
 * Version: 3.0.0
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
 * Update checks only matter in the dashboard and during cron, so skip the
 * work on front-end requests. wp_is_auto_update_enabled_for_type() lives in
 * an admin-only file, so make sure it's loaded before calling it; it returns
 * false whenever the site has disabled updates (AUTOMATIC_UPDATER_DISABLED /
 * WP_AUTO_UPDATE_CORE constants, automatic_updater_disabled / auto_update_plugin
 * filters, host overrides, etc.).
 *
 * @see https://github.com/YahnisElsts/plugin-update-checker
 */
if ( ( is_admin() || wp_doing_cron() ) && file_exists( __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
	if ( ! function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	if ( wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
		require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

		$ucsc_blocks_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/ucsc/ucsc-blocks/',
			__FILE__,
			'ucsc-blocks'
		);
		// Use the release asset (ucsc-blocks.zip) instead of the source archive.
		$ucsc_blocks_update_checker->getVcsApi()->enableReleaseAssets( '/ucsc-blocks\.zip/' );
	}
}

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