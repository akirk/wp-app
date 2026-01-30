# WpApp Framework

Build modern web applications on WordPress with routing, admin bar integration, and theme isolation.

## Why WordPress for Web Applications?

WordPress isn't just for blogs. It's a powerful foundation for web applications.

**For developers:**
- **Built-in User Management** - Authentication, roles, and permissions out of the box
- **Admin Interface** - Professional backend without building from scratch
- **Security & Updates** - Automatic security patches and proven practices
- **Global Ready** - Built-in i18n and multisite support

**For users:**
- **Universal Hosting** - Deploy anywhere WordPress runs, from shared hosting to cloud
- **Familiar Environment** - Manage your app alongside your existing WordPress site

## Try it Now

Try the examples instantly in your browser with WordPress Playground:

- [Minimal App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/minimal-app.json) - The simplest possible WpApp
- [Community App](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/community-app.json) - Full-featured example with database, REST API, and admin integration

## Features

- **URL Routing** - Pattern-based routing with WordPress rewrite rules
- **Theme Isolation** - Clean HTML output separate from your WordPress theme
- **Admin Bar Integration** - WordPress-style navigation for your app
- **Access Control** - WordPress capability-based authentication
- **BaseApp Pattern** - Structured architecture for complex applications
- **BaseStorage Pattern** - Database abstraction with schema management using `dbDelta`

## Quick start

You can use this command to scaffold your new plugin:

```bash
composer create-project akirk/create-wp-app my-app
```

Which gives you this little wizard:

```
Creating WpApp plugin: my-app
----------------------------------------
Plugin name [My App]: 
Namespace [MyApp]: 
Author []: 
URL path [my-app]: 
Setup type:
  [1] Minimal - simple WpApp setup
  [2] Full - with BaseApp structure
Choose [1]: 
✓ Updated plugin-name.php
✓ Updated templates/index.php
✓ Renamed plugin-name.php to my-app.php
✓ Removed src/ directory (not needed for minimal setup)
✓ Updated composer.json
✓ Cleaned up setup scripts
Done! Your plugin is ready.
Next steps:
  Option A: Run locally with WordPress Playground
    npx @wp-playground/cli@latest server --auto-mount=my-app --login
  Option B: Install in WordPress
    1. Activate the plugin in WordPress
    2. Visit /my-app/ to see your app
```
Which gives you something like this:

<img width="788" height="681" alt="create-wp-app" src="https://github.com/user-attachments/assets/0a7cfabd-5cd9-40a4-bb2f-2b45d3c57e34" />

Which you can then build on.

## Installation

```bash
composer require akirk/wp-app
```

## Quick Start

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

## Local Development

Run your plugin locally without a full WordPress installation:

```bash
npx @wp-playground/cli@latest server --auto-mount --login
```

This starts a local WordPress instance with your plugin mounted and activated.

## Documentation

| Topic | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | Installation, quick start, template functions |
| [Configuration](docs/configuration.md) | All configuration options |
| [Routing](docs/routing.md) | URL patterns, parameters, templates |
| [Masterbar](docs/masterbar.md) | Admin bar customization |
| [Access Control](docs/access-control.md) | Capabilities, roles, permissions |
| [BaseApp Pattern](docs/baseapp.md) | Structured architecture for larger apps |
| [Tutorial](TUTORIAL.md) | Step-by-step guide from minimal to full-featured |

## Claude Code Integration

Get AI assistance for WpApp development in [Claude Code](https://claude.ai/code):

```bash
/plugin marketplace add akirk/wp-app
/plugin install wpapp@wp-app
```

## Examples

### Minimal Example
See `examples/minimal-app/` - The simplest possible WpApp (3 lines of code)

[Try in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/minimal-app.json)

### Community App Example
See `examples/community-app/` - Full BaseApp pattern demonstration with:
- BaseStorage with schema management
- User progress tracking
- REST API endpoints
- Admin integration

[Try in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wp-app/main/blueprints/community-app.json)

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## WordPress Coding Standards

This package follows WordPress coding standards. Run PHPCS:

```bash
composer phpcs
```

## License

GPL-2.0-or-later
