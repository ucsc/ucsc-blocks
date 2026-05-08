/**
 * Edit component for the Calendar Feed block.
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
import { RawHTML, useState, useEffect } from '@wordpress/element';

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

    // Fetch preview when the feed URL changes (debounced).
    // The item count is applied client-side so changing it does not
    // trigger a new network request.
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
    }, [ feedUrl ] );

    /**
     * Fetch calendar feed data for the editor preview.
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
            formData.append( 'action', 'ucsc_calendar_feed_preview' );
            formData.append( 'feed_url', feedUrl );
            formData.append( 'item_count', 20 );
            formData.append( 'nonce', window.ucscCalendarFeedData?.nonce || '' );

            const response = await fetch(
                window.ucscCalendarFeedData?.ajaxUrl || '/wp-admin/admin-ajax.php',
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
            formData.append( 'action', 'ucsc_calendar_feed_clear_cache' );
            formData.append( 'feed_url', feedUrl );
            formData.append( 'nonce', window.ucscCalendarFeedData?.nonce || '' );

            const response = await fetch(
                window.ucscCalendarFeedData?.ajaxUrl || '/wp-admin/admin-ajax.php',
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
        <li key={ index } className="ucsc-cf-event-item">
            <div className="ucsc-cf-event-content">
                <p className="ucsc-cf-event-title">{ event.title }</p>
                { event.date && (
                    <div className="ucsc-cf-event-date">{ event.date }</div>
                ) }
                { event.location && (
                    <div className="ucsc-cf-event-location">
                        { ( () => {
                            try {
                                const u = new URL( event.location );
                                if ( u.protocol === 'https:' || u.protocol === 'http:' ) {
                                    return (
                                        <a href={ u.href } rel="noopener noreferrer">
                                            { u.hostname }
                                        </a>
                                    );
                                }
                            } catch {}
                            return event.location;
                        } )() }
                    </div>
                ) }
                { event.description && (
                    <RawHTML className="ucsc-cf-event-description">
                        { event.description }
                    </RawHTML>
                ) }
            </div>
        </li>
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
                        label={ __( 'Feed URL', 'ucsc-blocks' ) }
                        value={ feedUrl }
                        onChange={ ( value ) => setAttributes( { feedUrl: value.trim() } ) }
                        help={ __( 'Paste the URL of an iCalendar feed (usually ends with .ics).', 'ucsc-blocks' ) }
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

                    <div className="ucsc-cf-cache-controls">
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
                    <div className="ucsc-cf-placeholder">
                        <div className="ucsc-cf-placeholder-content">
                            <h3>{ __( 'iCalendar feed block', 'ucsc-blocks' ) }</h3>
                            <p>
                                { __('Enter a public iCalendar feed URL in the block settings to display upcoming events. In the settings for your Calendar, look for the "Public address in iCal format" link.', 'ucsc-blocks')}
                            </p>
                            <p>
                                <a href="https://support.google.com/calendar/answer/37083" target="_blank" rel="noreferrer">{__('See this Google Calendar help document for assistance.', 'ucsc-blocks')}</a> {__( 'Other calendar platforms should have similar options. Check their documentation for details.', 'ucsc-blocks' ) }
                            </p>
                        </div>
                    </div>
                ) }

                { feedUrl && isLoading && (
                    <div className="ucsc-cf-loading">
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
                    <ol className="ucsc-cf-events-list">
                        { previewData.slice( 0, itemCount ).map( renderEventItem ) }
                    </ol>
                ) }
            </div>
        </>
    );
}
