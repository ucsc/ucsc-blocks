/**
 * Edit component for the ICS Calendar block.
 *
 * Provides the editor UI: feed URL input, event count slider,
 * layout picker, live preview via the REST API proxy, and
 * a cache-clear button.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    RangeControl,
    SelectControl,
    Button,
    Notice,
    ToolbarGroup,
    ToolbarButton,
    Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
    const { feedUrl, itemCount, layoutStyle } = attributes;
    const [ previewData, setPreviewData ] = useState( [] );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ error, setError ] = useState( '' );
    const [ cacheCleared, setCacheCleared ] = useState( false );

    const blockProps = useBlockProps( {
        className: `layout-${ layoutStyle }`,
    } );

    const layoutOptions = [
        { label: __( 'List', 'ucsc-blocks' ), value: 'list' },
        { label: __( 'Grid', 'ucsc-blocks' ), value: 'grid' },
    ];

    // Fetch preview when feed URL or count changes (debounced).
    useEffect( () => {
        if ( ! feedUrl ) {
            setPreviewData( [] );
            setError( '' );
            return;
        }

        const timeoutId = setTimeout( () => {
            fetchPreviewData();
        }, 1000 );

        return () => clearTimeout( timeoutId );
    }, [ feedUrl, itemCount ] );

    /**
     * Fetch ICS data for the editor preview.
     *
     * Uses the WP AJAX endpoint which runs the server-side parser,
     * so the preview matches what render.php will produce.
     */
    const fetchPreviewData = async () => {
        if ( ! feedUrl ) return;

        setIsLoading( true );
        setError( '' );

        try {
            // Basic URL validation
            try {
                new URL( feedUrl );
            } catch {
                throw new Error( __( 'Please enter a valid URL', 'ucsc-blocks' ) );
            }

            const formData = new FormData();
            formData.append( 'action', 'ucsc_ics_calendar_preview' );
            formData.append( 'feed_url', feedUrl );
            formData.append( 'item_count', itemCount );
            formData.append( 'nonce', window.ucscIcsCalendarData?.nonce || '' );

            const response = await fetch(
                window.ucscIcsCalendarData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                {
                    method: 'POST',
                    body: formData,
                }
            );

            const result = await response.json();

            if ( result.success && Array.isArray( result.data ) ) {
                setPreviewData( result.data );
            } else {
                throw new Error(
                    result.data?.message || __( 'Failed to fetch calendar feed', 'ucsc-blocks' )
                );
            }
        } catch ( err ) {
            setError( err.message );
            setPreviewData( [] );
        } finally {
            setIsLoading( false );
        }
    };

    /**
     * Clear the server-side transient cache for this feed URL.
     */
    const clearCache = async () => {
        if ( ! feedUrl ) return;

        setIsLoading( true );

        try {
            const formData = new FormData();
            formData.append( 'action', 'ucsc_ics_calendar_clear_cache' );
            formData.append( 'feed_url', feedUrl );
            formData.append( 'nonce', window.ucscIcsCalendarData?.nonce || '' );

            const response = await fetch(
                window.ucscIcsCalendarData?.ajaxUrl || '/wp-admin/admin-ajax.php',
                {
                    method: 'POST',
                    body: formData,
                }
            );

            const result = await response.json();

            if ( result.success ) {
                setCacheCleared( true );
                setTimeout( () => setCacheCleared( false ), 3000 );
                fetchPreviewData();
            } else {
                throw new Error(
                    result.data?.message || __( 'Failed to clear cache', 'ucsc-blocks' )
                );
            }
        } catch ( err ) {
            setError( err.message );
        } finally {
            setIsLoading( false );
        }
    };

    const renderEventItem = ( event, index ) => (
        <div key={ index } className="ucsc-ics-event-item">
            <div className="ucsc-ics-event-content">
                <h3 className="ucsc-ics-event-title">{ event.title }</h3>
                { event.date && (
                    <div className="ucsc-ics-event-date">{ event.date }</div>
                ) }
                { event.location && (
                    <div className="ucsc-ics-event-location">
                        { /^https?:\/\//i.test( event.location ) ? (
                            <a href={ event.location } rel="noopener noreferrer">
                                { new URL( event.location ).hostname }
                            </a>
                        ) : (
                            event.location
                        ) }
                    </div>
                ) }
                { event.description && (
                    <div className="ucsc-ics-event-description">{ event.description }</div>
                ) }
            </div>
        </div>
    );

    return (
        <>
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon="update"
                        label={ __( 'Clear Cache', 'ucsc-blocks' ) }
                        onClick={ clearCache }
                        disabled={ ! feedUrl || isLoading }
                    />
                </ToolbarGroup>
            </BlockControls>

            <InspectorControls>
                <PanelBody title={ __( 'Calendar Settings', 'ucsc-blocks' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'ICS Feed URL', 'ucsc-blocks' ) }
                        value={ feedUrl }
                        onChange={ ( value ) => setAttributes( { feedUrl: value.trim() } ) }
                        help={ __( 'Paste the URL of an ICS/iCal feed (.ics). Works with Google Calendar, Outlook, and other calendar systems.', 'ucsc-blocks' ) }
                        placeholder="https://example.com/calendar.ics"
                    />

                    <RangeControl
                        label={ __( 'Number of Events', 'ucsc-blocks' ) }
                        value={ itemCount }
                        onChange={ ( value ) => setAttributes( { itemCount: value } ) }
                        min={ 1 }
                        max={ 20 }
                        help={ __( 'Maximum number of upcoming events to display', 'ucsc-blocks' ) }
                    />

                    <SelectControl
                        label={ __( 'Layout Style', 'ucsc-blocks' ) }
                        value={ layoutStyle }
                        options={ layoutOptions }
                        onChange={ ( value ) => setAttributes( { layoutStyle: value } ) }
                        help={ __( 'Choose how events should be displayed', 'ucsc-blocks' ) }
                    />

                    <div className="ucsc-ics-cache-controls">
                        <Button
                            variant="secondary"
                            onClick={ clearCache }
                            disabled={ ! feedUrl || isLoading }
                            isBusy={ isLoading }
                        >
                            { __( 'Clear Cache', 'ucsc-blocks' ) }
                        </Button>
                        { cacheCleared && (
                            <Notice status="success" isDismissible={ false }>
                                { __( 'Cache cleared successfully!', 'ucsc-blocks' ) }
                            </Notice>
                        ) }
                    </div>
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                { ! feedUrl && (
                    <div className="ucsc-ics-placeholder">
                        <div className="ucsc-ics-placeholder-content">
                            <h3>{ __( 'ICS Calendar', 'ucsc-blocks' ) }</h3>
                            <p>
                                { __( 'Enter an ICS feed URL in the block settings to display upcoming events.', 'ucsc-blocks' ) }
                            </p>
                        </div>
                    </div>
                ) }

                { feedUrl && isLoading && (
                    <div className="ucsc-ics-loading">
                        <Spinner />
                        <span>{ __( 'Loading events…', 'ucsc-blocks' ) }</span>
                    </div>
                ) }

                { feedUrl && error && (
                    <Notice status="error" isDismissible={ false }>
                        { error }
                    </Notice>
                ) }

                { feedUrl && ! isLoading && ! error && previewData.length === 0 && (
                    <Notice status="warning" isDismissible={ false }>
                        { __( 'No upcoming events found in this feed.', 'ucsc-blocks' ) }
                    </Notice>
                ) }

                { feedUrl && ! isLoading && ! error && previewData.length > 0 && (
                    <div className="ucsc-ics-events-list">
                        { previewData.map( renderEventItem ) }
                    </div>
                ) }
            </div>
        </>
    );
}
