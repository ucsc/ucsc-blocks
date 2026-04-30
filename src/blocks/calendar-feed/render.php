<?php
/**
 * Server-side render template for the Calendar Feed block.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package UcscBlocks
 */

$feed_url     = $attributes['feedUrl'] ?? '';
$item_count   = $attributes['itemCount'] ?? 5;
$layout_style = $attributes['layoutStyle'] ?? 'list';

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'layout-' . esc_attr( $layout_style ),
) );

// Fetch events
$events = array();
if ( ! empty( $feed_url ) && function_exists( 'ucsc_calendar_feed_fetch_events' ) ) {
    $events = ucsc_calendar_feed_fetch_events( $feed_url, $item_count );
}
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php if ( empty( $feed_url ) || empty( $events ) ) : ?>
        <div class="ucsc-cf-placeholder">
            <div class="ucsc-cf-placeholder-content">
                <p><?php esc_html_e( 'No upcoming events to display.', 'ucsc-blocks' ); ?></p>
            </div>
        </div>
    <?php else : ?>
        <ol class="ucsc-cf-events-list">
            <?php foreach ( $events as $event ) : ?>
                <li class="ucsc-cf-event-item">
                    <div class="ucsc-cf-event-content">
                        <p class="ucsc-cf-event-title">
                            <?php echo esc_html( $event['title'] ); ?>
                        </p>

                        <?php if ( ! empty( $event['date'] ) ) : ?>
                            <div class="ucsc-cf-event-date">
                                <?php echo esc_html( $event['date'] ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $event['location'] ) ) : ?>
                            <div class="ucsc-cf-event-location">
                                <?php
                                $location_url = filter_var( $event['location'], FILTER_VALIDATE_URL );
                                if ( $location_url && preg_match( '#^https?://#i', $location_url ) ) :
                                    $location_host = wp_parse_url( $location_url, PHP_URL_HOST );
                                ?>
                                    <a href="<?php echo esc_url( $location_url ); ?>" rel="noopener noreferrer">
                                        <?php echo esc_html( $location_host ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $event['location'] ); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $event['description'] ) ) : ?>
                            <div class="ucsc-cf-event-description">
                                <?php
                                echo wp_kses(
                                    $event['description'],
                                    ucsc_calendar_feed_allowed_description_html(),
                                    array( 'http', 'https' )
                                );
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
