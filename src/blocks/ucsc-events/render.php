<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$api_url = $attributes['apiUrl'] ?? '';
$item_count = $attributes['itemCount'] ?? 5;
$layout_style = $attributes['layoutStyle'] ?? 'list';

// Get the block wrapper attributes with layout class
$wrapper_attributes = get_block_wrapper_attributes(array(
	'class' => 'layout-' . esc_attr($layout_style)
));

// Fetch events data
$events = array();
if (!empty($api_url)) {
	if (function_exists('ucsc_events_fetch_data')) {
		$events = ucsc_events_fetch_data($api_url, $item_count);
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
				<p><?php _e('Visit the <a href="https://events.ucsc.edu">UCSC events calendar</a> for a list of all upcoming events', 'ucsc-events'); ?></p>
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