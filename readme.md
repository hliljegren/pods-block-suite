# PODS Block Suite

A complete suite of Gutenberg blocks for the [PODS Framework](https://pods.io/) that lets you display, loop, and filter custom fields directly in the WordPress Block Editor without legacy PODS Templates.

## Included Blocks

### 1. PODS Item Container (`pods-block-suite/item-container`)
Provides a specific Post ID context (or inherits the current post/page context) to all nested inner blocks. Ideal for showcasing a single featured POD item anywhere on a page.

### 2. PODS Related Item Container (`pods-block-suite/related-item-container`)
Loops through single or one-to-many PODS relationship fields (`pick`) and renders inner blocks for each related item.
* **Flexbox Grid Layout:** Supports column/row layouts with configurable column counts (`1–6`) and `justify-content` alignment (`center`, `flex-start`, `flex-end`, `space-between`, `space-around`, `space-evenly`).
* **Custom Sorting & Limits:** Sort related items by supported WordPress standard fields or target PODS fields (with automatic numeric sorting for `number` and `currency` fields), set `ASC`/`DESC`/`Random` order, and optionally cap the maximum number of displayed items.
* **Clickable Cards:** Optional card wrapper (`Link to post`) that links each item to its single post permalink.

### 3. PODS Item List Container (`pods-block-suite/item-list-container`)
Queries and lists items from any selected POD with configurable sorting, pagination, responsive Flexbox grids, and **interactive frontend visitor filters**.
* **Flexbox Grid & Alignment:** Supports column/row layouts (`1–6` columns), `justify-content` alignment controls, and clickable card wrappers.
* **Custom Sorting:** Order items by supported WordPress standard fields, PODS custom fields (automatically cast numerically for `number` and `currency` fields), or `Random` order.
* **Selection & Relationship fields (`pick`, `taxonomy`, `boolean`):** Automatically rendered as `<select>` dropdowns populated with available options.
* **Numeric & Currency fields (`number`, `currency`):** Rendered with a comparison operator selector (`=`, `>`, `>=`, `<`, `<=`) paired with a numeric input.
* **Text fields & Keyword Search:** Rendered as text inputs for partial matching.

### 4. PODS Smart Field (`pods-block-suite/smart-field`)
A context-aware field block with dynamic POD and field dropdowns (including WordPress standard fields supported by the selected POD). Automatically adapts its output based on field type or manual **Display As** override:
* **Media:** Images (with selectable sizes and optional media file links for lightboxes), HTML5 `<video>`, `<audio>`, and file download links.
* **Social Icons:** Automatically detects platforms (Instagram, LinkedIn, GitHub, X/Twitter, Facebook, YouTube, TikTok, Bluesky, Mastodon, Pinterest, Vimeo, Email, and Website) from single or repeatable URL fields, with Brand Colors or inherited text color, size, and corner radius (`0%–50%`) controls.
* **oEmbed:** Responsive 16:9 embeds for YouTube, Vimeo, and other WordPress oEmbed providers.
* **Color Picker:** Renders colors as customizable square swatches, circle swatches, or hex labels.
* **Relationships:** Single or multi-item links/lists displaying any chosen field from the related post.
* **Multi-item Controls:** Optional `Max Items` limit (`0 = unlimited`), layout modes (`row`, `column`, `ul`, `ol`), and Flexbox `justify-content` alignment.
* **URLs & Text:** Clickable links (with custom label and `target="_blank"` options) or semantic HTML text wrappers (`<div>`, `<p>`, `<h2>`, `<h3>`, `<span>`).

## Architecture & Shared Modules (v1.2.0+)
To keep the codebase DRY and maintainable across all blocks, shared logic is organized under `src/shared/` and `includes/`:
* **`src/shared/hooks/usePodsSchema.js`:** Fetches and caches the `/pods-block-suite/v1/schema` endpoint, auto-selects context PODs, and formats dropdown options.
* **`src/shared/components/SortControls.js`:** Shared `Sort Direction` (`DESC`, `ASC`, `RAND`) and grouped `Order By` selector.
* **`src/shared/components/LayoutControls.js`:** Shared Flexbox layout (`row`/`column`, `justify-content`, `columns`) and clickable card (`linkToPost`) inspector controls.
* **`src/shared/styles/_flex-layout.scss`:** Shared Dart Sass mixin (`@include pods-flex-container(...)`) for responsive multi-column Flexbox grids and FSE margin normalization.
* **`includes/render-helpers.php`:** Server-side helpers for layout attribute sanitization, PODS field type resolution, and `WP_Block` contextual rendering (`pods_block_suite_render_contextual_inner_blocks()`).

## Installation

### Standard WordPress Installation
1. Download the latest `pods-block-suite.zip` from [Releases](https://github.com/hliljegren/pods-block-suite/releases) (or clone this repository into `/wp-content/plugins/`).
2. Ensure the **PODS Framework** plugin is installed and activated.
3. Activate **PODS Block Suite** through the **Plugins** menu in WordPress.

### Development Setup
If you want to modify the block source files inside `src/`:
```bash
cd wp-content/plugins/pods-block-suite
npm install
npm start       # Development watch mode
npm run build   # Build production assets
npm run plugin-zip # Create a clean WordPress plugin zip