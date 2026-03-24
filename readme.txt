=== Bridge Builder ===
Contributors: mouad
Tags: page-builder, react, spa
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later

A custom React-based page builder that renders as a fast SPA on the front end without going headless.

== Description ==

Bridge Builder is a lightweight WordPress page builder that uses React for both the admin editor and the visitor-facing front end.

**Admin:** Full drag-and-drop editor with live preview, undo/redo, and responsive viewport toggle.

**Front End:** PHP renders semantic HTML for SEO, then a tiny React runtime (~25KB) hydrates the page for SPA-like interactivity and smooth page transitions.

**Builder pages = theme replacement:** For any page/post built with Bridge Builder, the theme template is not loaded. The plugin loads its own minimal template (`templates/full-page.php`) via `template_include`, so there is no theme header/footer/main, no CSS hiding, and no duplicate content. See THEME_COMPAT.md.

**Theme-friendly front end:** On the front end, only layout CSS (display, flex, gap, padding, margin, dimensions) is output so your theme controls colors, typography, and shadows. Builder content is wrapped in `.bb-content`; themes can target it (e.g. `.bb-content .bb-PostTitle`) to style builder blocks without being overwritten.

== Privacy & third-party services ==

Bridge Builder itself does not phone home or collect analytics. It can, however, connect to third-party services **only when you configure them**:

- **AI providers (optional):** If you enter an API key in *Bridge Builder → Settings*, prompts and layout requests are sent to the selected AI provider (NVIDIA NIM, OpenAI, Claude, Gemini, or a custom OpenAI-compatible endpoint). API keys are stored in WordPress options on your server and are never exposed to the front end.  
  - For details, see each provider’s own terms and privacy policy (for example, `build.nvidia.com`, `platform.openai.com`, `console.anthropic.com`, `aistudio.google.com`, or your custom endpoint).
- **Google Fonts (optional):** When the Design System selects Google Fonts, the plugin loads fonts from `fonts.googleapis.com`. No additional tracking is added by the plugin; requests go directly from the visitor’s browser to Google’s font CDN.
- **Embeds (optional):** Video and map widgets can embed content from YouTube, Vimeo, and Google Maps using URLs you provide. Those services may set cookies or log requests according to their own policies.
- **API Data widget (optional):** The *API Data* widget fetches JSON from a URL you configure, in the visitor’s browser, and renders a list or cards. Use it only with APIs you trust; the plugin does not modify or proxy those requests.
- **Contact Form:** The built-in contact form sends email via `wp_mail()` to the address you configure in the widget props. Submissions are not stored by the plugin; they are delivered as email.

If your site uses any of these optional features, you should mention them in your site’s privacy policy (for example, that third-party services like AI providers, Google Fonts, YouTube/Vimeo, Google Maps, or APIs you configure may receive data such as IP address and user agent when content is loaded).

== Development ==

Bridge Builder is developed in a split layout:

- The **WordPress plugin** lives in the `plugin/bridge-builder/` directory (PHP, templates, built JS/CSS).  
- The **unminified React/TypeScript source and build tooling** live in the `frontend/` directory of the project (not shipped in the plugin folder by default).

The full source code and build steps are documented in the project root `README.md` (including how to build `dist/builder.js` and `dist/runtime.js` from `frontend/src`).  
When publishing this plugin on WordPress.org, make sure the plugin’s readme there links to the public repository for this project so reviewers and users can inspect the readable source and build configuration.

== Installation ==

1. Upload the `bridge-builder` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Bridge Builder in the admin menu to start building.

== Changelog ==

= 1.4.3 =
* Builder: section/element toolbar no longer hidden behind header (z-index). Drag-and-drop into empty "Add block" column now inserts the element inside that column.

= 1.4.2 =
* Section right panel: "Content width" editor — Box/Full toggle with max-width dropdown (1400/1200/1060/880/740px). Updates the inner Container directly.

= 1.4.1 =
* UX: Add panel layout picker redesigned — two-step flow (column count tabs 1–6, then compact ratio grid). Replaced long scrolling list.
* UX: Row editor in right panel redesigned — breakpoint tabs, column count tabs, compact 2-col ratio grid, gap dropdown, icon alignment buttons.

= 1.4.0 =
* Layout builder rebuilt per spec: 4-layer architecture (Section > Container > Row > Column). All new sections auto-insert a Container centering wrapper. Row now uses CSS grid with 23 ratio presets across 1–6 columns (grouped by count in Add panel). Per-breakpoint grid (Desktop/Tablet/Mobile tabs in Row editor). Column hide-on-tablet/mobile toggles. Section HTML tag selector. Container max-width dropdown (1400/1200/1060/880/740/full). PHP CSS generator: tablet media query output + gridTemplateColumns support.

= 1.3.9 =
* Design System Studio simplified for average users: Google Fonts dropdowns (CDN load), shadow/motion/border presets only (no raw input), button hover/active colors and live preview states, typography scale preset (Small/Medium/Large).

= 1.3.8 =
* Phase 3: Header/Footer templates now support conditions + priority in admin and runtime selection uses conditions first with active-template fallback.
* Plugin-check fixes: sanitized template condition IDs/custom endpoint URL input and escaped the conditions edit link output; added WooCommerce hook PHPCS annotation in renderer.

= 1.3.7 =
* Advanced tab: CSS and JS fields now use an IDE-like code editor (CodeMirror 6) with syntax highlighting, line numbers, autocomplete (Ctrl+Space), and One Dark theme.

= 1.3.6 =
* Right panel reorganized: Editor and Advanced tabs. Editor shows properties, styles, repeaters; Advanced shows custom ID, class name, CSS, and JS for the selected component.
* Add panel: new "Pro" section for Phase 7 components (Conditional Display, Code Embed, API Data).

= 1.3.5 =
* Per-component custom code: every component can now have a custom ID, class name, and optional CSS and JS. Right panel "Custom HTML & code" section (ID, Class name, CSS, JS) applies to the selected component; CSS and JS are scoped to the component root (PHP and runtime).

= 1.3.4 =
* Phase 7 — Developer & power user: Conditional display (show/hide by device, logged-in, role, date, query param; match all/any), Code embed pro (HTML + scoped CSS + optional JS; allow script toggle), API data widget (REST URL, list path, field mapping, list/cards layout).

= 1.3.3 =
* Phase 6 — App/business widgets: Data table (sortable, filterable, responsive stack), Timeline (vertical/horizontal, date/title/content items), Before/after image slider (drag-handle compare), Chart (bar/line/pie from labels and datasets).

= 1.3.2 =
* Phase 5 — Marketing & Conversion: Popup trigger (time/exit-intent/scroll/click, frequency), Comparison table (feature matrix, sticky column, plan highlight), Pricing calculator (range sliders, checkboxes, live total), Lottie animation (JSON URL, loop, hover/scroll triggers).
* Auto-add widgets: clicking any widget in the Add panel auto-creates Section > Row > Column if none is selected.

= 1.3.1 =
* Reverted builder UI to previous design (toolbar with viewport in center, Save button, light left panel, no bottom status bar).

= 1.3.0 =
* Builder UI redesign (Stitch-inspired): toolbar "Editing: [Page Name]", Publish/Settings; dark left sidebar; bottom status bar. (Reverted in 1.3.1.)

= 1.2.4 =
* Fix parse error (unexpected token "{"): removed invalid empty(expression) usage — Add to Cart, archive title/description, pagination, excerpt, API key checks now use variable + comparison for PHP compatibility.

= 1.2.3 =
* Phase 2.1: Dynamic tokens in Query Loop — Text, Heading, Image, Button support {post.title}, {post.excerpt}, {post.image}, {post.url}, {post.date}, {post.readingTime}, {author.name}, {author.url}, {meta.KEY}. PHP renderer and REST enrichment.
* Contact form: select, radio, and checkbox (multi) field types with comma-separated options in builder; REST accepts array values for email body.

= 1.2.2 =
* Frontend styling: Settings option "Theme (layout only)" vs "Builder (full design)". When Builder is selected, builder CSS is output in the footer with scoped selectors so the design is not overridden by the theme (no flash of theme look).

= 1.2.1 =
* Phase 1 dynamic content: Query Loop (generic loop with template), Post meta widgets (Post Title, Excerpt, Featured Image, Date, Author, Reading Time), Archive widgets (Archive Title, Description, Pagination, No Results). Build blog/archive templates in the builder.

= 1.1.6 =
* Save button matches Restore style; revision card layout fix; AI loading overlays; polished AI modal.

= 1.1.4 =
* AI Settings: show only selected provider's API key input (dropdown + conditional field).
* Toolbar: removed History icon (revisions via left panel only); Shadcn-style toolbar redesign.
* Revisions panel: simplified header (removed redundant icon).

= 1.1.3 =
* AI: Multi-provider support. Choose NVIDIA NIM, OpenAI, Claude, Gemini, or a custom OpenAI-compatible endpoint.
* Bridge Builder → Settings: Provider dropdown + API key fields for each provider. Add keys for any providers you use.
* Custom option: enter your own endpoint URL, API key, and model (e.g. OpenRouter, Groq, local LLMs).

= 1.1.2 =
* AI page generation: describe a page in text and generate the layout via NVIDIA NIM.
* New "Generate page with AI" (Sparkles) button in the builder toolbar; modal with prompt textarea.
* Bridge Builder → Settings: NVIDIA API Key field (get key from build.nvidia.com). Key is stored server-side only.
* REST: POST /generate-page, GET /ai-status. Replace page tree with generated result (like Import).

= 1.1.1 =
* UI overhaul (1.1): design tokens (8px radius, panel shadows), section headers, Layout pills (Stack/Row/Column/Grid), Wrap Yes/No.
* Border radius: link/unlink corners, slider when linked, per-corner inputs (TL/TR/BL/BR) when unlinked.
* Spacing: sliders for padding and margin when linked.
* Typography: sliders for font size and line height.

= 1.0.23 =
* Builder: Canvas "+ Add block" popup — 3 icons per row, icon on top and label below (same as sidebar search).

= 1.0.22 =
* Builder: Search elements grid — 3 icons per row, icon on top and label below, more spacing.

= 1.0.21 =
* Builder: single collapse/expand control on left rail only (controls both panels); removed collapse from toolbar and right panel.
* Builder: Add panel widget list reverted to original vertical list (icon + label per row).

= 1.0.20 =
* Builder: single toolbar button to collapse/expand both side panels.
* Builder: Add panel widget picker — 3 icons per row, larger cards and clearer labels.

= 1.0.19 =
* Builder: removed Header/Footer from admin menu and front-end output (focus on page builder).
* Builder: larger preview — narrower side panels, canvas max-width 1400px, less padding.
* Builder: add-block picker uses search bar instead of long dropdown (canvas + Add panel).

= 1.0.18 =
* Architecture: full template override for builder pages. Theme template is no longer loaded; plugin loads its own minimal template (templates/full-page.php) via template_include. No theme execution, no CSS hiding, no duplicate content or header/footer. Pure theme-replacement model for builder pages.

= 1.0.17 =
* Global header/footer overwrite (Elementor-style): block themes get empty header/footer template parts via pre_get_block_file_template so the theme never outputs them; BB outputs its own at wp_body_open/wp_footer.
* Theme compat: bridge_builder_has_global_header(), bridge_builder_has_global_footer(); THEME_COMPAT.md and add_theme_support( 'bridge-builder-header-footer' ) for classic themes.

= 1.0.16 =
* Fix: duplicate page content — only replace the_content in main query loop and output once per request (static flag).
* Fix: two footers — filterable CSS selectors to hide theme header/footer; added block-theme template-part selectors.

= 1.0.15 =
* Fix: no duplicate page content when using global header (only replace the_content when in the main loop).
* Fix: hide theme header/footer when BB global header/footer are active so only one header and one footer show.

= 1.0.14 =
* Fix: builder loads on Edit with Builder (correct admin hook toplevel_page_bridge-builder; enqueue only when post_id present).
* Plugin icon: use bb logo.png as assets/icon.png for sidebar menu.

= 1.0.13 =
* Admin: Bridge Builder in wp-admin sidebar with icon (assets/icon.png or dashicon); submenus Pages, Sections, Header, Footer, Popups, Templates.
* Global content: CPTs bb_section, bb_header, bb_footer, bb_popup, bb_template; list screens with Add new, Edit with Builder, Set as active (header/footer), Trash.
* REST: GET /items?type=..., GET|POST /options (activeHeaderId, activeFooterId); save/load for all builder CPTs; draft save (body.draft) and GET ?draft=1.
* Front end: active header at wp_body_open, active footer and popups in wp_footer (with scoped CSS).
* Preview: _builder_json_draft; Preview button in toolbar (posts/pages) saves draft and opens front-end preview; content and page-data use draft when preview=1 and valid nonce.
* Templates: "Add new from template" on Pages dashboard; creates draft page from bb_template and opens in builder.

= 1.0.12 =
* P2 widgets: Team Member (name, role, image, bio, social[]), Google Maps (address, zoom, height), Posts Grid (postType, postsPerPage, columns, show*; WP_Query + tree enrichment), Contact Form (fields[], submitText, email; REST POST + wp_mail).
* REST API: POST /contact-form endpoint; page-by-url returns postId for SPA; tree enrichment for Posts Grid.
* Runtime: updates wpBuilderData.postId on SPA navigation.

= 1.0.10 =
* Menu widget: dropdown in edit panel to select WordPress nav menu (menus passed via wpBuilderData).
* Custom HTML widget: code-editor-style textarea (monospace, dark theme, larger area) for raw HTML.

= 1.0.9 =
* P3 (power user) widgets: Toggle, List (items repeater), Blockquote, Progress Bar, Video Box, Image Box, Button Group (buttons repeater), Countdown, Menu (wp_nav_menu), Shortcode (do_shortcode), Custom HTML (wp_kses_post).
* Column/Container/Grid allowedChildren updated for all new widgets.

= 1.0.8 =
* List widgets use array-of-objects in props (no JSON strings): Tabs, Accordion, Gallery, Carousel, Social Icons, Pricing Table (props.tabs, .items, .images, .slides, .networks, .plans); legacy *Json fallback in PHP and shared components.
* Repeater UIs in Right Panel for all list widgets (add/remove/edit items; no raw JSON textareas).
* Add panel: collapsible section headers (Add section, Add row to section, Content, Media, Interactive) for better UX.

= 1.0.7 =
* Footer always outputs valid #bb-page-data (empty page tree if JSON invalid) so runtime never sees "No page data found".
* Runtime fallback: fetch tree from page-by-url API when inline script missing or stripped.

= 1.0.6 =
* Runtime always uses createRoot (never hydrate) so Tabs/Accordion and complex widgets never cause hydration crashes; PHP HTML remains for SEO/first paint.

= 1.0.5 =
* Tabs/Accordion: fix hydration mismatch (add id, data-bb-type to match PHP; Accordion use ▼ span to match PHP; suppressHydrationWarning).

= 1.0.4 =
* Front end: use queried object ID when not in the loop so builder content replaces the_content in block/custom themes.
* Runtime creates #bb-render and injects into main/content when theme does not output it (content always shows).
* Body class uses queried object for builder pages.

= 1.0.3 =
* Page data output in wp_footer so runtime always finds it; relaxed the_content filter.
* API and frontend never return/wipe valid tree (normalize legacy format, guard null).
* Content filter always returns string (fix PHP 8.1 strpos null deprecation).

= 1.0.2 =
* Tabs and Accordion: repeater UI in builder (no raw JSON). Fix front-end visibility and hydration.

= 1.0.1 =
* New widgets: Icon, Icon Box, Video (self-hosted/YouTube/Vimeo), Tabs, Accordion, Testimonial.
* Added business and advanced component categories.
* PHP renderers for all new widgets.

= 1.0.0 =
* Initial release.

