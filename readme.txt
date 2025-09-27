
=== UCSC Events ===

Contributors:      WordPress Telex
Tags:              block, events, api, external content
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Fetch and display events from external WordPress sites with customizable layouts and caching.

== Description ==

The UCSC Events block allows you to fetch and display event data from external WordPress sites using their REST API. This powerful block provides:

* **External API Integration**: Connect to any WordPress site's REST API to fetch event data
* **Customizable Display**: Control the number of events shown and choose from different layout styles
* **Smart Caching**: Built-in WordPress transient caching for improved performance
* **Cache Management**: Easy-to-use cache clearing functionality right in the block settings
* **Rich Content Display**: Shows event titles, teasers, and featured images
* **Responsive Layouts**: Multiple layout options that work great on all devices

Perfect for universities, organizations, or any website that needs to display events from multiple WordPress sites in a unified, attractive format.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ucsc-events` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Add the UCSC Events block to any post or page
4. Configure the API URL, number of items, and layout style in the block settings

== Frequently Asked Questions ==

= What API endpoints does this block support? =

This block works with standard WordPress REST API endpoints, specifically endpoints that return post data with title, excerpt, and featured image information.

= How does the caching work? =

The block uses WordPress transients to cache API responses for 15 minutes by default. This reduces API calls and improves page load times. You can clear the cache manually using the "Clear Cache" button in the block settings.

= What layout styles are available? =

The block includes multiple layout styles including list, grid, and card layouts. Each style is fully responsive and can be customized with CSS.

= Can I use this with non-WordPress APIs? =

This block is specifically designed for WordPress REST API endpoints. For other APIs, you would need to modify the code to handle different response formats.

== Screenshots ==

1. Block settings panel showing API URL input, item count selector, and layout options
2. Grid layout displaying events with featured images, titles, and teasers
3. List layout showing events in a clean, organized format
4. Cache management controls in the block toolbar

== Changelog ==

= 0.1.0 =
* Initial release
* API URL input field
* Configurable number of items to display
* Multiple layout style options
* WordPress transient caching
* Cache clearing functionality
* Responsive design support

== Upgrade Notice ==

= 0.1.0 =
Initial release of UCSC Events block.
