=== PODS Block Suite ===
Contributors:      hliljegren
Tags:              pods, gutenberg, blocks, custom-fields, dynamic-content
Requires at least: 6.5
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        1.3.1
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A complete suite of Gutenberg blocks for PODS that lets you display, loop, and filter custom fields directly in the Block Editor without legacy PODS Templates.

== Description ==

**PODS Block Suite** bridges the gap between the PODS Framework and the WordPress Block Editor (Gutenberg). Instead of writing HTML/Magic Tag templates inside PODS, you can visually compose layouts using native WordPress blocks combined with four specialized PODS blocks:

1. **PODS Item Container (`pods-block-suite/item-container`)**
   Provides a specific Post ID context (or inherits the current post/page context) to all nested inner blocks. Ideal for showcasing a single featured POD item anywhere on a page.

2. **PODS Related Item Container (`pods-block-suite/related-item-container`)**
   Loops through single or one-to-many PODS relationship fields (`pick`) and renders inner blocks for each related item. Supports Flexbox column/row grids, configurable column counts, item limits, and clickable cards.

3. **PODS Item List Container (`pods-block-suite/item-list-container`)**
   Queries and lists items from any selected POD with configurable sorting, pagination, responsive Flexbox grids, and **interactive frontend visitor filters**. Administrators can select which fields visitors can filter by:
   * **Selection & Relationship fields (`pick`, `taxonomy`, `boolean`):** Automatically rendered as `<select>` dropdowns populated with available options.
   * **Color fields (`color`):** Filter by named HSL color families, using a dropdown or optional swatches for each enabled color field. Customize labels and matching ranges with the `pods_block_suite_color_list` and `pods_block_suite_color_ranges` filters.
   * **Numeric & Currency fields (`number`, `currency`):** Rendered with a comparison operator selector (`=`, `>`, `>=`, `<`, `<=`) paired with a numeric input.
   * **Text fields & Keyword Search:** Rendered as text inputs for partial matching.

4. **PODS Smart Field (`pods-block-suite/smart-field`)**
   A context-aware field block with dynamic POD and field dropdowns (including WordPress standard fields such as `post_thumbnail`, `post_title`, and `post_content`). Automatically adapts its output based on field type or manual override:
   * **Media:** Images (with selectable sizes and optional media file links for lightboxes), HTML5 `<video>`, `<audio>`, and file download links.
   * **oEmbed:** Responsive 16:9 embeds for YouTube, Vimeo, and other WordPress oEmbed providers.
   * **Color Picker:** Renders colors as customizable square swatches, circle swatches, or hex labels.
   * **Relationships:** Single or multi-item links/lists displaying any chosen field from the related post.
   * **Layout Controls:** Row (default) or column Flexbox layout and alignment for every field. Multi-value media, relationship, and social fields can also use list layouts and item limits.
   * **URLs & Text:** Clickable links (with custom label and `target="_blank"` options) or semantic HTML text wrappers (`<div>`, `<p>`, `<h2>`, `<h3>`, `<span>`).

Smart Fields without an explicitly saved layout now use the row default, including fields created before version 1.3.0. Links inside PODS blocks do not navigate in the editor; they remain active on the frontend.

== Installation ==

### Standard WordPress Installation
1. Upload the `pods-block-suite` folder to the `/wp-content/plugins/` directory (or clone the repository directly into `/wp-content/plugins/`).
2. Ensure the **PODS Framework** plugin is installed and activated.
3. Activate **PODS Block Suite** through the **Plugins** menu in WordPress.

### Development Setup
If you want to modify the block source files inside `src/`:
1. Navigate to the plugin directory in your terminal: `cd wp-content/plugins/pods-block-suite`
2. Install dependencies: `npm install`
3. Start development watch mode: `npm start`
4. Build production assets: `npm run build`

== Frequently Asked Questions ==

= Do I need to create PODS Templates to use these blocks? =
No. All layout and markup are handled directly inside the WordPress Block Editor using inner blocks and the **PODS Smart Field** block.

= How does context work when nesting blocks? =
All container blocks (`Item Container`, `Related Item Container`, and `Item List Container`) propagate `postId` and `postType` via WordPress's `render_block_context` filter. You can wrap **PODS Smart Field** blocks inside core `Group`, `Columns`, or `Stack` blocks without losing the item context.

= How should I embed YouTube or Vimeo videos with PODS Smart Field? =
Store the video URL in a standard PODS **Website** or **Plain Text** field, then select that field in **PODS Smart Field** and set **Display As** to **oEmbed (YouTube / Vimeo / Embed)**.

= Is the plugin translation-ready? =
Yes. All interface strings use the `pods-block-suite` text domain and `@wordpress/i18n` / WordPress PHP localization functions.

== Changelog ==

= 1.0.0 =
* Initial release of PODS Block Suite.
* Added **PODS Item Container** block with custom Post ID override.
* Added **PODS Related Item Container** block with Flexbox grid layout and clickable cards.
* Added **PODS Item List Container** block with pagination and dynamic frontend visitor filters.
* Added **PODS Smart Field** block supporting Media, oEmbed, Color Swatches, Relationships, Links, and WordPress standard fields.
* Added REST API endpoint (`/wp-json/pods-block-suite/v1/schema`) for live POD and field discovery in the Block Editor.
* Full internationalization (i18n) support under the `pods-block-suite` text domain.

= 1.1.0 =
* Added PODS Framework dependency check with an admin error notice and `Requires Plugins: pods` header.
* Added **Social Icons** display mode to **PODS Smart Field** with automatic platform detection, brand/inherit color styles, icon size, and border-radius controls.
* Added **Max Items** (`limit`) control to **PODS Smart Field** for repeatable fields, media galleries, and relationships.
* Added dynamic **Order By** (supporting PODS numeric/text fields and post-type-supported WordPress fields) and **Sort Direction** (`DESC`, `ASC`, `RAND`) to both **PODS Item List Container** and **PODS Related Item Container**.
* Added Flexbox **Justify Content** alignment and full-width responsive grid handling across container and field blocks.
* Fixed FSE `is-layout-flow` top margin alignment issues on multi-column grids.

= 1.2.0 =
* Refactored shared editor logic into reusable modules (`usePodsSchema` hook, `SortControls`, and `LayoutControls` components) under `src/shared/`.
* Consolidated multi-column Flexbox grid and clickable card styles into a shared Dart Sass partial (`src/shared/styles/_flex-layout.scss`).
* Extracted server-side attribute sanitization, PODS field type detection, and contextual inner-block rendering into `includes/render-helpers.php`.
* Enhanced **PODS Smart Field** to detect single vs. repeatable/multi-value fields (`isMultiple`) via the schema REST endpoint, automatically hiding multi-item layout controls for single-value fields such as `post_thumbnail`.

= 1.3.0 =
* Added customizable color-family filters and metadata-backed color caching.
* Restored Smart Field layout controls for single fields and changed the default layout to row.
* Prevented links within PODS blocks from navigating in the block editor.
* Fixed numeric comparison options, keyword searching, and post visibility in item lists.
* Improved clickable card overlays for nested links.

= 1.3.1 =
* Added optional color swatches for item list color filters.