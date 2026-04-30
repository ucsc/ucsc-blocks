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
	TextControl,
	RangeControl,
	SelectControl,
	ToggleControl,
	Button,
	Notice,
	ToolbarGroup,
	ToolbarButton,
	Spinner
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';

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
export default function Edit( { attributes, setAttributes } ) {
	const { apiUrl, itemCount, layoutStyle, hideRepeating } = attributes;
	const [previewData, setPreviewData] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');
	const [cacheCleared, setCacheCleared] = useState(false);

	const blockProps = useBlockProps({
		className: `layout-${layoutStyle}`
	});

	const layoutOptions = [
		{ label: __('List', 'ucsc-events'), value: 'list' },
		{ label: __('Grid', 'ucsc-events'), value: 'grid' },
		{ label: __('Cards', 'ucsc-events'), value: 'cards' }
	];

	// Fetch preview data when API URL changes (debounced).
	// Always fetches 50 events; itemCount is applied locally when rendering.
	useEffect(() => {
		if (!apiUrl) {
			setPreviewData([]);
			setError('');
			return;
		}

		// Debounce API calls - wait 1s after user stops typing
		const timeoutId = setTimeout(() => {
			fetchPreviewData();
		}, 1000);

		// Cleanup function to cancel the timeout if apiUrl changes again
		return () => clearTimeout(timeoutId);
	}, [apiUrl]);

	const fetchPreviewData = async () => {
		if (!apiUrl) return;

		setIsLoading(true);
		setError('');

		try {
			// Basic URL validation
			let url;
			try {
				url = new URL(apiUrl);
			} catch {
				throw new Error(__('Please enter a valid URL', 'ucsc-events'));
			}

			url.searchParams.set('per_page', 50);
			url.searchParams.set('starts_after', 'yesterday');

			const response = await fetch(url.toString(), {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json'
				},
				signal: AbortSignal.timeout(8000) // 8 second timeout
			});
			
			if (!response.ok) {
				if (response.status === 404) {
					throw new Error(__('API endpoint not found. Please check the URL.', 'ucsc-events'));
				} else if (response.status === 403) {
					throw new Error(__('Access denied to the API endpoint.', 'ucsc-events'));
				} else {
					throw new Error(__('Failed to fetch data from API. Status: ' + response.status, 'ucsc-events'));
				}
			}

			const fetched = await response.json();
			const data = fetched.events;
			
			if (!Array.isArray(data)) {
				throw new Error(__('Invalid API response format. Expected an object with array of events.', 'ucsc-events'));
			}

			if (data.length === 0) {
				setPreviewData([]);
				return;
			}

			const processedData = data.map(item => ({
				title: item.title || __('Untitled', 'ucsc-events'),
				organizer: item.organizer?.organizer || '',
				date: dateI18n('F, j, Y', item.start_date) || '',
				venue: item.venue?.venue || '',
				featured_image: item.image?.url || '',
				link: item.url || '',
				slug: item.slug || ''
			}));

			setPreviewData(processedData);

		} catch (err) {
			if (err.name === 'AbortError') {
				setError(__('Request timeout. Please try again.', 'ucsc-events'));
			} else {
				setError(err.message);
			}
			setPreviewData([]);
		} finally {
			setIsLoading(false);
		}
	};

	const clearCache = async () => {
		if (!apiUrl) return;

		setIsLoading(true);

		try {
			const formData = new FormData();
			formData.append('action', 'ucsc_events_clear_cache');
			formData.append('api_url', apiUrl);
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
				throw new Error(result.data?.message || __('Failed to clear cache', 'ucsc-events'));
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
						{__('Series', 'ucsc-events')}
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
						label={__('Clear Cache', 'ucsc-events')}
						onClick={clearCache}
						disabled={!apiUrl || isLoading}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Event Settings', 'ucsc-events')} initialOpen={true}>
					<TextControl
						label={__('API URL', 'ucsc-events')}
						value={apiUrl}
						onChange={(value) => setAttributes({ apiUrl: value.trim() })}
						help={
							<>
								{__('See ', 'ucsc-events')}
                                <a href="https://docs.google.com/spreadsheets/d/16DKhaoxPc2h9qMHah6_fvHTvizZbIlEvOVvMW3Hxg-c/edit?gid=1514211697#gid=1514211697" target="_blank" rel="noopener noreferrer">
                                    this Google Sheet
								</a>
                                {__(' ↗️ for help.', 'ucsc-events')}
							</>
						}
						placeholder="https://events.ucsc.edu/wp-json/tribe/events/v1/events"
					/>

					<RangeControl
						label={__('Number of Events', 'ucsc-events')}
						value={itemCount}
						onChange={(value) => setAttributes({ itemCount: value })}
						min={1}
						max={40}
						help={__('Maximum number of events to display', 'ucsc-events')}
					/>

					<SelectControl
						label={__('Layout Style', 'ucsc-events')}
						value={layoutStyle}
						options={layoutOptions}
						onChange={(value) => setAttributes({ layoutStyle: value })}
						help={__('Choose how events should be displayed', 'ucsc-events')}
					/>

					<ToggleControl
						label={__('Hide repeating events', 'ucsc-events')}
						checked={hideRepeating}
						onChange={(value) => setAttributes({ hideRepeating: value })}
						help={__('Show only the next upcoming instance of each repeating event.', 'ucsc-events')}
					/>

					<div className="ucsc-events-cache-controls">
						<Button
							variant="secondary"
							onClick={clearCache}
							disabled={!apiUrl || isLoading}
							isBusy={isLoading}
						>
							{__('Clear Cache', 'ucsc-events')}
						</Button>
						{cacheCleared && (
							<Notice status="success" isDismissible={false}>
								{__('Cache cleared successfully!', 'ucsc-events')}
							</Notice>
						)}
					</div>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{!apiUrl && (
					<div className="ucsc-events-placeholder">
						<div className="ucsc-events-placeholder-content">
							<h3>{__('UCSC Events', 'ucsc-events')}</h3>
							<p>{__('Enter an API URL in the block settings to display items.', 'ucsc-events')}</p>
						</div>
					</div>
				)}

				{apiUrl && isLoading && (
					<div className="ucsc-events-loading">
						<Spinner />
						<span>{__('Loading items...', 'ucsc-events')}</span>
					</div>
				)}

				{apiUrl && error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{apiUrl && !isLoading && !error && displayData.length === 0 && (
					<Notice status="warning" isDismissible={false}>
						{__('No items found at the specified URL.', 'ucsc-events')}
					</Notice>
				)}

				{apiUrl && !isLoading && !error && displayData.length > 0 && (
					<div className="ucsc-events-list">
						{displayData.map(renderEventItem)}
					</div>
				)}
			</div>
		</>
	);
}