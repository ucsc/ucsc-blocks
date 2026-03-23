/**
 * Jest tests for the ICS calendar parser.
 *
 * Run:  npm test
 */

import { readFileSync } from 'fs';
import { join } from 'path';
import {
	icsParse,
	icsParseDateTime,
	isLocationUrl,
	getHostFromUrl,
} from './parser';

// Load the shared fixture once.
const FIXTURE_PATH = join(
	__dirname,
	'../../../tests/fixtures/sample-calendar.ics'
);
const ICS_CONTENT = readFileSync( FIXTURE_PATH, 'utf8' );

// ─────────────────────────────────────────────────────────────────────
// icsParseDateTime
// ─────────────────────────────────────────────────────────────────────
describe( 'icsParseDateTime', () => {
	it( 'parses a UTC datetime (YYYYMMDDTHHMMSSz)', () => {
		const ts = icsParseDateTime( '20260401T090000Z' );
		const d = new Date( ts * 1000 );
		expect( d.toISOString() ).toBe( '2026-04-01T09:00:00.000Z' );
	} );

	it( 'parses a floating datetime (YYYYMMDDTHHMMSS)', () => {
		const ts = icsParseDateTime( '20260405T140000' );
		expect( ts ).toBeGreaterThan( 0 );
		const d = new Date( ts * 1000 );
		expect( d.getUTCHours() ).toBe( 14 );
	} );

	it( 'parses an all-day date (YYYYMMDD)', () => {
		const ts = icsParseDateTime( '20260410' );
		expect( ts ).toBeGreaterThan( 0 );
		const d = new Date( ts * 1000 );
		expect( d.getUTCHours() ).toBe( 0 );
		expect( d.getUTCMinutes() ).toBe( 0 );
	} );

	it( 'returns 0 for unrecognised formats', () => {
		expect( icsParseDateTime( 'not-a-date' ) ).toBe( 0 );
		expect( icsParseDateTime( '' ) ).toBe( 0 );
		expect( icsParseDateTime( '2026-04-01' ) ).toBe( 0 );
	} );
} );

// ─────────────────────────────────────────────────────────────────────
// icsParse — fixture-based integration tests
// ─────────────────────────────────────────────────────────────────────
describe( 'icsParse', () => {
	let events;

	beforeAll( () => {
		events = icsParse( ICS_CONTENT );
	} );

	it( 'extracts all 13 VEVENT blocks', () => {
		expect( events ).toHaveLength( 13 );
	} );

	it( 'parses the first event summary', () => {
		expect( events[ 0 ].summary ).toBe( 'Spring Quarter Orientation' );
	} );

	it( 'parses the last event (Zoom dissertation defense)', () => {
		expect( events[ 12 ].summary ).toBe(
			'Dissertation Defense: Maria Chen — Coastal Erosion Modeling'
		);
	} );

	// RFC 5545 line folding
	it( 'unfolds continuation lines in DESCRIPTION', () => {
		const evt = events[ 1 ]; // Faculty Research Symposium
		expect( evt.description ).toContain( 'Philosophy' );
		expect( evt.description ).not.toContain( '\n ' ); // no raw fold
	} );

	// ICS text escaping
	it( 'unescapes \\, to a literal comma', () => {
		expect( events[ 0 ].location ).toContain( ',' );
		expect( events[ 0 ].location ).toBe(
			'Stevenson Event Center, UC Santa Cruz'
		);
	} );

	it( 'unescapes \\; to a literal semicolon', () => {
		const desc = events[ 3 ].description; // Concert
		expect( desc ).toContain( ';' );
		expect( desc ).not.toContain( '\\;' );
	} );

	it( 'converts \\n to a real newline', () => {
		const desc = events[ 0 ].description;
		expect( desc ).toContain( '\n' );
	} );

	// Datetime parsing through the full pipeline
	it( 'parses UTC DTSTART to a valid timestamp', () => {
		expect( events[ 0 ].dtstart ).toBeGreaterThan( 0 );
		const d = new Date( events[ 0 ].dtstart * 1000 );
		expect( d.toISOString() ).toMatch( /^2026-04-01T09:00/ );
	} );

	it( 'parses TZID DTSTART to a valid timestamp', () => {
		expect( events[ 1 ].dtstart ).toBeGreaterThan( 0 );
	} );

	it( 'parses all-day VALUE=DATE to midnight', () => {
		const d = new Date( events[ 2 ].dtstart * 1000 );
		expect( d.getUTCHours() ).toBe( 0 );
		expect( d.getUTCMinutes() ).toBe( 0 );
	} );

	// Edge cases
	it( 'handles an empty SUMMARY gracefully', () => {
		const evt = events[ 10 ]; // evt-011 — empty summary
		expect( evt.summary ).toBe( '' );
	} );

	it( 'handles a missing DTEND', () => {
		const evt = events[ 11 ]; // evt-012 — no DTEND
		expect( evt.dtend ).toBe( 0 );
		expect( evt.dtstart ).toBeGreaterThan( 0 );
	} );

	it( 'parses a Zoom URL in LOCATION', () => {
		const evt = events[ 12 ]; // evt-013
		expect( evt.location ).toMatch( /^https:\/\/ucsc\.zoom\.us/ );
	} );

	// Unicode
	it( 'preserves Unicode characters (emoji, accented chars)', () => {
		const evt = events[ 9 ]; // evt-010 — Café ☕
		expect( evt.summary ).toContain( '☕' );
		expect( evt.summary ).toContain( 'Café' );
	} );

	// Past event is still parsed (filtering is a separate concern)
	it( 'parses past events (filtering is done separately)', () => {
		const past = events[ 8 ]; // evt-009
		expect( past.summary ).toBe(
			'Past Event — Should Be Filtered Out'
		);
		expect( past.dtstart ).toBeGreaterThan( 0 );
	} );

	// Empty / null input
	it( 'returns an empty array for empty input', () => {
		expect( icsParse( '' ) ).toEqual( [] );
		expect( icsParse( null ) ).toEqual( [] );
		expect( icsParse( undefined ) ).toEqual( [] );
	} );

	it( 'returns an empty array for content with no VEVENTs', () => {
		const noEvents =
			'BEGIN:VCALENDAR\nVERSION:2.0\nEND:VCALENDAR';
		expect( icsParse( noEvents ) ).toEqual( [] );
	} );
} );

// ─────────────────────────────────────────────────────────────────────
// isLocationUrl / getHostFromUrl
// ─────────────────────────────────────────────────────────────────────
describe( 'isLocationUrl', () => {
	it( 'returns true for https URLs', () => {
		expect(
			isLocationUrl( 'https://ucsc.zoom.us/j/123?pwd=abc' )
		).toBe( true );
	} );

	it( 'returns true for http URLs', () => {
		expect( isLocationUrl( 'http://example.com' ) ).toBe( true );
	} );

	it( 'returns false for plain-text locations', () => {
		expect( isLocationUrl( 'Stevenson Event Center' ) ).toBe( false );
		expect( isLocationUrl( 'Room 206' ) ).toBe( false );
		expect( isLocationUrl( '' ) ).toBe( false );
	} );

	it( 'returns false for non-http schemes', () => {
		expect( isLocationUrl( 'ftp://files.example.com' ) ).toBe( false );
		expect( isLocationUrl( 'javascript:alert(1)' ) ).toBe( false );
	} );
} );

describe( 'getHostFromUrl', () => {
	it( 'extracts hostname from a Zoom URL', () => {
		expect(
			getHostFromUrl(
				'https://ucsc.zoom.us/j/98765432100?pwd=xYz'
			)
		).toBe( 'ucsc.zoom.us' );
	} );

	it( 'extracts hostname from a simple URL', () => {
		expect( getHostFromUrl( 'https://example.com/path' ) ).toBe(
			'example.com'
		);
	} );

	it( 'returns the original string if URL is invalid', () => {
		expect( getHostFromUrl( 'not a url' ) ).toBe( 'not a url' );
	} );
} );
