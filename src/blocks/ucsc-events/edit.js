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
	const { apiUrl, itemCount, layoutStyle } = attributes;
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

	// Fetch preview data when API URL or item count changes
	useEffect(() => {
		if (apiUrl) {
			fetchPreviewData();
		} else {
			setPreviewData([]);
			setError('');
		}
	}, [apiUrl, itemCount]);

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

			url.searchParams.set('per_page', itemCount);

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
				link: item.url || ''
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
			formData.append('nonce', window.ucscEventsNonce || '');

			const response = await fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
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
						help={__('Enter the WordPress REST API URL (e.g., https://example.com/wp-json/wp/v2/posts)', 'ucsc-events')}
						placeholder="https://example.com/wp-json/wp/v2/posts"
					/>

					<RangeControl
						label={__('Number of Events', 'ucsc-events')}
						value={itemCount}
						onChange={(value) => setAttributes({ itemCount: value })}
						min={1}
						max={20}
						help={__('Maximum number of events to display', 'ucsc-events')}
					/>

					<SelectControl
						label={__('Layout Style', 'ucsc-events')}
						value={layoutStyle}
						options={layoutOptions}
						onChange={(value) => setAttributes({ layoutStyle: value })}
						help={__('Choose how events should be displayed', 'ucsc-events')}
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
							<p>{__('Enter an API URL in the block settings to fetch and display events.', 'ucsc-events')}</p>
						</div>
					</div>
				)}

				{apiUrl && isLoading && (
					<div className="ucsc-events-loading">
						<Spinner />
						<span>{__('Loading events...', 'ucsc-events')}</span>
					</div>
				)}

				{apiUrl && error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{apiUrl && !isLoading && !error && previewData.length === 0 && (
					<Notice status="warning" isDismissible={false}>
						{__('No events found at the specified API URL.', 'ucsc-events')}
					</Notice>
				)}

				{apiUrl && !isLoading && !error && previewData.length > 0 && (
					<div className="ucsc-events-list">
						{previewData.map(renderEventItem)}
					</div>
				)}
			</div>
		</>
	);
}