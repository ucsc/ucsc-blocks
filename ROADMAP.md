# ROADMAP

Each item is scoped to be completable in a single Claude Code session.
Start a session by saying: **"Build feature: \<feature-name\>"**

Items are ordered by suggested priority. Dependencies are noted where they exist.

---

## 1. Block: ICS Calendar

**Branch:** `2026-03-block-ics-calendar`
**Status:** Not started

**Goal:** New block that fetches and displays events from an ICS/iCal feed URL (`.ics`). Many campus departments publish ICS feeds from Google Calendar, Outlook, or other systems. This block lets editors paste a feed URL and display upcoming events.

**Scope:**

- Block attributes: `feedUrl`, `itemCount` (default 5), `layoutStyle` (list/grid)
- Server-side PHP: fetch ICS feed, parse into events, cache with transients (15 min)
- Use a lightweight ICS parser (PHP library or custom parser for VEVENT)
- Server-side render (like ucsc-events)
- Editor: URL input, count slider, layout picker, preview, cache clear button
- Frontend: event list with date, title, location, description

**Acceptance:**

- Editor can paste an ICS URL, see a preview, and choose layout
- Frontend renders upcoming events sorted by start date
- Results are cached; cache can be cleared from the editor

**Notes:** Follow the ucsc-events block as a reference pattern. Register in `ucsc-blocks.php`.

---

## 2. Block: ucsc-events — Card Image Fallback

**Branch:** create from `main`
**Status:** Not started

**Goal:** When an event has no featured image, display a branded placeholder instead of hiding the image entirely. Improves visual consistency in grid/card layouts.

**Scope:**
- Add a default placeholder SVG or image (UCSC branded)
- Update `render.php` to use placeholder when no image URL exists
- Update `edit.js` preview to match
- Optional: block attribute to let editor choose a custom fallback image

**Acceptance:**
- Grid and card layouts show a consistent placeholder for events without images
- Editor preview matches frontend behavior

---

## 3. Block: ucsc-events — Date Range Filter

**Branch:** create from `main`
**Status:** Not started

**Goal:** Let editors filter events by date range — e.g., "next 7 days", "next 30 days", "this month", or a custom start/end date. Currently hardcoded to `starts_after=yesterday`.

**Scope:**
- New block attributes: `dateFilter` (preset or custom), `startDate`, `endDate`
- Add `SelectControl` in editor sidebar for presets
- Add `DateTimePicker` controls for custom range
- Update API query parameters in PHP fetch function

**Acceptance:**
- Editor can choose a date range preset or enter custom dates
- API requests include the correct date parameters
- Preview updates when filter changes

---

## 4. Block: ucsc-events — Category/Tag Filtering

**Branch:** create from `main`
**Status:** Not started

**Goal:** Let editors filter events by category or tag from the events API, so they can show only events relevant to their department or topic.

**Scope:**
- Investigate API for available filter parameters (categories, tags, departments)
- Add block attributes for selected filters
- Add multi-select or checkbox controls in editor sidebar
- Update PHP fetch function to include filter params

**Acceptance:**
- Editor can select one or more categories/tags to filter events
- Only matching events are displayed
- Works with existing layout options

---

## 5. Block: News/Announcements

**Branch:** create from `main`
**Status:** Not started

**Goal:** New block that fetches and displays news or announcements from a WordPress REST API endpoint (e.g., UCSC News). Similar architecture to ucsc-events but for posts/news content.

**Scope:**
- Block attributes: `apiUrl`, `itemCount`, `layoutStyle` (list/grid/cards)
- Server-side fetch from WP REST API `/wp-json/wp/v2/posts`
- Display: title, excerpt, date, featured image, link
- Caching with transients
- Editor controls matching ucsc-events pattern

**Acceptance:**
- Editor can enter a WP REST API URL and see news posts
- Supports list, grid, and card layouts
- Cached and performant

**Notes:** This shares significant patterns with ucsc-events. Consider extracting shared utilities after this block is built.

---

## 6. Shared: Extract Common Fetch/Cache Utilities

**Branch:** create from `main`
**Status:** Not started
**Depends on:** At least 2 blocks using fetch/cache (ucsc-events + one more)

**Goal:** Once multiple blocks share the same fetch-and-cache pattern, extract shared PHP utilities to reduce duplication.

**Scope:**
- Create `src/includes/` or `src/shared/` directory for shared PHP
- Extract: transient caching wrapper, HTTP fetch with error handling, cache-clear AJAX handler
- Refactor ucsc-events and other blocks to use shared functions
- Update `ucsc-blocks.php` to include shared files

**Acceptance:**
- Shared code is DRY across blocks
- All blocks still function identically after refactor
- No regression in caching or error handling

---

## 7. Block: People/Directory

**Branch:** create from `main`
**Status:** Not started

**Goal:** New block that displays a staff/faculty directory from a data source (REST API or manual entry). Common need for department pages.

**Scope:**
- Block attributes: `apiUrl` (optional), `layoutStyle` (list/grid), `department`
- Display: name, title, email, phone, photo, office
- Server-side render with caching
- Editor: data source input, layout picker, department filter
- Responsive grid layout

**Acceptance:**
- Block displays a directory of people with contact info
- Works with an external API or manual data entry
- Responsive across device sizes

---

## 8. Block: Social Media Links

**Branch:** create from `main`
**Status:** Not started

**Goal:** Simple block for displaying a row of social media icon links. Editors enter URLs for each platform; the block renders accessible icon links.

**Scope:**
- Block attributes: repeater/array of `{platform, url}` objects
- Support: Instagram, X/Twitter, Facebook, LinkedIn, YouTube, GitHub, Bluesky
- SVG icons for each platform
- Editor: repeater UI to add/remove/reorder links
- Accessible: `aria-label` on each link
- Style options: icon size, color scheme (branded or monochrome)

**Acceptance:**
- Editor can add social media links with a clean UI
- Frontend renders accessible icon links in a row
- Icons are SVG (no external font dependency)

---

## 9. Block: Alert/Notice Banner

**Branch:** create from `main`
**Status:** Not started

**Goal:** A dismissible alert or notice banner for important announcements. Useful for campus-wide alerts, maintenance notices, or department announcements.

**Scope:**
- Block attributes: `message` (rich text), `type` (info/warning/error/success), `dismissible` (boolean)
- Editor: rich text input, type selector, dismissible toggle
- Frontend: styled banner with optional dismiss button
- `view.js`: dismiss handler with `localStorage` to remember dismissal
- Accessible: appropriate ARIA roles (`alert`, `status`)

**Acceptance:**
- Editor can create styled alert banners
- Users can dismiss banners (persisted in localStorage)
- Meets WCAG accessibility requirements

---

## 10. Enhancement: Dark Mode / High Contrast Support

**Branch:** create from `main`
**Status:** Not started

**Goal:** Ensure all blocks respect `prefers-color-scheme` and provide a high-contrast mode for accessibility compliance.

**Scope:**
- Audit all block styles for color contrast (WCAG AA minimum)
- Add CSS custom properties for theming
- Add `@media (prefers-color-scheme: dark)` rules
- Add `@media (prefers-contrast: more)` rules
- Test across all layouts

**Acceptance:**
- All blocks look correct in light mode, dark mode, and high contrast
- Color contrast meets WCAG AA (4.5:1 for normal text)

---

## Future Ideas (not yet scoped)

These need more research or discussion before becoming roadmap items:

- **Block: Accordion/FAQ** — Collapsible content sections
- **Block: Campus Map Embed** — Interactive campus map with location pins
- **Block: Quick Links** — Styled link list with icons, common for department homepages
- **Block: Stats/Counter** — Animated statistics display (e.g., "15,000 students")
- **Plugin: Settings Page** — Global settings (default cache duration, API keys, brand colors)
- **Testing: E2E Tests** — Playwright tests for block editor interactions
- **Testing: PHP Unit Tests** — PHPUnit for server-side fetch/cache functions
