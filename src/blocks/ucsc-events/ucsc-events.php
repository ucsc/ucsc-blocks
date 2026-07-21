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
 * a hand-built `apiUrl`) is used as a fallback. Failing that, if category/tag
 * filters are active the base campus feed is returned so those filters can drive
 * a campus-wide fetch; otherwise an empty string is returned so an unconfigured
 * block renders a placeholder instead of the feed.
 *
 * IDs are sanitized and sorted so the resulting URL — and therefore the cache
 * key derived from it — is deterministic regardless of selection order.
 *
 * @param array  $organizer_ids Organizer IDs to filter by.
 * @param string $legacy_url    Optional legacy API URL for backward compatibility.
 * @param bool   $has_filters   Whether category/tag filters are active.
 * @return string The events API URL to fetch, or '' when nothing is configured.
 */
if ( ! function_exists( 'ucsc_events_build_api_url' ) ) {
	function ucsc_events_build_api_url( $organizer_ids, $legacy_url = '', $has_filters = false ) {
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

		// No organizer or legacy URL, but category/tag filters can still fetch
		// the campus-wide feed (filters are applied by ucsc_events_fetch_data).
		if ( $has_filters ) {
			return $base;
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

	// Mirror the fetch path so we target the same transient for this selection.
	$categories = isset( $_POST['categories'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['categories'] ) ) : array();
	$tags       = isset( $_POST['tags'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['tags'] ) ) : array();

	$has_filters = ! empty( $categories ) || ! empty( $tags );
	$api_url     = ucsc_events_build_api_url( $organizer_ids, $legacy_url, $has_filters );

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
 * AJAX: return processed events for the block editor preview.
 *
 * The editor cannot fetch the events API directly. That is a cross-origin
 * request, and the CDN in front of the API caches responses without varying on
 * the Origin header, so cached copies lack an Access-Control-Allow-Origin header
 * and the browser blocks them (intermittently, depending on what is cached).
 *
 * Proxying through the server sidesteps CORS entirely and reuses the same fetch,
 * sanitization, and transient cache as the frontend renderer, so the editor
 * preview matches the published output. Editor-only: requires the events nonce
 * and edit_posts capability.
 */
function ucsc_events_preview() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'ucsc_events_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ) );
		return;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Rebuild the request from the block's attributes, mirroring render.php.
	$organizer_ids = array();
	if ( isset( $_POST['organizers'] ) ) {
		$decoded       = json_decode( wp_unslash( $_POST['organizers'] ), true );
		$organizer_ids = ucsc_events_get_organizer_ids( $decoded );
	}

	$legacy_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ), array( 'http', 'https' ) ) : '';
	$categories = isset( $_POST['categories'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['categories'] ) ) : array();
	$tags       = isset( $_POST['tags'] ) ? ucsc_events_sanitize_slugs( wp_unslash( $_POST['tags'] ) ) : array();

	$has_filters = ! empty( $categories ) || ! empty( $tags );
	$api_url     = ucsc_events_build_api_url( $organizer_ids, $legacy_url, $has_filters );

	// Nothing configured — return an empty set so the editor shows its placeholder.
	if ( empty( $api_url ) ) {
		wp_send_json_success( array( 'events' => array() ) );
		return;
	}

	// Returns the processed, cached event list (up to 50); the editor applies the
	// item count, de-duplication, and series detection client-side.
	$events = ucsc_events_fetch_data( $api_url, $categories, $tags );

	wp_send_json_success( array( 'events' => $events ) );
}
add_action( 'wp_ajax_ucsc_events_preview', 'ucsc_events_preview' );

/**
 * Shared nonce + capability guard for the editor's AJAX lookups.
 *
 * @return bool True when the request is a valid editor request.
 */
if ( ! function_exists( 'ucsc_events_verify_editor_request' ) ) {
	function ucsc_events_verify_editor_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'ucsc_events_nonce' ) ) {
			return false;
		}

		return current_user_can( 'edit_posts' );
	}
}

/**
 * Derive a sibling Tribe REST endpoint (e.g. categories, tags) from the events
 * endpoint, so all lookups share the single filterable source of truth.
 *
 * @param string $taxonomy Route name, e.g. 'categories' or 'tags'.
 * @return string The derived endpoint URL.
 */
if ( ! function_exists( 'ucsc_events_get_taxonomy_endpoint' ) ) {
	function ucsc_events_get_taxonomy_endpoint( $taxonomy ) {
		$endpoints = ucsc_events_get_api_endpoints();

		return preg_replace( '#/events/?$#', '/' . $taxonomy, $endpoints['events'] );
	}
}

/**
 * Fetch and JSON-decode a Tribe REST endpoint server-side for editor lookups.
 *
 * @param string $url Endpoint URL to fetch.
 * @return array|WP_Error Decoded response array, or WP_Error on failure.
 */
if ( ! function_exists( 'ucsc_events_remote_get_json' ) ) {
	function ucsc_events_remote_get_json( $url ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Invalid URL' );
		}

		$response = wp_remote_get( $url, array(
			'timeout'   => 8,
			'headers'   => array(
				'User-Agent' => 'UCSC Events Block/1.0',
				'Accept'     => 'application/json',
			),
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'bad_status', 'Unexpected response code' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON response' );
		}

		return $data;
	}
}

/**
 * AJAX: organizer autocomplete for the editor, proxied server-side.
 *
 * Like the preview, these lookups avoid a direct cross-origin fetch so they are
 * not affected by the events API CDN's origin-agnostic CORS caching.
 */
function ucsc_events_search_organizers() {
	if ( ! ucsc_events_verify_editor_request() ) {
		wp_send_json_error( array( 'organizers' => array() ) );
		return;
	}

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( strlen( $search ) < 2 ) {
		wp_send_json_success( array( 'organizers' => array() ) );
		return;
	}

	$endpoints = ucsc_events_get_api_endpoints();
	$url       = add_query_arg(
		array(
			'search'   => $search,
			'per_page' => 20,
		),
		$endpoints['organizers']
	);

	$data = ucsc_events_remote_get_json( $url );
	if ( is_wp_error( $data ) || empty( $data['organizers'] ) || ! is_array( $data['organizers'] ) ) {
		wp_send_json_success( array( 'organizers' => array() ) );
		return;
	}

	// Normalize to { id, name } and drop duplicate names. Names are sanitized as
	// external text; the editor decodes HTML entities for display.
	$organizers = array();
	$seen       = array();
	foreach ( $data['organizers'] as $item ) {
		$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$name = isset( $item['organizer'] ) ? sanitize_text_field( $item['organizer'] ) : '';
		if ( ! $id || '' === $name || isset( $seen[ $name ] ) ) {
			continue;
		}
		$seen[ $name ] = true;
		$organizers[]  = array(
			'id'   => $id,
			'name' => $name,
		);
	}

	wp_send_json_success( array( 'organizers' => $organizers ) );
}
add_action( 'wp_ajax_ucsc_events_search_organizers', 'ucsc_events_search_organizers' );

/**
 * AJAX: list available event categories for the editor, proxied server-side.
 */
function ucsc_events_get_categories() {
	if ( ! ucsc_events_verify_editor_request() ) {
		wp_send_json_error( array( 'categories' => array() ) );
		return;
	}

	$url  = add_query_arg( array( 'per_page' => 100 ), ucsc_events_get_taxonomy_endpoint( 'categories' ) );
	$data = ucsc_events_remote_get_json( $url );
	if ( is_wp_error( $data ) || empty( $data['categories'] ) || ! is_array( $data['categories'] ) ) {
		wp_send_json_success( array( 'categories' => array() ) );
		return;
	}

	$categories = array();
	foreach ( $data['categories'] as $item ) {
		$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
		if ( '' === $slug ) {
			continue;
		}
		$categories[] = array(
			'name' => isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : $slug,
			'slug' => $slug,
		);
	}

	wp_send_json_success( array( 'categories' => $categories ) );
}
add_action( 'wp_ajax_ucsc_events_get_categories', 'ucsc_events_get_categories' );

/**
 * AJAX: tag autocomplete for the editor, proxied server-side.
 */
function ucsc_events_search_tags() {
	if ( ! ucsc_events_verify_editor_request() ) {
		wp_send_json_error( array( 'tags' => array() ) );
		return;
	}

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( strlen( $search ) < 2 ) {
		wp_send_json_success( array( 'tags' => array() ) );
		return;
	}

	$url  = add_query_arg(
		array(
			'search'   => $search,
			'per_page' => 10,
		),
		ucsc_events_get_taxonomy_endpoint( 'tags' )
	);

	$data = ucsc_events_remote_get_json( $url );
	if ( is_wp_error( $data ) || empty( $data['tags'] ) || ! is_array( $data['tags'] ) ) {
		wp_send_json_success( array( 'tags' => array() ) );
		return;
	}

	$tags = array();
	foreach ( $data['tags'] as $item ) {
		$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
		if ( '' !== $slug ) {
			$tags[] = $slug;
		}
	}

	wp_send_json_success( array( 'tags' => array_values( array_unique( $tags ) ) ) );
}
add_action( 'wp_ajax_ucsc_events_search_tags', 'ucsc_events_search_tags' );

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
