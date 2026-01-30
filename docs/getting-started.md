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

- [Minimal App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/minimal-app.json) - The simplest possible WpApp
- [Community App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/community-app.json) - Full-featured example with database, REST API, and admin integration

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
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<title><?php echo wp_app_title( 'My App' ); ?></title>
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
| `wp_app_title( 'Page' )` | Page title with site name |
| `wp_app_language_attributes()` | Language attributes for `<html>` tag |
| `wp_app_enqueue_style( $handle, $src )` | Enqueue styles for app pages |
| `wp_app_enqueue_script( $handle, $src )` | Enqueue scripts for app pages |

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
