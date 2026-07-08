/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';

/**
 * WordPress dependencies
 */
import {
	PanelBody,
	FormTokenField,
	RangeControl,
	SelectControl,
	ToggleControl,
	Button,
	Notice,
	ToolbarGroup,
	ToolbarButton,
	Spinner
} from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object} props               Properties passed to the function.
 * @param {Object} props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {Element} Element to render.
 */
// Base UCSC Tribe Events endpoint. Used only to decide whether the block is
// configured (see buildEventsUrl); all data is fetched server-side via the
// same-origin AJAX proxies below, so the events API is never called from the
// browser (avoiding its cross-origin CDN/CORS caching issues).
const EVENTS_ENDPOINT =
	window.ucscEventsData?.eventsUrl ||
	'https://events.ucsc.edu/wp-json/tribe/events/v1/events';

export default function Edit( { attributes, setAttributes } ) {
	const { organizers = [], apiUrl, itemCount, layoutStyle, hideRepeating, categories, tags } = attributes;
	const [previewData, setPreviewData] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');
	const [cacheCleared, setCacheCleared] = useState(false);
	const [categoryOptions, setCategoryOptions] = useState([]);
	const [tagSuggestions, setTagSuggestions] = useState([]);
	const tagSearchTimeout = useRef();

	// Organizer autocomplete state.
	const [orgSearch, setOrgSearch] = useState('');
	const [orgSuggestions, setOrgSuggestions] = useState([]);

	const blockProps = useBlockProps({
		className: `layout-${layoutStyle}`
	});

	const layoutOptions = [
		{ label: __('List', 'ucsc-blocks'), value: 'list' },
		{ label: __('Grid', 'ucsc-blocks'), value: 'grid' },
		{ label: __('Cards', 'ucsc-blocks'), value: 'cards' }
	];

	// Convert a label into a slug, mirroring WordPress's sanitize_title() closely
	// enough for free-form tokens the user types that aren't in the suggestion list.
	const slugify = (value) =>
		String(value)
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');

	// POST to a same-origin admin-ajax action. Organizer/category/tag lookups and
	// the events preview are all proxied through the server so the browser never
	// makes a cross-origin request to the events API. Returns the handler's
	// `data` payload on success, or null on any failure (callers fall back to an
	// empty result).
	const ajaxLookup = async (action, params = {}, signal) => {
		const formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', window.ucscEventsData?.nonce || '');
		Object.entries(params).forEach(([key, value]) => formData.append(key, value));

		const response = await fetch(
			window.ucscEventsData?.ajaxUrl || '/wp-admin/admin-ajax.php',
			{ method: 'POST', body: formData, signal }
		);
		const result = await response.json();
		return result?.success ? result.data : null;
	};

	// Maps between category names (shown to editors) and slugs (stored/sent).
	const slugToCategoryName = {};
	const categoryNameToSlug = {};
	categoryOptions.forEach((option) => {
		slugToCategoryName[option.slug] = option.name;
		categoryNameToSlug[option.name] = option.slug;
	});

	// Load the available event categories once on mount (they don't depend on the
	// selected organizer).
	useEffect(() => {
		let cancelled = false;
		ajaxLookup('ucsc_events_get_categories')
			.then((data) => {
				if (cancelled || !data || !Array.isArray(data.categories)) return;
				setCategoryOptions(
					data.categories.map((category) => ({
						name: decodeEntities(category.name || ''),
						slug: category.slug
					}))
				);
			})
			.catch(() => {
				if (!cancelled) setCategoryOptions([]);
			});

		return () => {
			cancelled = true;
		};
	}, []);

	// Debounced tag search to feed the tag token field's suggestions.
	const searchTags = (input) => {
		clearTimeout(tagSearchTimeout.current);

		if (!input || input.length < 2) {
			setTagSuggestions([]);
			return;
		}

		tagSearchTimeout.current = setTimeout(() => {
			ajaxLookup('ucsc_events_search_tags', { search: input })
				.then((data) => {
					if (!data || !Array.isArray(data.tags)) return;
					setTagSuggestions(data.tags);
				})
				.catch(() => setTagSuggestions([]));
		}, 500);
	};

	/**
	 * Build the events API URL from the selected organizers.
	 *
	 * Mirrors the server-side `ucsc_events_build_api_url()`: filter by organizer
	 * when any are selected, fall back to a legacy hand-built URL, then to the
	 * base campus feed when category/tag filters are set, otherwise return an
	 * empty string so the block shows a placeholder until configured.
	 */
	const buildEventsUrl = () => {
		if (organizers.length > 0) {
			const url = new URL(EVENTS_ENDPOINT);
			organizers.forEach((org) => url.searchParams.append('organizer[]', org.id));
			return url.toString();
		}
		if (apiUrl) {
			return apiUrl;
		}
		// No organizer selected, but category/tag filters can still drive a
		// campus-wide fetch of the base events feed.
		if (categories.length || tags.length) {
			return EVENTS_ENDPOINT;
		}
		return '';
	};

	const effectiveUrl = buildEventsUrl();

	// Search organizers for the autocomplete field (debounced).
	useEffect(() => {
		const query = orgSearch.trim();
		if (query.length < 2) {
			setOrgSuggestions([]);
			return;
		}

		const controller = new AbortController();
		const timeoutId = setTimeout(async () => {
			try {
				const data = await ajaxLookup(
					'ucsc_events_search_organizers',
					{ search: query },
					controller.signal
				);
				const list = data && Array.isArray(data.organizers) ? data.organizers : [];

				// Drop duplicate display names so each suggestion is unambiguous in
				// the token field. Names may arrive HTML-encoded (e.g. "Men&#8217;s");
				// decode so tokens display the real characters.
				const seen = new Set();
				const mapped = [];
				list.forEach((item) => {
					const name = decodeEntities(item.name || '');
					if (!name || !item.id || seen.has(name)) return;
					seen.add(name);
					mapped.push({ id: item.id, name });
				});

				setOrgSuggestions(mapped);
			} catch {
				setOrgSuggestions([]);
			}
		}, 400);

		return () => {
			clearTimeout(timeoutId);
			controller.abort();
		};
	}, [orgSearch]);

	/**
	 * Resolve the token strings from FormTokenField back into { id, name }
	 * objects, preferring already-selected organizers, then current suggestions.
	 * Unknown free-text entries are ignored so only valid organizers persist.
	 */
	const handleOrganizersChange = (tokens) => {
		const next = tokens
			.map((name) => {
				const existing = organizers.find((org) => org.name === name);
				if (existing) return existing;
				return orgSuggestions.find((org) => org.name === name) || null;
			})
			.filter(Boolean);

		setAttributes({ organizers: next });
	};

	// Fetch preview data when the effective URL, categories, or tags change (debounced).
	// Always fetches 50 events; itemCount is applied locally when rendering.
	useEffect(() => {
		// Nothing configured yet — show the placeholder, don't fetch.
		if (!effectiveUrl) {
			setPreviewData([]);
			setError('');
			return;
		}

		// Debounce API calls so rapid organizer edits don't spam the API.
		const timeoutId = setTimeout(() => {
			fetchPreviewData();
		}, 1000);

		// Cleanup function to cancel the timeout if the URL changes again
		return () => clearTimeout(timeoutId);
	}, [effectiveUrl, categories.join(','), tags.join(',')]);

	// Load the preview through a same-origin AJAX proxy rather than fetching the
	// events API directly. The API sits behind a CDN that caches responses without
	// varying on Origin, so a direct cross-origin fetch intermittently hits a
	// cached copy with no CORS header and is blocked by the browser. The proxy
	// (ucsc_events_preview) fetches server-side and returns events already
	// processed into the render shape, sharing the frontend's cache so the preview
	// matches the published output.
	const fetchPreviewData = async () => {
		if (!effectiveUrl) return;

		setIsLoading(true);
		setError('');

		try {
			const formData = new FormData();
			formData.append('action', 'ucsc_events_preview');
			formData.append('organizers', JSON.stringify(organizers.map((org) => org.id)));
			formData.append('api_url', apiUrl || '');
			formData.append('categories', categories.join(','));
			formData.append('tags', tags.join(','));
			formData.append('nonce', window.ucscEventsData?.nonce || '');

			const response = await fetch(window.ucscEventsData?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
				signal: AbortSignal.timeout(10000)
			});

			const result = await response.json();

			if (!result.success) {
				throw new Error(result.data?.message || __('Failed to load the events preview.', 'ucsc-blocks'));
			}

			// The server returns events already in the render shape
			// ({ title, date, venue, featured_image, link, slug }).
			const data = Array.isArray(result.data?.events) ? result.data.events : [];
			setPreviewData(data);

		} catch (err) {
			if (err.name === 'AbortError' || err.name === 'TimeoutError') {
				setError(__('Request timeout. Please try again.', 'ucsc-blocks'));
			} else {
				setError(err.message);
			}
			setPreviewData([]);
		} finally {
			setIsLoading(false);
		}
	};

	const clearCache = async () => {
		if (!effectiveUrl) return;

		setIsLoading(true);

		try {
			const formData = new FormData();
			formData.append('action', 'ucsc_events_clear_cache');
			// Send organizer IDs (and any legacy URL) so the server rebuilds the
			// exact URL used as the cache key.
			formData.append('organizers', JSON.stringify(organizers.map((org) => org.id)));
			formData.append('api_url', apiUrl || '');
			formData.append('categories', categories.join(','));
			formData.append('tags', tags.join(','));
			formData.append('nonce', window.ucscEventsData?.nonce || '');

			const response = await fetch(window.ucscEventsData?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData
			});

			const result = await response.json();
			
			if (result.success) {
				setCacheCleared(true);
				setTimeout(() => setCacheCleared(false), 3000);
				// Refetch data after clearing cache
				fetchPreviewData();
			} else {
				throw new Error(result.data?.message || __('Failed to clear cache', 'ucsc-blocks'));
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setIsLoading(false);
		}
	};

	const stripHTMLTags = (html) => {
		const tmp = document.createElement('div');
		tmp.innerHTML = html;
		return tmp.textContent || tmp.innerText || '';
	};

	/**
	 * Filter out duplicate events based on slug.
	 * Since the API returns events sorted by date, the first occurrence
	 * of each slug is the nearest upcoming instance.
	 */
	const deduplicateEvents = (events) => {
		const seen = new Set();
		return events.filter((event) => {
			if (!event.slug) return true;
			if (seen.has(event.slug)) return false;
			seen.add(event.slug);
			return true;
		});
	};

	// Identify slugs that appear more than once (series/repeating events)
	const seriesSlugs = new Set();
	const slugCounts = {};
	previewData.forEach((event) => {
		if (event.slug) {
			slugCounts[event.slug] = (slugCounts[event.slug] || 0) + 1;
		}
	});
	Object.entries(slugCounts).forEach(([slug, count]) => {
		if (count > 1) seriesSlugs.add(slug);
	});

	const dedupedData = hideRepeating ? deduplicateEvents(previewData) : previewData;
	const displayData = dedupedData.slice(0, itemCount);

	const renderEventItem = (event, index) => (
		<div key={index} className="ucsc-event-item">
			{event.featured_image && (
				<div className="ucsc-event-image">
					<img 
						src={event.featured_image} 
						alt="" 
						onError={(e) => {
							e.target.style.display = 'none';
						}}
					/>
				</div>
			)}
			<div className="ucsc-event-content">
				<h3 className="ucsc-event-title">
					{stripHTMLTags(event.title)}
				</h3>
				{event.date && (
					<div className="ucsc-event-date">
						{stripHTMLTags(event.date)}
					</div>
				)}
				{event.slug && seriesSlugs.has(event.slug) && (
					<div className="ucsc-event-series">
						<span className="dashicons dashicons-controls-repeat"></span>
						{__('Series', 'ucsc-blocks')}
					</div>
				)}
				{event.venue && (
					<div className="ucsc-event-venue">
						{stripHTMLTags(event.venue)}
					</div>
				)}
			</div>
		</div>
	);

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="update"
						label={__('Clear Cache', 'ucsc-blocks')}
						onClick={clearCache}
						disabled={!effectiveUrl || isLoading}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Event Settings', 'ucsc-blocks')} initialOpen={true}>
					<FormTokenField
						className="ucsc-events-organizers-field"
						label={__('Organizers', 'ucsc-blocks')}
						value={organizers.map((org) => org.name)}
						suggestions={orgSuggestions.map((org) => org.name)}
						onInputChange={setOrgSearch}
						onChange={handleOrganizersChange}
						__experimentalExpandOnFocus={true}
						__experimentalShowHowTo={false}
						help={__('Start typing to search organizers. Select organizers, or use the category/tag filters below, to display events.', 'ucsc-blocks')}
					/>

					<RangeControl
						label={__('Number of Events', 'ucsc-blocks')}
						value={itemCount}
						onChange={(value) => setAttributes({ itemCount: value })}
						min={1}
						max={40}
						help={__('Maximum number of events to display', 'ucsc-blocks')}
					/>

					<SelectControl
						label={__('Layout Style', 'ucsc-blocks')}
						value={layoutStyle}
						options={layoutOptions}
						onChange={(value) => setAttributes({ layoutStyle: value })}
						help={__('Choose how events should be displayed', 'ucsc-blocks')}
					/>

					<ToggleControl
						label={__('Hide repeating events', 'ucsc-blocks')}
						checked={hideRepeating}
						onChange={(value) => setAttributes({ hideRepeating: value })}
						help={__('Show only the next upcoming instance of each repeating event.', 'ucsc-blocks')}
					/>

					<FormTokenField
						label={__('Filter by Category', 'ucsc-blocks')}
						value={categories.map((slug) => slugToCategoryName[slug] || slug)}
						suggestions={categoryOptions.map((option) => option.name)}
						onChange={(tokens) => {
							const slugs = tokens.map(
								(token) => categoryNameToSlug[token] || slugify(token)
							);
							setAttributes({ categories: [...new Set(slugs)] });
						}}
						__experimentalExpandOnFocus
						help={__('Show only events in the selected categories.', 'ucsc-blocks')}
					/>

					<FormTokenField
						label={__('Filter by Tag', 'ucsc-blocks')}
						value={tags}
						suggestions={tagSuggestions}
						onInputChange={searchTags}
						onChange={(tokens) => {
							const slugs = tokens.map((token) => slugify(token)).filter(Boolean);
							setAttributes({ tags: [...new Set(slugs)] });
						}}
						__experimentalExpandOnFocus
						help={__('Type to search tags, then select to filter events.', 'ucsc-blocks')}
					/>

					<div className="ucsc-events-cache-controls">
						<Button
							variant="secondary"
							onClick={clearCache}
							disabled={!effectiveUrl || isLoading}
							isBusy={isLoading}
						>
							{__('Clear Cache', 'ucsc-blocks')}
						</Button>
						{cacheCleared && (
							<Notice status="success" isDismissible={false}>
								{__('Cache cleared successfully!', 'ucsc-blocks')}
							</Notice>
						)}
					</div>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{!effectiveUrl && (
					<div className="ucsc-events-placeholder">
						<div className="ucsc-events-placeholder-content">
							<h3>{__('UCSC Events', 'ucsc-blocks')}</h3>
							<p>{__('Select one or more organizers, categories, or tags in the block settings to display events.', 'ucsc-blocks')}</p>
						</div>
					</div>
				)}

				{effectiveUrl && isLoading && (
					<div className="ucsc-events-loading">
						<Spinner />
						<span>{__('Loading items...', 'ucsc-blocks')}</span>
					</div>
				)}

				{effectiveUrl && !isLoading && error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{effectiveUrl && !isLoading && !error && displayData.length === 0 && (
					<Notice status="warning" isDismissible={false}>
						{__('No upcoming events found for the selected filters.', 'ucsc-blocks')}
					</Notice>
				)}

				{effectiveUrl && !isLoading && !error && displayData.length > 0 && (
					<div className="ucsc-events-list">
						{displayData.map(renderEventItem)}
					</div>
				)}
			</div>
		</>
	);
}