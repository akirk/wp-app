# Repository guidance

## Page assets

- Do not handwrite `<style>` or `<script>` blocks in PHP templates or output methods, or create style elements from JavaScript. Avoid inline `style` attributes and event-handler attributes such as `onclick`.
- Put static CSS and JavaScript in `assets/`. Load app-page assets with `wp_app_enqueue_style()` and `wp_app_enqueue_script()`, passing the app scope explicitly. Use `wp_app_get_asset_url()` for framework assets and `WP_APP_VERSION` to version them.
- For WordPress admin pages, use the WordPress enqueue APIs. Pass dynamic data through HTML data attributes or a separate configuration response that an external script reads.
- When changing existing inline output, check the rendered page as well as the source. Shared head hooks can emit tags that are not present in a template.

## Gutenberg content in app templates

- Render `the_content` before `wp_app_head()` so block styles, support styles, and script module dependencies are discovered before the head is printed. Keep `wp_app_body_close()` for footer assets and native lightbox markup.
- WpApp removes `global-styles`, including core flex and grid layout rules. Apps rendering blocks must supply those core rules separately; loading gallery/image block CSS alone does not restore them. Call `wp_app_enqueue_block_styles()` before the head. See [Rendering Gutenberg content](docs/getting-started.md#rendering-gutenberg-content).
- Verify actual CSS rules and browser behavior. `*-inline-css` can contain full WordPress block stylesheets, not only overrides; the presence of those tags alone does not prove gallery layout or lightbox behavior works.
