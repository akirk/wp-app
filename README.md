# WpApp Framework

Build modern web applications on WordPress with routing, admin bar integration, and theme isolation.

## Why WordPress for Web Applications?

WordPress isn't just for blogs. It's a powerful foundation for web applications:

- **🔐 Built-in User Management** - Complete authentication with roles and permissions
- **🌐 Universal Hosting** - Deploy anywhere WordPress runs
- **🔒 Security & Updates** - Automatic security updates and proven practices
- **🧩 Rich Ecosystem** - 60,000+ plugins for payments, email, analytics, and more
- **📱 Admin Interface** - Professional backend without building from scratch
- **🌍 Global Ready** - Built-in i18n, multisite, and SEO optimization

**WpApp** makes it easy to build clean applications on WordPress while leveraging all these benefits.

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
- **WordPress Coding Standards** - Follows WordPress best practices

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

## Configuration Options

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'show_masterbar_for_anonymous' => true,  // Show nav for logged-out users
	'show_wp_logo'                 => false, // Hide WordPress logo
	'show_site_name'               => true,  // Show site name
	'require_login'                => true,  // Require login (alias for require_capability => 'read')
	'clear_admin_bar'              => false, // Remove all WP admin bar items
	'app_name'                     => 'My App',
] );
```

## Routing

Routes automatically map to template files:

```php
// Main route -> templates/index.php
$app->route( '' );

// Simple route -> templates/dashboard.php
$app->route( 'dashboard' );

// Route with parameter -> templates/user.php
$app->route( 'user/{user_id}' );

// Nested route -> templates/posts-edit.php
$app->route( 'posts/{post_id}/edit' );
```

Access route parameters in templates:

```php
$user_id = get_query_var( 'user_id' );
$post_id = get_query_var( 'post_id' );
```

## BaseApp Pattern (Recommended for Structured Apps)

For larger applications, extend `BaseApp` for better organization:

```php
<?php
use WpApp\WpApp;
use WpApp\BaseApp;
use WpApp\BaseStorage;

/**
 * Storage class with database schema
 */
class MyAppStorage extends BaseStorage {
	protected function get_schema() {
		$charset_collate = $this->wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$this->wpdb->prefix}my_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				data longtext,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id)
			) $charset_collate;",
		);
	}

	public function get_user_data( $user_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}my_table WHERE user_id = %d",
				$user_id
			)
		);
	}
}

/**
 * Main app class
 */
class MyApp extends BaseApp {

	public function __construct() {
		$this->storage = new MyAppStorage();

		$this->app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'my-app',
			array(
				'require_login' => true,
				'app_name'      => 'My App',
			)
		);

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	protected function setup_database() {
		// Database handled by storage->create_tables() in activate()
	}

	protected function setup_routes() {
		$this->app->route( '' );
		$this->app->route( 'dashboard' );
		$this->app->route( 'user/{user_id}' );
	}

	protected function setup_menu() {
		$this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
	}

	public function activate() {
		$this->storage->create_tables();
		$this->setup_routes();
		flush_rewrite_rules();
	}
}

new MyApp();
```

## Database Management with BaseStorage

Define your schema in the storage class:

```php
class MyAppStorage extends BaseStorage {
	protected function get_schema() {
		$charset_collate = $this->wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$this->wpdb->prefix}my_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",
		);
	}
}
```

Then in your activation hook:

```php
public function activate() {
	$this->storage->create_tables(); // Calls dbDelta with your schema
	flush_rewrite_rules();
}
```

The `create_tables()` method uses WordPress's `dbDelta()` function to create or update tables safely.

## Access Control

### App-Wide Access Control

```php
// Require users to be logged in
$app->require_capability( 'read' );

// Require higher privileges
$app->require_capability( 'edit_posts' );
```

### Per-Route Access Control

```php
// Public route
$app->route( 'about' );

// Logged-in users only
$app->route( 'dashboard', 'dashboard.php', array(), 'read' );

// Administrators only
$app->route( 'admin', 'admin.php', array(), 'manage_options' );
```

### Custom App Roles

Create app-specific roles that appear in wp-admin user profiles:

```php
$app->add_role( 'app_user', 'App User', array( 'read' => true ) );
$app->add_role( 'app_moderator', 'App Moderator', array(
	'read'         => true,
	'moderate_app' => true,
) );

// Use custom capabilities in routes
$app->route( 'moderate', 'moderate.php', array(), 'moderate_app' );
```

## Admin Bar

Customize the WordPress admin bar for your app:

```php
// Add submenu items (default - appears under your app name)
$app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
$app->add_menu_item( 'settings', 'Settings', home_url( '/my-app/settings' ) );

// Add top-level menu items (appears directly in admin bar)
$app->add_top_level_menu_item( 'important', 'Important', '/important' );

// Add user menu items (shown when logged in)
$app->add_user_menu_item( 'profile', 'My Profile', home_url( '/my-app/profile' ) );

// Remove all WordPress admin bar items for clean app-only bar
$app->clear_admin_bar();

// Control visibility
$app->masterbar()->show_wp_logo( false );
$app->masterbar()->show_site_name( true );
```

## Template Functions

Helper functions for use in templates:

```php
// HTML head with WordPress hooks
wp_app_head();

// Body open hook (renders admin bar)
wp_app_body_open();

// Body close hook
wp_app_body_close();

// Page title with site name
wp_app_title( 'My Page' );

// Language attributes for <html> tag
wp_app_language_attributes();

// Enqueue styles for app pages
wp_app_enqueue_style( 'my-style', plugin_dir_url( __FILE__ ) . 'style.css' );

// Enqueue scripts for app pages
wp_app_enqueue_script( 'my-script', plugin_dir_url( __FILE__ ) . 'script.js' );
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

## WordPress Coding Standards

This package follows WordPress coding standards. Run PHPCS:

```bash
composer phpcs
```

Auto-fix issues:
```bash
composer phpcbf
```

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## License

GPL-2.0-or-later

## Learn More

- [Tutorial](TUTORIAL.md) - Step-by-step guide from minimal to full-featured
- [Community App Example](examples/community-app/README.md) - Complete example with BaseApp pattern
- [WordPress Playground](https://wordpress.github.io/wordpress-playground/) - Test locally without setup
