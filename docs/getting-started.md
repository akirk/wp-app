# Getting Started

Build modern web applications on WordPress with routing, admin bar integration, and theme isolation.

## Why WordPress for Web Applications?

WordPress isn't just for blogs. It's a powerful foundation for web applications:

- **Built-in User Management** - Complete authentication with roles and permissions
- **Universal Hosting** - Deploy anywhere WordPress runs
- **Security & Updates** - Automatic security updates and proven practices
- **Rich Ecosystem** - 60,000+ plugins for payments, email, analytics, and more
- **Admin Interface** - Professional backend without building from scratch
- **Global Ready** - Built-in i18n, multisite, and SEO optimization

**WpApp** makes it easy to build clean applications on WordPress while leveraging all these benefits.

## Try it Now

Try the examples instantly in your browser with WordPress Playground:

- [Minimal App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app-examples/main/minimal-app.json) - The simplest possible WpApp
- [Community App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app-examples/main/community-app.json) - Full-featured example with database, REST API, and admin integration

## Installation

```bash
composer require akirk/wp-app
```

## Quick Start (Minimal Example)

The simplest possible WpApp requires just 3 lines:

```php
<?php
/**
 * Plugin Name: Minimal App
 */

require_once __DIR__ . '/vendor/autoload.php';
use WpApp\WpApp;

$app = new WpApp( __DIR__ . '/templates', 'minimal' );
$app->init();
```

Create `templates/index.php`:

```php
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<title><?php wp_app_the_title( 'My App' ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>
	<h1>Welcome to My App!</h1>
	<?php wp_app_body_close(); ?>
</body>
</html>
```

Your app is now available at `/minimal`!

## Template Functions

Helper functions for use in templates:

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

## Rendering Gutenberg content

Render post content through `the_content` **before** calling `wp_app_head()` (or including a header partial that calls it), then print the rendered HTML in the body. Set up the post context first if the blocks need it.

```php
<?php
wp_app_enqueue_block_styles();
$content = apply_filters( 'the_content', $post->post_content );
require __DIR__ . '/partials/header.php'; // Calls wp_app_head().
echo $content; // Already rendered through the_content, as in a theme template.
require __DIR__ . '/partials/footer.php'; // Calls wp_app_body_close().
```

Rendering blocks queues their styles, script modules, and block support styles. With an active block theme, WordPress prints module import maps and some block support styles in the head. Rendering content afterward can leave native image lightboxes without their interactivity dependencies, even when the lightbox markup and script appear in the page. Keep `wp_app_body_close()` so WordPress can print the lightbox overlay and footer assets too.

WpApp also removes the active theme's `global-styles` stylesheet for theme isolation. That stylesheet includes shared WordPress layout rules such as `.is-layout-flex { display: flex; }`, wrapping, and grid rules. A gallery can therefore stack vertically even when its full `wp-block-gallery` and `wp-block-image` styles are present. Call `wp_app_enqueue_block_styles()` from the app template before the head, as shown above, to restore core default CSS without restoring the active theme's global styles.

The helper uses WordPress's generated core CSS and enqueue API, requires WordPress 5.9+, and is safe to call repeatedly. It applies to the current request, so call it only on pages displaying block content. It does not render content or enqueue every block's assets: keep rendering through `the_content` before the head so WordPress discovers those dependencies. Templates retain control of the page structure.

When diagnosing a broken gallery, inspect the actual CSS rules and computed styles, not just stylesheet IDs. WordPress can inline the complete block stylesheets under `wp-block-gallery-inline-css` and `wp-block-image-inline-css`; those IDs do not imply that the styles are only overrides. Verify flex display and wrapping on the gallery, column sizing on its images, the interactivity entry in the module import map, and the lightbox script and overlay. Confirm the layout and zoom behavior in the browser.

## Testing with WordPress Playground

You can run your plugin locally without a full WordPress installation using the Playground CLI:

```bash
npx @wp-playground/cli@latest server --auto-mount --login
```

Run this from your plugin directory. It will start a local WordPress instance with your plugin automatically mounted and activated.

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Next Steps

- [Configuration](configuration.md) - All configuration options
- [Routing](routing.md) - URL patterns and parameters
- [Masterbar](masterbar.md) - Admin bar customization
- [Access Control](access-control.md) - Authentication and permissions
- [BaseApp Pattern](baseapp.md) - Structured architecture for larger apps
- [Offline PWA Support](offline-pwa.md) - Installable apps and offline caching
- [Client-Side Encryption](encryption.md) - Encrypted-field app pattern
- [Abilities](abilities.md) - Exposing your app to assistants and other apps
