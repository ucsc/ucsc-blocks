/**
 * ICS/iCal parser — shared module.
 *
 * Mirrors the PHP implementation in ics-calendar.php so the editor
 * preview can parse an ICS feed client-side when needed, and so the
 * logic can be tested with Jest.
 *
 * @package UcscBlocks
 */

/**
 * Parse an ICS datetime string into a Unix timestamp (seconds).
 *
 * Recognised formats:
 *  - 20260301T090000Z   (UTC)
 *  - 20260301T090000    (floating / local — treated as UTC here)
 *  - 20260301           (all-day)
 *
 * @param {string} dt Raw ICS datetime value.
 * @return {number} Unix timestamp in seconds, or 0 on failure.
 */
export function icsParseDateTime( dt ) {
	dt = dt.trim();

	// All-day: YYYYMMDD
	if ( /^\d{8}$/.test( dt ) ) {
		const y = dt.slice( 0, 4 );
		const m = dt.slice( 4, 6 );
		const d = dt.slice( 6, 8 );
		const ts = Date.UTC(
			Number( y ),
			Number( m ) - 1,
			Number( d )
		);
		return ts / 1000;
	}

	// UTC: YYYYMMDDTHHMMSSz
	if ( /^\d{8}T\d{6}Z$/.test( dt ) ) {
		const y = dt.slice( 0, 4 );
		const m = dt.slice( 4, 6 );
		const d = dt.slice( 6, 8 );
		const H = dt.slice( 9, 11 );
		const M = dt.slice( 11, 13 );
		const S = dt.slice( 13, 15 );
		const ts = Date.UTC(
			Number( y ),
			Number( m ) - 1,
			Number( d ),
			Number( H ),
			Number( M ),
			Number( S )
		);
		return ts / 1000;
	}

	// Floating (no TZ): YYYYMMDDTHHMMSS — treat as UTC for consistency.
	if ( /^\d{8}T\d{6}$/.test( dt ) ) {
		const y = dt.slice( 0, 4 );
		const m = dt.slice( 4, 6 );
		const d = dt.slice( 6, 8 );
		const H = dt.slice( 9, 11 );
		const M = dt.slice( 11, 13 );
		const S = dt.slice( 13, 15 );
		const ts = Date.UTC(
			Number( y ),
			Number( m ) - 1,
			Number( d ),
			Number( H ),
			Number( M ),
			Number( S )
		);
		return ts / 1000;
	}

	// Unrecognised format.
	return 0;
}

/**
 * Parse raw ICS content into an array of event objects.
 *
 * Handles:
 *  - RFC 5545 §3.1 line folding (CRLF + whitespace continuation)
 *  - ICS text escaping (\n, \,, \;, \\)
 *  - DTSTART/DTEND with or without TZID parameters
 *  - All-day dates (VALUE=DATE)
 *
 * @param {string} content Raw ICS file content.
 * @return {Array<Object>} Parsed VEVENT objects.
 */
export function icsParse( content ) {
	if ( ! content ) {
		return [];
	}

	// Normalise line endings and unfold continuation lines.
	content = content.replace( /\r\n/g, '\n' );
	content = content.replace( /\n[ \t]/g, '' );

	const lines = content.split( '\n' );
	const events = [];
	let event = null;

	for ( const rawLine of lines ) {
		const line = rawLine.trim();

		if ( line === 'BEGIN:VEVENT' ) {
			event = {
				summary: '',
				dtstart: 0,
				dtend: 0,
				location: '',
				description: '',
				uid: '',
				url: '',
			};
			continue;
		}

		if ( line === 'END:VEVENT' && event !== null ) {
			events.push( event );
			event = null;
			continue;
		}

		if ( event === null ) {
			continue;
		}

		const colonPos = line.indexOf( ':' );
		if ( colonPos === -1 ) {
			continue;
		}

		const propPart = line.slice( 0, colonPos );
		let valuePart = line.slice( colonPos + 1 );

		// Extract property name (before any ;PARAM=VAL).
		const semiPos = propPart.indexOf( ';' );
		const propName = (
			semiPos !== -1 ? propPart.slice( 0, semiPos ) : propPart
		).toUpperCase();

		// Unescape ICS text values.
		valuePart = valuePart
			.replace( /\\n/gi, '\n' )
			.replace( /\\,/g, ',' )
			.replace( /\\;/g, ';' )
			.replace( /\\\\/g, '\\' );

		switch ( propName ) {
			case 'SUMMARY':
				event.summary = valuePart;
				break;
			case 'DTSTART':
				event.dtstart = icsParseDateTime( valuePart );
				break;
			case 'DTEND':
				event.dtend = icsParseDateTime( valuePart );
				break;
			case 'LOCATION':
				event.location = valuePart;
				break;
			case 'DESCRIPTION':
				event.description = valuePart;
				break;
			case 'UID':
				event.uid = valuePart;
				break;
			case 'URL':
				event.url = valuePart;
				break;
		}
	}

	return events;
}

/**
 * Check whether a location string looks like a URL.
 *
 * @param {string} location The location value from an ICS event.
 * @return {boolean} True if the location is an http(s) URL.
 */
export function isLocationUrl( location ) {
	return /^https?:\/\//i.test( location );
}

/**
 * Extract the hostname from a URL string.
 *
 * @param {string} url A valid URL.
 * @return {string} The hostname, or the original string on failure.
 */
export function getHostFromUrl( url ) {
	try {
		return new URL( url ).hostname;
	} catch {
		return url;
	}
}
