<?php
/**
 * Server-side functions for the ICS Calendar block.
 *
 * Handles ICS feed fetching, parsing VEVENT data, caching with transients,
 * and AJAX cache-clearing.
 *
 * @package UcscBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parse an ICS feed string into an array of events.
 *
 * Lightweight parser that extracts VEVENT components and their properties.
 * Handles folded lines (RFC 5545 §3.1), DTSTART/DTEND with and without
 * TZID parameters, and common text fields.
 *
 * @param string $ics_content Raw ICS file content.
 * @return array Array of associative arrays with event data.
 */
function ucsc_ics_parse( $ics_content ) {
    if ( empty( $ics_content ) ) {
        return array();
    }

    // Unfold lines per RFC 5545 §3.1 — a CRLF followed by a single
    // whitespace character is a line continuation.
    $ics_content = str_replace( "\r\n", "\n", $ics_content );
    $ics_content = preg_replace( '/\n[ \t]/', '', $ics_content );

    $lines  = explode( "\n", $ics_content );
    $events = array();
    $event  = null;

    foreach ( $lines as $line ) {
        $line = trim( $line );

        if ( $line === 'BEGIN:VEVENT' ) {
            $event = array(
                'summary'     => '',
                'dtstart'     => '',
                'dtend'       => '',
                'location'    => '',
                'description' => '',
                'uid'         => '',
                'url'         => '',
            );
            continue;
        }

        if ( $line === 'END:VEVENT' && $event !== null ) {
            $events[] = $event;
            $event    = null;
            continue;
        }

        if ( $event === null ) {
            continue;
        }

        // Split on the first colon that is not inside a parameter value.
        // Property lines look like: PROPNAME;PARAM=VAL:value
        // We need to handle parameters like DTSTART;TZID=America/Los_Angeles:20260301T090000
        $colon_pos = strpos( $line, ':' );
        if ( $colon_pos === false ) {
            continue;
        }

        $prop_part  = substr( $line, 0, $colon_pos );
        $value_part = substr( $line, $colon_pos + 1 );

        // The property name is everything before the first semicolon (parameters).
        $semi_pos  = strpos( $prop_part, ';' );
        $prop_name = ( $semi_pos !== false ) ? substr( $prop_part, 0, $semi_pos ) : $prop_part;
        $prop_name = strtoupper( $prop_name );

        // Unescape ICS text values.
        $value_part = str_replace(
            array( '\\n', '\\N', '\\,', '\\;', '\\\\' ),
            array( "\n",  "\n",  ',',   ';',   '\\'    ),
            $value_part
        );

        switch ( $prop_name ) {
            case 'SUMMARY':
                $event['summary'] = $value_part;
                break;
            case 'DTSTART':
                $event['dtstart'] = ucsc_ics_parse_datetime( $value_part );
                break;
            case 'DTEND':
                $event['dtend'] = ucsc_ics_parse_datetime( $value_part );
                break;
            case 'LOCATION':
                $event['location'] = $value_part;
                break;
            case 'DESCRIPTION':
                $event['description'] = $value_part;
                break;
            case 'UID':
                $event['uid'] = $value_part;
                break;
            case 'URL':
                $event['url'] = $value_part;
                break;
        }
    }

    return $events;
}

/**
 * Parse an ICS datetime string into a Unix timestamp.
 *
 * Supports formats:
 *  - 20260301T090000Z  (UTC)
 *  - 20260301T090000   (floating / local)
 *  - 20260301          (all-day)
 *
 * @param string $dt ICS datetime value.
 * @return int Unix timestamp, or 0 on failure.
 */
function ucsc_ics_parse_datetime( $dt ) {
    $dt = trim( $dt );

    // All-day date: YYYYMMDD
    if ( preg_match( '/^\d{8}$/', $dt ) ) {
        $parsed = DateTime::createFromFormat( 'Ymd', $dt );
        if ( $parsed ) {
            $parsed->setTime( 0, 0, 0 );
            return $parsed->getTimestamp();
        }
    }

    // Date-time with UTC indicator
    if ( preg_match( '/^\d{8}T\d{6}Z$/', $dt ) ) {
        $parsed = DateTime::createFromFormat( 'Ymd\THis\Z', $dt, new DateTimeZone( 'UTC' ) );
        if ( $parsed ) {
            return $parsed->getTimestamp();
        }
    }

    // Date-time without timezone (treat as site timezone)
    if ( preg_match( '/^\d{8}T\d{6}$/', $dt ) ) {
        $tz     = wp_timezone();
        $parsed = DateTime::createFromFormat( 'Ymd\THis', $dt, $tz );
        if ( $parsed ) {
            return $parsed->getTimestamp();
        }
    }

    // No recognised format — return 0 rather than accepting arbitrary input.
    return 0;
}

/**
 * Validate that a feed URL is safe to fetch.
 *
 * Rejects non-HTTPS schemes and private/reserved IP ranges to
 * prevent SSRF attacks against internal infrastructure.
 *
 * @param string $url The URL to validate.
 * @return bool True if the URL is safe, false otherwise.
 */
function ucsc_ics_validate_feed_url( $url ) {
    // Must be a valid URL.
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }

    // Only allow HTTPS (block file://, ftp://, http://, gopher://, etc.).
    $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
    if ( strtolower( $scheme ) !== 'https' ) {
        return false;
    }

    // Resolve hostname and reject private/reserved IPs (SSRF protection).
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( empty( $host ) ) {
        return false;
    }

    // Block obvious internal hostnames.
    $blocked_hosts = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
    if ( in_array( strtolower( $host ), $blocked_hosts, true ) ) {
        return false;
    }

    // Resolve DNS and reject private/reserved IP ranges.
    $ip = gethostbyname( $host );
    if ( $ip === $host ) {
        // DNS resolution failed.
        return false;
    }

    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return false;
    }

    return true;
}

/**
 * Maximum allowed ICS response body size in bytes (2 MB).
 */
define( 'UCSC_ICS_MAX_BODY_SIZE', 2 * 1024 * 1024 );

/**
 * Fetch and parse events from an ICS feed URL.
 *
 * Results are cached using WordPress transients for 15 minutes.
 * Only future events (starting from now) are returned, sorted by start date.
 *
 * @param string $feed_url The ICS feed URL.
 * @param int    $count    Maximum number of events to return.
 * @return array Array of processed event arrays.
 */
function ucsc_ics_fetch_events( $feed_url, $count = 5 ) {
    if ( empty( $feed_url ) ) {
        return array();
    }

    // Clamp count to a sane range.
    $count = max( 1, min( 20, intval( $count ) ) );

    // Cache key
    $cache_key  = 'ucsc_ics_' . md5( $feed_url . $count );
    $cached     = get_transient( $cache_key );

    if ( false !== $cached ) {
        return $cached;
    }

    // Validate URL — scheme, host, and SSRF checks.
    if ( ! ucsc_ics_validate_feed_url( $feed_url ) ) {
        error_log( 'UCSC ICS Calendar Error: URL failed validation — ' . $feed_url );
        return array();
    }

    // Fetch the ICS feed
    $response = wp_remote_get( $feed_url, array(
        'timeout'   => 10,
        'headers'   => array(
            'User-Agent' => 'UCSC ICS Calendar Block/1.0',
            'Accept'     => 'text/calendar, text/plain, */*',
        ),
        'sslverify' => true,
        // WordPress doesn't natively enforce a body size limit on
        // wp_remote_get, but we check immediately after retrieval.
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'UCSC ICS Calendar Error: ' . $response->get_error_message() );
        return array();
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 200 ) {
        error_log( 'UCSC ICS Calendar Error: HTTP ' . $response_code );
        return array();
    }

    $body = wp_remote_retrieve_body( $response );

    // Enforce a maximum body size to prevent memory exhaustion.
    if ( strlen( $body ) > UCSC_ICS_MAX_BODY_SIZE ) {
        error_log( 'UCSC ICS Calendar Error: Response body exceeds ' . UCSC_ICS_MAX_BODY_SIZE . ' bytes' );
        return array();
    }

    // Basic sanity check — must contain VCALENDAR
    if ( strpos( $body, 'BEGIN:VCALENDAR' ) === false ) {
        error_log( 'UCSC ICS Calendar Error: Response does not appear to be a valid ICS feed' );
        return array();
    }

    // Parse events
    $raw_events = ucsc_ics_parse( $body );

    if ( empty( $raw_events ) ) {
        return array();
    }

    $now    = time();
    $events = array();

    foreach ( $raw_events as $raw ) {
        $start = $raw['dtstart'];
        $end   = $raw['dtend'];

        // Skip events that have already ended (or started in the past with no end)
        if ( $end && $end < $now ) {
            continue;
        }
        if ( ! $end && $start && $start < $now ) {
            continue;
        }

        // Format date for display
        $date_display = '';
        if ( $start ) {
            $date_format = get_option( 'date_format' );
            $time_format = get_option( 'time_format' );

            // Check if this is an all-day event (time is midnight and no end or end is also midnight)
            $start_dt  = new DateTime( '@' . $start );
            $is_allday = ( $start_dt->format( 'H:i:s' ) === '00:00:00' );

            if ( $is_allday ) {
                $date_display = date_i18n( $date_format, $start );
            } else {
                $date_display = date_i18n( $date_format . ' ' . $time_format, $start );
            }
        }

        // Sanitize text fields from the ICS feed.
        $title    = ! empty( $raw['summary'] ) ? sanitize_text_field( $raw['summary'] ) : __( 'Untitled Event', 'ucsc-blocks' );
        $location = sanitize_text_field( $raw['location'] );
        $desc     = wp_trim_words( wp_strip_all_tags( $raw['description'] ), 30, '&hellip;' );

        // Only allow http/https URLs from the ICS URL field.
        $event_url = '';
        if ( ! empty( $raw['url'] ) ) {
            $raw_url = esc_url_raw( $raw['url'], array( 'https', 'http' ) );
            if ( ! empty( $raw_url ) ) {
                $event_url = $raw_url;
            }
        }

        $events[] = array(
            'title'       => $title,
            'date'        => $date_display,
            'start'       => $start,
            'location'    => $location,
            'description' => $desc,
            'url'         => $event_url,
        );
    }

    // Sort by start date ascending
    usort( $events, function ( $a, $b ) {
        return $a['start'] - $b['start'];
    } );

    // Limit to requested count
    $events = array_slice( $events, 0, $count );

    // Remove internal 'start' timestamp before caching
    $events = array_map( function ( $e ) {
        unset( $e['start'] );
        return $e;
    }, $events );

    // Cache for 15 minutes
    set_transient( $cache_key, $events, 30 * MINUTE_IN_SECONDS );

    return $events;
}

/**
 * AJAX handler: clear ICS calendar cache.
 */
function ucsc_ics_calendar_clear_cache() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ucsc_ics_calendar_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
        return;
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        return;
    }

    $feed_url = isset( $_POST['feed_url'] ) ? sanitize_url( $_POST['feed_url'] ) : '';

    if ( ! empty( $feed_url ) ) {
        // Clear cache for all possible item counts (max 20).
        for ( $i = 1; $i <= 20; $i++ ) {
            $cache_key = 'ucsc_ics_' . md5( $feed_url . $i );
            delete_transient( $cache_key );
        }

        wp_send_json_success( array( 'message' => 'Cache cleared successfully' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid feed URL' ) );
    }
}
add_action( 'wp_ajax_ucsc_ics_calendar_clear_cache', 'ucsc_ics_calendar_clear_cache' );

/**
 * AJAX handler: return parsed ICS events as JSON for the editor preview.
 */
function ucsc_ics_calendar_preview() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ucsc_ics_calendar_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
        return;
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        return;
    }

    $feed_url   = isset( $_POST['feed_url'] ) ? sanitize_url( $_POST['feed_url'] ) : '';
    $item_count = isset( $_POST['item_count'] ) ? absint( $_POST['item_count'] ) : 5;
    $item_count = max( 1, min( 20, $item_count ) );

    if ( empty( $feed_url ) ) {
        wp_send_json_error( array( 'message' => 'No feed URL provided' ) );
        return;
    }

    if ( ! ucsc_ics_validate_feed_url( $feed_url ) ) {
        wp_send_json_error( array( 'message' => 'Invalid or disallowed feed URL. Only public HTTPS URLs are accepted.' ) );
        return;
    }

    $events = ucsc_ics_fetch_events( $feed_url, $item_count );
    wp_send_json_success( $events );
}
add_action( 'wp_ajax_ucsc_ics_calendar_preview', 'ucsc_ics_calendar_preview' );
