<?php
/**
 * Plugin Name:       UCSC Blocks
 * Description:       Blocks for UCSC WordPress websites.
 * Version: 1.1.3
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
 * Enqueue scripts for the block editor
 */
function ucsc_events_enqueue_block_editor_assets() {
	wp_localize_script(
		'ucsc-events-editor-script',
		'ucscEventsData',
		array(
			'nonce' => wp_create_nonce('ucsc_events_nonce'),
			'ajaxUrl' => admin_url('admin-ajax.php')
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ucsc_events_enqueue_block_editor_assets' );

/**
 * Add nonce to frontend
 */
function ucsc_events_enqueue_frontend_assets() {
	if (has_block('ucsc/events')) {
		wp_add_inline_script(
			'wp-block-ucsc-events-view-script',
			'window.ucscEventsNonce = ' . json_encode(wp_create_nonce('ucsc_events_nonce')) . ';\n' .
			'window.ajaxurl = ' . json_encode(admin_url('admin-ajax.php')) . ';',
			'before'
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ucsc_events_enqueue_frontend_assets' );

/**
 * Handle cache clearing AJAX request
 */
function ucsc_events_clear_cache() {
	// Verify nonce for security
	if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'ucsc_events_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ) );
		return;
	}

	// Check user permissions
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$api_url = isset($_POST['api_url']) ? sanitize_url( $_POST['api_url'] ) : '';
	
	if ( ! empty( $api_url ) ) {
		// Clear cache for different item counts
		for ( $i = 1; $i <= 20; $i++ ) {
			$cache_key = 'ucsc_events_' . md5( $api_url . $i );
			delete_transient( $cache_key );
		}
		
		wp_send_json_success( array( 'message' => 'Cache cleared successfully' ) );
	} else {
		wp_send_json_error( array( 'message' => 'Invalid API URL' ) );
	}
}
add_action( 'wp_ajax_ucsc_events_clear_cache', 'ucsc_events_clear_cache' );

/**
 * Fetch events data from external API
 */
if ( ! function_exists( 'ucsc_events_fetch_data' ) ) {
	function ucsc_events_fetch_data( $api_url, $per_page = 5 ) {
		if ( empty( $api_url ) ) {
			return array();
		}

		// Create cache key based on URL and per_page
		$cache_key = 'ucsc_events_' . md5( $api_url . $per_page );
		
		// Try to get cached data first
		$cached_data = get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Validate URL
		if ( ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
			return array();
		}

		// Prepare API URL with per_page parameter
		$full_url = add_query_arg( array(
			'per_page' => absint( $per_page )
		), $api_url );

		// Fetch data from API
		$response = wp_remote_get( $full_url, array(
			'timeout' => 8,
			'headers' => array(
				'User-Agent' => 'UCSC Events Block/1.0',
				'Accept' => 'application/json'
			),
			'sslverify' => true
		) );

		if ( is_wp_error( $response ) ) {
			// Log error for debugging
			error_log( 'UCSC Events API Error: ' . $response->get_error_message() );
			return array();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			error_log( 'UCSC Events API Error: HTTP ' . $response_code );
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$fetched = json_decode( $body, true );
		$data = $fetched['events'];

		if ( ! is_array( $data ) ) {
			error_log( 'UCSC Events API Error: Invalid JSON response' );
			return array();
		}

		// Process and clean the data
		$events = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$featured_image = '';
			
			// Try to get featured image from _embedded data
			if ( isset( $item['image']['url'] ) ) {
				$featured_image = $item['image']['url'];
			} elseif ( isset( $item['image']['sizes']['medium']['url'] ) ) {
				$featured_image = $item['image']['sizes']['medium']['url'];
			}

			$events[] = array(
				'title' => isset( $item['title'] ) ? $item['title'] : 'Untitled',
				'organizer' => isset( $item['organizer']['organizer'] ) ? $item['organizer']['organizer'] : '',
				'date' => isset( $item['start_date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $item['start_date'] ) ) : '',
				'venue' => isset( $item['venue']['venue'] ) ? $item['venue']['venue'] : '',
				'featured_image' => $featured_image,
				'link' => isset( $item['url'] ) ? $item['url'] : ''
			);
		}

		// Cache the processed data for 15 minutes
		set_transient( $cache_key, $events, 30 * MINUTE_IN_SECONDS );

		return $events;
	}
}