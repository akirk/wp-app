# CSS and JavaScript Assets

[Documentation index](README.md)

Keep static CSS and JavaScript in your plugin's `assets/` directory. Register app-page files with `wp_app_enqueue_style()` and `wp_app_enqueue_script()` before calling `wp_app_head()` or including a header that calls it.

## Load App Assets

For example, register files from your plugin's main PHP file after loading the framework:

```php
wp_app_enqueue_style(
    'my-app',
    plugins_url( 'assets/app.css', __FILE__ ),
    [],
    '1.0.0',
    'my-app'
);
wp_app_enqueue_script(
    'my-app',
    plugins_url( 'assets/app.js', __FILE__ ),
    [],
    '1.0.0',
    true,
    'my-app'
);
```

The script's `true` argument prints it in the footer; keep `wp_app_body_close()` in the template. Use your plugin version for your own files. For assets shipped with the framework, resolve their URL with `wp_app_get_asset_url( 'filename.js' )` and use `WP_APP_VERSION` as the version.

## App Scope

Pass the app's URL path as the scope, as above, or use `[ 'app' => 'my-app' ]`. The helper attaches output to that app's scoped hook, so registering assets before render time does not cause them to print in another app.

Use `[ 'app' => 'global' ]` for shared framework assets intended to print for every WpApp. The inline helper APIs, `wp_app_add_inline_style()` and `wp_app_add_inline_script()`, also accept app scopes, but static CSS and JavaScript belong in files.

For WordPress admin pages, use the WordPress enqueue APIs instead.

## Dynamic Values

Pass dynamic values through escaped HTML data attributes or a separate configuration response that the external script reads:

```php
<div id="my-app" data-record-id="<?php echo esc_attr( $record_id ); ?>"></div>
```

Read this from `assets/app.js`:

```js
const root = document.getElementById('my-app');
const recordId = root.dataset.recordId;
```

Avoid handwritten `<style>` and `<script>` blocks, inline `style` attributes, event-handler attributes such as `onclick`, and style elements created from JavaScript. Bind event listeners in the external script.

## Verify Asset Output

Check the rendered page as well as the template source: shared head hooks can emit additional tags. Confirm that the files load on the intended app, that footer scripts run, and that another app does not receive the same scoped assets.

## Related Documentation

- [Templates](templates.md) — page helpers and rendering order
- [Colors and Dark Mode](colors.md) — CSS custom properties for app styling
- [App Themes](themes.md) — loading assets for a selected theme
- [Rendering Gutenberg Content](gutenberg-content.md) — core block CSS and render-discovered dependencies
