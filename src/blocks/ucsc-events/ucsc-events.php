<?php
/**
 * Server-side functions for the UCSC Events block.
 *
 * @package UcscBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UCSC Tribe Events REST API endpoints.
 *
 * Single source of truth for the base URLs, shared by the server-side renderer
 * and the block editor (passed to JS via wp_localize_script). Filterable so a
 * site can point the block at a different events source.
 *
 * @return array Associative array with 'events' and 'organizers' endpoint URLs.
 */
if ( ! function_exists( 'ucsc_events_get_api_endpoints' ) ) {
	function ucsc_events_get_api_endpoints() {
		return apply_filters(
			'ucsc_events_api_endpoints',
			array(
				'events'     => 'https://events.ucsc.edu/wp-json/tribe/events/v1/events',
				'organizers' => 'https://events.ucsc.edu/wp-json/tribe/events/v1/organizers',
			)
		);
	}
}

/**
 * Enqueue scripts for the block editor
 */
function ucsc_events_enqueue_block_editor_assets() {
	$endpoints = ucsc_events_get_api_endpoints();

	wp_localize_script(
		'ucsc-events-editor-script',
		'ucscEventsData',
		array(
			'nonce'         => wp_create_nonce('ucsc_events_nonce'),
			'ajaxUrl'       => admin_url('admin-ajax.php'),
			'eventsUrl'     => $endpoints['events'],
			'organizersUrl' => $endpoints['organizers'],
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ucsc_events_enqueue_block_editor_assets' );

/**
 * Add nonce to frontend
 */
function ucsc_events_enqueue_frontend_assets() {
	if (has_block('ucsc/events')) {
		wp_enqueue_style('dashicons');
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
 * Build the events API URL from selected organizers.
 *
 * Organizers are filtered with the Tribe `organizer[]` query argument. When no
 * organizers are selected, an optional legacy URL (from older blocks that stored
 * a hand-built `apiUrl`) is used as a fallback; otherwise an empty string is
 * returned so an unconfigured block renders a placeholder instead of the feed.
 *
 * IDs are sanitized and sorted so the resulting URL — and therefore the cache
 * key derived from it — is deterministic regardless of selection order.
 *
 * @param array  $organizer_ids Organizer IDs to filter by.
 * @param string $legacy_url    Optional legacy API URL for backward compatibility.
 * @return string The events API URL to fetch, or '' when nothing is configured.
 */
if ( ! function_exists( 'ucsc_events_build_api_url' ) ) {
	function ucsc_events_build_api_url( $organizer_ids, $legacy_url = '' ) {
		$endpoints = ucsc_events_get_api_endpoints();
		$base      = $endpoints['events'];

		$organizer_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $organizer_ids ) ) ) );
		sort( $organizer_ids );

		if ( ! empty( $organizer_ids ) ) {
			return add_query_arg( array( 'organizer' => $organizer_ids ), $base );
		}

		if ( ! empty( $legacy_url ) && filter_var( $legacy_url, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $legacy_url, array( 'http', 'https' ) );
		}

		return '';
	}
}

/**
 * Extract sanitized organizer IDs from a block's `organizers` attribute.
 *
 * The attribute is an array of `{ id, name }` objects supplied by the editor.
 * Only the IDs are used server-side; names are display-only.
 *
 * @param mixed $organizers Raw organizers attribute value.
 * @return int[] Sanitized organizer IDs.
 */
if ( ! function_exists( 'ucsc_events_get_organizer_ids' ) ) {
	function ucsc_events_get_organizer_ids( $organizers ) {
		if ( ! is_array( $organizers ) ) {
			return array();
		}

		$ids = array();
		foreach ( $organizers as $organizer ) {
			if ( is_array( $organizer ) && isset( $organizer['id'] ) ) {
				$id = absint( $organizer['id'] );
			} else {
				$id = absint( $organizer );
			}
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}

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
		wp_send_json_error( array( 'message' => 'Insufficient permissions to clear the cache' ) );
		return;
	}

	// Rebuild the API URL server-side from the submitted organizer IDs (and any
	// legacy URL) so the cache key matches the one used during rendering.
	$organizer_ids = array();
	if ( isset( $_POST['organizers'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['organizers'] ), true );
		$organizer_ids = ucsc_events_get_organizer_ids( $decoded );
	}

	$legacy_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ), array( 'http', 'https' ) ) : '';

	$api_url = ucsc_events_build_api_url( $organizer_ids, $legacy_url );

	// Mirror the fetch path so we target the same transient for this selection.
	$categories = isset( $_POST['categories'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['categories'] ) ) : array();
	$tags       = isset( $_POST['tags'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['tags'] ) ) : array();

	if ( ! empty( $api_url ) ) {
		$cache_key = ucsc_events_cache_key( $api_url, $categories, $tags );
		delete_transient( $cache_key );

		wp_send_json_success( array( 'message' => 'Cache cleared successfully' ) );
	} else {
		wp_send_json_error( array( 'message' => 'Invalid API URL' ) );
	}
}
add_action( 'wp_ajax_ucsc_events_clear_cache', 'ucsc_events_clear_cache' );

/**
 * Sanitize a list of taxonomy slugs from external/editor input.
 *
 * Accepts either an array of slugs or a comma-separated string. Each value is
 * passed through sanitize_title() and empties are dropped, so the result is
 * safe to use in cache keys and API query strings.
 *
 * @param array|string $slugs Raw slug list.
 * @return string[] Cleaned, re-indexed slug list.
 */
if ( ! function_exists( 'ucsc_events_sanitize_slugs' ) ) {
	function ucsc_events_sanitize_slugs( $slugs ) {
		if ( is_string( $slugs ) ) {
			$slugs = explode( ',', $slugs );
		}

		if ( ! is_array( $slugs ) ) {
			return array();
		}

		$clean = array_map( 'sanitize_title', $slugs );

		return array_values( array_filter( $clean ) );
	}
}

/**
 * Build the transient cache key for a fetch, scoped to the URL and filters.
 *
 * Filters are folded into the key so different category/tag selections cache
 * separately. Both the fetch and the cache-clear handler must use this helper
 * so they target the same transient.
 */
if ( ! function_exists( 'ucsc_events_cache_key' ) ) {
	function ucsc_events_cache_key( $api_url, $categories = array(), $tags = array() ) {
		return 'ucsc_events_' . md5(
			$api_url
			. '|cats=' . implode( ',', $categories )
			. '|tags=' . implode( ',', $tags )
		);
	}
}

/**
 * Fetch events data from external API.
 *
 * Always fetches the maximum 50 events from the API and caches them.
 * Callers are responsible for slicing the result to the desired count.
 *
 * @param string       $api_url    Events API endpoint.
 * @param array|string $categories Category slugs to filter by (optional).
 * @param array|string $tags       Tag slugs to filter by (optional).
 */
if ( ! function_exists( 'ucsc_events_fetch_data' ) ) {
	function ucsc_events_fetch_data( $api_url, $categories = array(), $tags = array() ) {
		if ( empty( $api_url ) ) {
			return array();
		}

		// Sanitize filter slugs before they touch the cache key or query string.
		$categories = ucsc_events_sanitize_slugs( $categories );
		$tags       = ucsc_events_sanitize_slugs( $tags );

		// Create cache key based on URL and selected filters
		$cache_key = ucsc_events_cache_key( $api_url, $categories, $tags );

		// Try to get cached data first
		$cached_data = get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Validate URL
		if ( ! filter_var( $api_url, FILTER_VALIDATE_URL ) ) {
			return array();
		}

		// Always fetch the maximum number of events (API caps at 50)
		$query_args = array(
			'per_page'     => 50,
			'starts_after' => 'yesterday'
		);

		// Forward category/tag filters as comma-separated slugs (OR semantics).
		if ( ! empty( $categories ) ) {
			$query_args['categories'] = implode( ',', $categories );
		}
		if ( ! empty( $tags ) ) {
			$query_args['tags'] = implode( ',', $tags );
		}

		$full_url = add_query_arg( $query_args, $api_url );

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
				'link' => isset( $item['url'] ) ? $item['url'] : '',
				'slug' => isset( $item['slug'] ) ? $item['slug'] : ''
			);
		}

		// Cache the processed data for 15 minutes
		set_transient( $cache_key, $events, HOUR_IN_SECONDS );

		return $events;
	}
}
