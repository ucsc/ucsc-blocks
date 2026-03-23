/**
 * Frontend JavaScript for the ICS Calendar block.
 *
 * Adds hover interactivity and makes entire event items clickable
 * when they contain a link.
 */

document.addEventListener( 'DOMContentLoaded', function () {
    const eventItems = document.querySelectorAll(
        '.wp-block-ucsc-ics-calendar .ucsc-ics-event-item'
    );

    eventItems.forEach( function ( item ) {
        // Hover lift effect
        item.addEventListener( 'mouseenter', function () {
            this.style.transition = 'transform 0.2s ease';
            this.style.transform = 'translateY(-2px)';
        } );

        item.addEventListener( 'mouseleave', function () {
            this.style.transform = 'translateY(0)';
        } );

        // Make the entire item clickable if it contains a link
        const link = item.querySelector( 'a' );
        if ( link && ! item.classList.contains( 'clickable-item' ) ) {
            item.classList.add( 'clickable-item' );
            item.style.cursor = 'pointer';

            item.addEventListener( 'click', function ( e ) {
                if ( e.target.tagName.toLowerCase() === 'a' ) {
                    return;
                }
                window.open( link.href, '_blank', 'noopener,noreferrer' );
            } );
        }
    } );
} );
