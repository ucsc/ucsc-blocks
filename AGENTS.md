# AGENTS.md

## Project Overview

UCSC Blocks is a modular WordPress plugin providing custom Gutenberg blocks for UC Santa Cruz websites. Built with `@wordpress/scripts` and `@wordpress/create-block`.

## Architecture

We strictly follow a [multi-block, single plugin architecture](https://developer.wordpress.org/news/2024/09/how-to-build-a-multi-block-plugin/).

```a
src/blocks/<block-name>/
  block.json       # Block metadata, attributes, asset references
  index.js         # Block registration
  edit.js          # Editor UI (React)
  save.js          # Client-side save (empty when using server-side render)
  view.js          # Frontend interactivity (vanilla JS)
  render.php       # Server-side rendering template
  <block-name>.php # Server-side functions (API, AJAX, enqueue hooks)
  editor.scss      # Editor-only styles
  style.scss       # Shared editor + frontend styles
```

Each block is self-contained. Block-specific PHP functions live in `src/blocks/<block-name>/<block-name>.php`, not in the main plugin file.

The main plugin file (`ucsc-blocks.php`) handles only:

- Block registration via `register_block_type()`
- Including block-specific PHP files from `build/blocks/`

To add a new block:

1. Create its directory under `src/blocks/`
2. Add the block name to the `$custom_blocks` array in `ucsc-blocks.php`
3. Add its PHP include to the `$ucsc_block_includes` array in `ucsc-blocks.php`

## Build

- **Dev**: `npm start` (watches and rebuilds, copies code files to `build/`)
- **Production**: `npm run build`
- The `--webpack-copy-php` flag copies `.php` files from `src/blocks/` to `build/blocks/`
- Never edit files in `build/` directly

## Commit Conventions

Conventional Commits with emoji prefixes:

- `feat: ✨ Description` — new feature
- `fix: 🐛 Description` — bug fix
- `refactor: ✏️ Description` — code restructuring
- `chore(release): X.Y.Z` — release commits (automated by standard-version)
- Reference issues/PRs with `(#N)`

## Versioning

Uses `standard-version`. Version is tracked in three places (automatically bumped):

- `package.json`
- `package-lock.json`
- `ucsc-blocks.php` plugin header

## Code Style

- 4-space indentation (see `.editorconfig`)
- WordPress PHP coding standards (function naming: `ucsc_<block>_<action>`)
- WordPress JS/React patterns with `@wordpress/*` packages

## External Data Security

All content fetched from external sources (APIs, ICS feeds, etc.) must be treated as untrusted and sanitized before it is cached, stored, or rendered. Follow these rules:

- **Text fields**: Pass through `sanitize_text_field()` before caching. For longer content intended as plain text, use `wp_strip_all_tags()` before storage.
- **URLs from external data**: Validate with `esc_url_raw()` restricted to `https` and `http` schemes before caching. Use `esc_url()` again at render time.
- **HTML output**: Use `esc_html()` for plain-text values in templates. Only use `wp_kses_post()` when markup is intentionally preserved, and never on raw external input.
- **Feed/API URLs provided by editors**: Validate scheme (HTTPS only), resolve the hostname, and reject private/reserved IP ranges to prevent SSRF. See `ucsc_ics_validate_feed_url()` for the reference implementation.
- **Response bodies**: Enforce a maximum size (e.g. 2 MB) before parsing to prevent memory exhaustion.
- **Numeric inputs**: Clamp to expected bounds with `absint()`, `intval()`, and `min()`/`max()` at every entry point (block attributes, AJAX handlers).
- **Caching**: Sanitize data *before* writing to transients so cached values are already clean.
- **Datetime parsing**: Accept only well-defined formats; never fall back to permissive parsing like `strtotime()` on external input.

## Current Blocks

- **ucsc-events** (`ucsc/events`): Fetches and displays campus events from an external API. Supports list, grid, and card layouts. Caches API responses using WordPress transients.
- **ics-calendar** (`ucsc/ics-calendar`): Fetches and displays upcoming events from an ICS/iCal feed URL. Supports list and grid layouts. Parses VEVENT data server-side, caches with transients, and includes SSRF protection and input sanitization.
