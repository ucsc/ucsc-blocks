<?php
/**
 * PHPUnit tests for the ICS calendar parser.
 *
 * Run:
 *   ./vendor/bin/phpunit                       (with bootstrap stubs)
 *   WP_TESTS_DIR=/path/to/wp-tests ./vendor/bin/phpunit  (full WP)
 *
 * @package UcscBlocks
 */

use PHPUnit\Framework\TestCase;

class IcsParserTest extends TestCase {

    /**
     * Path to the sample ICS fixture.
     */
    private static string $fixture_path;

    /**
     * Raw ICS content loaded once for all tests.
     */
    private static string $ics;

    public static function setUpBeforeClass(): void {
        self::$fixture_path = dirname( __DIR__ ) . '/fixtures/sample-calendar.ics';
        self::$ics = file_get_contents( self::$fixture_path );
    }

    // ─── ucsc_ics_parse_datetime ────────────────────────────────────

    public function test_parse_utc_datetime(): void {
        $ts = ucsc_ics_parse_datetime( '20260401T090000Z' );
        $this->assertGreaterThan( 0, $ts );
        $dt = new DateTime( '@' . $ts );
        $dt->setTimezone( new DateTimeZone( 'UTC' ) );
        $this->assertEquals( '2026-04-01 09:00:00', $dt->format( 'Y-m-d H:i:s' ) );
    }

    public function test_parse_floating_datetime(): void {
        $ts = ucsc_ics_parse_datetime( '20260405T140000' );
        $this->assertGreaterThan( 0, $ts );
    }

    public function test_parse_allday_date(): void {
        $ts = ucsc_ics_parse_datetime( '20260410' );
        $this->assertGreaterThan( 0, $ts );
        $dt = new DateTime( '@' . $ts );
        $this->assertEquals( '00:00:00', $dt->format( 'H:i:s' ) );
    }

    public function test_rejects_invalid_format(): void {
        $this->assertEquals( 0, ucsc_ics_parse_datetime( 'not-a-date' ) );
        $this->assertEquals( 0, ucsc_ics_parse_datetime( '' ) );
        $this->assertEquals( 0, ucsc_ics_parse_datetime( '2026-04-01' ) );
    }

    // ─── ucsc_ics_parse ─────────────────────────────────────────────

    public function test_extracts_all_vevent_blocks(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertCount( 13, $events );
    }

    public function test_first_event_summary(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertEquals( 'Spring Quarter Orientation', $events[0]['summary'] );
    }

    public function test_last_event_is_zoom_defense(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertEquals(
            'Dissertation Defense: Maria Chen — Coastal Erosion Modeling',
            $events[12]['summary']
        );
    }

    public function test_unfolds_continuation_lines(): void {
        $events = ucsc_ics_parse( self::$ics );
        $desc = $events[1]['description']; // Faculty Research Symposium
        $this->assertStringContainsString( 'Philosophy', $desc );
    }

    public function test_unescapes_comma(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertEquals(
            'Stevenson Event Center, UC Santa Cruz',
            $events[0]['location']
        );
    }

    public function test_unescapes_semicolon(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertStringContainsString( ';', $events[3]['description'] );
        $this->assertStringNotContainsString( '\\;', $events[3]['description'] );
    }

    public function test_unescapes_newline(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertStringContainsString( "\n", $events[0]['description'] );
    }

    public function test_handles_empty_summary(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertEmpty( $events[10]['summary'] ); // evt-011
    }

    public function test_handles_missing_dtend(): void {
        $events = ucsc_ics_parse( self::$ics );
        $evt = $events[11]; // evt-012
        $this->assertEmpty( $evt['dtend'] );
        $this->assertGreaterThan( 0, $evt['dtstart'] );
    }

    public function test_zoom_url_in_location(): void {
        $events = ucsc_ics_parse( self::$ics );
        $this->assertStringStartsWith( 'https://ucsc.zoom.us', $events[12]['location'] );
    }

    public function test_preserves_unicode(): void {
        $events = ucsc_ics_parse( self::$ics );
        $evt = $events[9]; // evt-010
        $this->assertStringContainsString( '☕', $evt['summary'] );
        $this->assertStringContainsString( 'Café', $evt['summary'] );
    }

    public function test_empty_input_returns_empty_array(): void {
        $this->assertSame( [], ucsc_ics_parse( '' ) );
        $this->assertSame( [], ucsc_ics_parse( null ) );
    }

    public function test_no_vevents_returns_empty_array(): void {
        $ics = "BEGIN:VCALENDAR\nVERSION:2.0\nEND:VCALENDAR";
        $this->assertSame( [], ucsc_ics_parse( $ics ) );
    }

    // ─── ucsc_ics_validate_feed_url (if loaded with WP stubs) ───────

    public function test_validate_rejects_http(): void {
        if ( ! function_exists( 'ucsc_ics_validate_feed_url' ) ) {
            $this->markTestSkipped( 'ucsc_ics_validate_feed_url not loaded' );
        }
        $this->assertFalse( ucsc_ics_validate_feed_url( 'http://example.com/cal.ics' ) );
    }

    public function test_validate_rejects_localhost(): void {
        if ( ! function_exists( 'ucsc_ics_validate_feed_url' ) ) {
            $this->markTestSkipped( 'ucsc_ics_validate_feed_url not loaded' );
        }
        $this->assertFalse( ucsc_ics_validate_feed_url( 'https://localhost/cal.ics' ) );
    }

    public function test_validate_rejects_file_scheme(): void {
        if ( ! function_exists( 'ucsc_ics_validate_feed_url' ) ) {
            $this->markTestSkipped( 'ucsc_ics_validate_feed_url not loaded' );
        }
        $this->assertFalse( ucsc_ics_validate_feed_url( 'file:///etc/passwd' ) );
    }
}
