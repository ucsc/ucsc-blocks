<?php
/**
 * PHPUnit bootstrap file.
 *
 * For full WordPress integration tests, set WP_TESTS_DIR to point at
 * a wordpress-develop checkout's tests/phpunit directory.  For unit
 * tests that only need the plugin's own functions, this lightweight
 * bootstrap provides the minimum WordPress stubs.
 *
 * @package UcscBlocks
 */

// If a real WordPress test suite is available, use it.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
    // Point the test suite at our plugin.
    $GLOBALS['wp_tests_options'] = array(
        'active_plugins' => array( 'ucsc-blocks/ucsc-blocks.php' ),
    );

    require_once $wp_tests_dir . '/includes/functions.php';

    /**
     * Load the plugin under test.
     */
    function _manually_load_plugin() {
        require dirname( __DIR__ ) . '/ucsc-blocks.php';
    }
    tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

    require $wp_tests_dir . '/includes/bootstrap.php';

} else {
    /*
     * Lightweight standalone mode: define the bare-minimum WordPress
     * constants and function stubs so ics-calendar.php can be loaded
     * without the full WordPress stack.  Good for CI or quick local
     * runs where you don't have wp-phpunit set up.
     */

    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', '/stub/' );
    }
    if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
        define( 'MINUTE_IN_SECONDS', 60 );
    }
    if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
        define( 'HOUR_IN_SECONDS', 3600 );
    }

    // ── Transient stubs ──────────────────────────────────────────
    $GLOBALS['_stub_transients'] = [];

    if ( ! function_exists( 'get_transient' ) ) {
        function get_transient( $key ) {
            return $GLOBALS['_stub_transients'][ $key ] ?? false;
        }
    }
    if ( ! function_exists( 'set_transient' ) ) {
        function set_transient( $key, $value, $expiration = 0 ) {
            $GLOBALS['_stub_transients'][ $key ] = $value;
            return true;
        }
    }
    if ( ! function_exists( 'delete_transient' ) ) {
        function delete_transient( $key ) {
            unset( $GLOBALS['_stub_transients'][ $key ] );
            return true;
        }
    }

    // ── WordPress function stubs ─────────────────────────────────
    if ( ! function_exists( 'wp_timezone' ) ) {
        function wp_timezone() {
            return new DateTimeZone( 'America/Los_Angeles' );
        }
    }
    if ( ! function_exists( 'get_option' ) ) {
        function get_option( $key ) {
            $defaults = [
                'date_format' => 'F j, Y',
                'time_format' => 'g:i a',
            ];
            return $defaults[ $key ] ?? '';
        }
    }
    if ( ! function_exists( 'sanitize_text_field' ) ) {
        function sanitize_text_field( $str ) {
            return trim( strip_tags( $str ) );
        }
    }
    if ( ! function_exists( 'wp_strip_all_tags' ) ) {
        function wp_strip_all_tags( $str ) {
            return strip_tags( $str );
        }
    }
    if ( ! function_exists( 'wp_trim_words' ) ) {
        function wp_trim_words( $text, $num_words = 55, $more = '&hellip;' ) {
            $words = preg_split( '/\s+/', trim( $text ) );
            if ( count( $words ) <= $num_words ) return $text;
            return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
        }
    }
    if ( ! function_exists( 'esc_url_raw' ) ) {
        function esc_url_raw( $url, $protocols = null ) {
            if ( $protocols && ! preg_match( '#^(https?|http)://#i', $url ) ) return '';
            return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
        }
    }
    if ( ! function_exists( 'date_i18n' ) ) {
        function date_i18n( $fmt, $ts ) {
            return date( $fmt, $ts );
        }
    }
    if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = '' ) {
            return $text;
        }
    }

    // Load the ICS calendar PHP code.
    require_once dirname( __DIR__ ) . '/src/blocks/ics-calendar/ics-calendar.php';
}
