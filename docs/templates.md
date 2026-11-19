# Templates

[Documentation index](README.md)

Routes select PHP templates from the app's template directory. See [Routing](routing.md) for naming conventions, explicit template names, and route parameters; [App Themes](themes.md) describes template overrides and fallback.

## Page Structure

A template owns the HTML document. Use the WpApp helpers so navigation, scoped assets, and footer integrations are printed:

```php
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php wp_app_the_title( 'My App' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
    <?php wp_app_body_open(); ?>
    <h1>Welcome to My App!</h1>
    <?php wp_app_body_close(); ?>
</body>
</html>
```

Prepare assets and render block content before calling `wp_app_head()`, including when that call lives in a shared header partial. Keep `wp_app_body_close()` at the end for footer scripts and native lightbox markup. The `wp-app-body` class enables the framework's default app color styles.

Escape dynamic text with `esc_html()`, attributes with `esc_attr()`, and URLs with `esc_url()`. The printing title and language helpers already escape their output.

## Template Helpers

| Function | Description |
|----------|-------------|
| `wp_app_head()` | HTML head with WordPress hooks |
| `wp_app_body_open()` | Body open hook (renders admin bar) |
| `wp_app_body_close()` | Body close hook |
| `wp_app_the_title( 'Page' )` | Print the page title with the site name, escaped |
| `wp_app_get_title( 'Page' )` | The same title as plain text, for you to escape |
| `wp_app_language_attributes()` | Print language attributes for the `<html>` tag |
| `wp_app_enqueue_style( $handle, $src, $deps, $ver, $scope )` | Enqueue styles for app pages |
| `wp_app_enqueue_script( $handle, $src, $deps, $ver, $in_footer, $scope )` | Enqueue scripts for app pages |
| `wp_app_enqueue_block_styles()` | Enqueue core default styles for Gutenberg content before the head |

For pages displaying WordPress block content, see [Rendering Gutenberg content](gutenberg-content.md) for CSS setup and rendering order.

## Related Documentation

- [Assets](assets.md) — CSS, JavaScript, app scope, and dynamic data
- [Colors](colors.md) — styling with admin color tokens
- [BaseApp Pattern](baseapp.md#using-storage-in-templates) — accessing stored data
