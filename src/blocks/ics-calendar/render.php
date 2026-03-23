<?php
/**
 * Server-side render template for the ICS Calendar block.
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
if ( ! empty( $feed_url ) && function_exists( 'ucsc_ics_fetch_events' ) ) {
    $events = ucsc_ics_fetch_events( $feed_url, $item_count );
}
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php if ( empty( $feed_url ) || empty( $events ) ) : ?>
        <div class="ucsc-ics-placeholder">
            <div class="ucsc-ics-placeholder-content">
                <p><?php esc_html_e( 'No upcoming events to display.', 'ucsc-blocks' ); ?></p>
            </div>
        </div>
    <?php else : ?>
        <div class="ucsc-ics-events-list">
            <?php foreach ( $events as $event ) : ?>
                <div class="ucsc-ics-event-item">
                    <div class="ucsc-ics-event-content">
                        <h3 class="ucsc-ics-event-title">
                            <?php if ( ! empty( $event['url'] ) ) : ?>
                                <a href="<?php echo esc_url( $event['url'] ); ?>" rel="noopener noreferrer">
                                    <?php echo esc_html( $event['title'] ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $event['title'] ); ?>
                            <?php endif; ?>
                        </h3>

                        <?php if ( ! empty( $event['date'] ) ) : ?>
                            <div class="ucsc-ics-event-date">
                                <?php echo esc_html( $event['date'] ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $event['location'] ) ) : ?>
                            <div class="ucsc-ics-event-location">
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
                            <div class="ucsc-ics-event-description">
                                <?php echo wp_kses_post( $event['description'] ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
