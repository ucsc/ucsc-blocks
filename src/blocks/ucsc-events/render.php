<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$organizers = $attributes['organizers'] ?? array();
$legacy_url = $attributes['apiUrl'] ?? '';
$item_count = $attributes['itemCount'] ?? 6;
$layout_style = $attributes['layoutStyle'] ?? 'list';
$hide_repeating = $attributes['hideRepeating'] ?? false;
$categories = $attributes['categories'] ?? array();
$tags = $attributes['tags'] ?? array();

// Build the API URL from the selected organizers (falling back to a legacy URL
// or the unfiltered campus feed).
$organizer_ids = function_exists('ucsc_events_get_organizer_ids')
	? ucsc_events_get_organizer_ids($organizers)
	: array();
$api_url = function_exists('ucsc_events_build_api_url')
	? ucsc_events_build_api_url($organizer_ids, $legacy_url)
	: $legacy_url;

// Get the block wrapper attributes with layout class
$wrapper_attributes = get_block_wrapper_attributes(array(
	'class' => 'layout-' . esc_attr($layout_style)
));

// Fetch all cached events (up to 50 from the API)
$events = array();
$series_slugs = array();
if (!empty($api_url)) {
	if (function_exists('ucsc_events_fetch_data')) {
		$events = ucsc_events_fetch_data($api_url, $categories, $tags);

		// Identify slugs that appear more than once (i.e. repeating/series events).
		// Built from the full dataset before any filtering or slicing.
		$slug_counts = array();
		foreach ($events as $event) {
			$slug = $event['slug'] ?? '';
			if (!empty($slug)) {
				$slug_counts[$slug] = ($slug_counts[$slug] ?? 0) + 1;
			}
		}
		$series_slugs = array_filter($slug_counts, function ($count) {
			return $count > 1;
		});

		// Filter out repeating events, keeping only the nearest upcoming instance.
		// Events are returned sorted by date, so the first occurrence of each slug wins.
		if ($hide_repeating && !empty($events)) {
			$seen_slugs = array();
			$events = array_filter($events, function ($event) use (&$seen_slugs) {
				$slug = $event['slug'] ?? '';
				if (empty($slug)) {
					return true;
				}
				if (isset($seen_slugs[$slug])) {
					return false;
				}
				$seen_slugs[$slug] = true;
				return true;
			});
		}

		// Slice to the requested number of events
		$events = array_slice($events, 0, $item_count);
	}
}

// Add nonce to global JS object
if (!wp_script_is('ucsc-events-frontend', 'done')) {
	wp_add_inline_script(
		'wp-block-ucsc-events-view-script',
		'window.ucscEventsNonce = ' . json_encode(wp_create_nonce('ucsc_events_nonce')) . ';',
		'before'
	);
}

?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if (empty($api_url) or empty($events)): ?>
		<div class="ucsc-events-placeholder">
			<div class="ucsc-events-placeholder-content">
				<p><?php _e('Visit the <a href="https://events.ucsc.edu">UCSC events calendar</a> for a list of all upcoming events', 'ucsc-blocks'); ?></p>
			</div>
		</div>
	<?php else: ?>
		<div class="ucsc-events-list">
			<?php foreach ($events as $event): ?>
				<div class="ucsc-event-item">
					<?php if (!empty($event['featured_image'])): ?>
						<div class="ucsc-event-image">
							<img
								src="<?php echo esc_url($event['featured_image']); ?>"
								alt=""
								loading="lazy"
								onerror="this.style.display='none'" />
						</div>
					<?php endif; ?>

					<div class="ucsc-event-content">
						<h3 class="ucsc-event-title">
							<?php if (!empty($event['link'])): ?>
								<a href="<?php echo esc_url($event['link']); ?>" rel="noopener noreferrer">
									<?php echo wp_kses_post($event['title']); ?>
								</a>
							<?php else: ?>
								<?php echo wp_kses_post($event['title']); ?>
							<?php endif; ?>
						</h3>

						<?php if (!empty($event['date'])): ?>
							<div class="ucsc-event-date">
								<?php echo wp_kses_post($event['date']); ?>
							</div>
						<?php endif; ?>

						<?php if (isset($series_slugs[$event['slug'] ?? ''])): ?>
							<div class="ucsc-event-series">
								<span class="dashicons dashicons-controls-repeat"></span>
								<?php _e('Series', 'ucsc-blocks'); ?>
							</div>
						<?php endif; ?>

						<?php if (!empty($event['venue'])): ?>
							<div class="ucsc-event-venue">
								<?php echo wp_kses_post($event['venue']); ?>
							</div>
						<?php endif; ?>

					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>