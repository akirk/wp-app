# WpApp Framework

Build modern web applications on WordPress - the world's most popular CMS platform.

## Why WordPress for Web Applications?

WordPress isn't just for blogs anymore. It's an incredibly powerful foundation for web applications:

**🔐 Built-in User Management** - Complete authentication system with roles, permissions, and user profiles out of the box

**🌐 Universal Hosting** - WordPress hosting is available everywhere, from $3/month shared hosting to enterprise solutions

**🔒 Security & Maintenance** - Regular security updates, automatic backups, and battle-tested security practices

**🧩 Rich Ecosystem** - 50,000+ plugins for payments, email, analytics, caching, and more

**⚡ Performance Ready** - Mature caching solutions, CDN integration, and optimization tools

**📱 Admin Interface** - Professional admin panel for content and user management without building one from scratch

**🌍 Global Ready** - Built-in internationalization, multi-site support, and SEO optimization

**💾 Database Abstraction** - Robust database APIs, migrations, and WordPress's proven data handling

**📧 Communication Tools** - Email systems, notifications, and user communication built-in

**⏰ Background Processing** - Cron jobs and scheduled tasks without server configuration

**🎯 Modern APIs** - REST API, GraphQL support, and webhook capabilities

**Perfect for:** SaaS applications, community platforms, membership sites, dashboards, e-commerce, and any web application that benefits from WordPress's mature ecosystem.

---

**wp-app** makes it easy to build clean, app-like experiences on WordPress while leveraging all these benefits. Get user management, hosting, security, and scalability for free - focus on building your unique features.

## Features

- **URL Routing**: Easy pattern-based routing system that integrates with WordPress rewrite rules
- **Database Management**: Simple table creation and migration system using WordPress's `dbDelta`
- **Access Control**: WordPress capability-based authentication with custom role support
- **Admin Bar Integration**: WordPress-style admin bar that shows login state and provides navigation
- **Template System**: Load specific templates for different URL patterns with clean HTML output
- **Custom Roles**: Create app-specific roles that automatically appear in wp-admin user profiles
- **Configuration Management**: Built-in configuration storage and retrieval

## Installation

```bash
composer require akirk/wp-app
```

## Basic Usage (WordPress Plugin)

Here's how to create a WordPress plugin using the akirk/wp-app framework:

**1. Create your plugin's main file:**

```php
<?php
/**
 * Plugin Name: My Web App
 * Description: A web application built with akirk/wp-app
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';
use WpApp\WpApp;

class MyWebApp {
    private $app;

    public function __construct() {
        $this->app = new WpApp( plugin_dir_path( __FILE__ ) . 'templates', 'my-web-app' );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    public function init() {
        // Setup database tables
        $this->app->add_table( 'webapp_posts', [
            'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
            'author_id' => 'bigint(20) unsigned NOT NULL',
            'title' => 'varchar(255) NOT NULL',
            'content' => 'longtext',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'PRIMARY KEY' => '(id)'
        ], '1.0.0' );

        // Setup routes (framework automatically creates main route)
        $this->app->route( 'dashboard' );
        $this->app->route( 'posts' );
        $this->app->route( 'profile/{user_id}' );

        // Add submenu items (automatically appear under main app menu)
        $this->app->add_menu_item( 'posts', 'Posts', home_url( '/my-web-app/posts' ) );
        $this->app->add_user_menu_item( 'my-dashboard', 'Dashboard', home_url( '/my-web-app/dashboard' ) );

        // Initialize the app
        $this->app->init();
    }

    public function activate() {
        $this->app->database()->migrate();
        flush_rewrite_rules();
    }
}

new MyWebApp();
```

**2. Add composer.json to your plugin:**

```json
{
    "require": {
        "akirk/wp-app": "^1.0"
    }
}
```

**3. Create templates/ directory with your app templates**

## Examples

- **`examples/minimal-app/`** - The simplest possible wp-app (just 3 lines of PHP!)
- **`examples/community-app/`** - Complete community platform demonstrating:
  - User profiles, posts, and progress tracking
  - Points and leveling system
  - WordPress admin integration
  - REST API endpoints
  - Responsive design with admin bar integration

## Quick Start (3 Lines of Code)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use WpApp\WpApp;

$app = new WpApp( __DIR__ . '/templates', 'my-app' );
$app->init();
```

### With Configuration

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
    'show_masterbar_for_anonymous' => true,
    'show_wp_logo' => false,
    'show_site_name' => true,
    'require_login' => true,              // Alias for require_capability => 'read'
    'require_capability' => 'edit_posts', // Set minimum capability needed to access app
    'clear_admin_bar' => false
] );
$app->init();
```

That's it! Your app is now available at `/my-app` with automatic template discovery and WordPress integration.

## Routing

The routing system allows you to define URL patterns that load specific templates:

```php
// Simple route (automatically loads templates/dashboard.php)
$app->route( 'dashboard' );

// Route with parameters (automatically loads templates/user-post.php)
$app->route( 'user/{user_id}/posts/{post_id}' );

// Access parameters in templates using WordPress query vars
$user_id = get_query_var( 'user_id' );
$post_id = get_query_var( 'post_id' );
```

## Database Management

Create and manage database tables with automatic migrations:

```php
// Add a table
$app->add_table( 'my_table', [
    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
    'name' => 'varchar(255) NOT NULL',
    'data' => 'longtext',
    'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
    'PRIMARY KEY' => '(id)',
    'KEY name' => '(name)'
], '1.0.0' );

// Tables are created/updated automatically when you call $app->init()
```

## Admin Bar Integration

The framework integrates with WordPress's admin bar system. By default, your app gets a main menu item with submenu navigation:

```php
// Add submenu items (default behavior - creates dropdown under main app item)
$app->add_menu_item( 'my-item', 'My Item', '/my-url' );
$app->add_menu_item( 'settings', 'Settings', '/app/settings' );

// Add top-level menu items (appears directly in admin bar, not as submenu)
$app->add_top_level_menu_item( 'important', 'Important', '/important' );

// Add user menu items (shown when logged in)
$app->add_user_menu_item( 'profile', 'My Profile', '/app/profile' );

// Remove all WordPress admin bar items for clean app-only bar
$app->clear_admin_bar();

// Clear your app's menu items
$app->clear_menu_items();

// Control visibility
$app->masterbar()->show_wp_logo( false );
$app->masterbar()->show_site_name( true );

// Disable the admin bar entirely
$app->enable_masterbar( false );
```

## API Endpoints

Create simple API endpoints:

```php
$app->api( 'users/{user_id}', function( $params ) {
    return [
        'user_id' => $params['user_id'],
        'data' => get_user_data( $params['user_id'] )
    ];
} );

// Accessible at /api/users/123
```

## Access Control

Control who can access your app using WordPress's capability system:

### App-Wide Access Control

```php
// Require users to be logged in
$app->require_capability( 'read' );

// Or require higher privileges
$app->require_capability( 'edit_posts' );
```

### Per-Route Access Control

```php
// Public route
$app->route( 'about', 'about.php' );

// Logged-in users only
$app->route( 'dashboard', 'dashboard.php', [], 'read' );

// Administrators only
$app->route( 'admin', 'admin.php', [], 'manage_options' );
```

### Custom App Roles

Create specific roles for your app that automatically appear in wp-admin:

```php
// Register custom roles
$app->add_role( 'app_user', 'App User', [ 'read' => true ] );
$app->add_role( 'app_moderator', 'App Moderator', [
    'read' => true,
    'moderate_app' => true,
    'edit_app_posts' => true
] );

// Use custom capabilities in routes
$app->route( 'moderate', 'moderate.php', [], 'moderate_app' );
```

Administrators can then assign these roles to users through the standard WordPress user profile interface.

## Template Files

Template files work like normal WordPress templates but have access to route parameters:

```php
<?php
// profile.php template
global $app;

$params = $app->get_route_params();
$user_id = $params['user_id'];

get_header(); ?>

<h1>User Profile: <?php echo esc_html( $user_id ); ?></h1>

<?php if ( $app->is_app_request() ) : ?>
    <p>This is an app route!</p>
<?php endif; ?>

<?php get_footer();
```

## Configuration

Store and retrieve app configuration:

```php
// Set configuration
$app->set_config( 'api_key', 'your-api-key' );
$app->set_config( [
    'setting1' => 'value1',
    'setting2' => 'value2'
] );

// Get configuration
$api_key = $app->get_config( 'api_key' );
$all_config = $app->get_config();
```

## Advanced Usage

### Custom Database Manager

```php
$db = $app->database();

// Check if table exists
if ( $db->table_exists( 'my_table' ) ) {
    // Table exists
}

// Get current version
$version = $db->get_current_version();

// Manual migration
$db->migrate();
```

### Custom Router

```php
$router = $app->router();

// Add complex route
$router->add_route( 'complex/{param1}/sub/{param2}', 'complex.php', [ 'param1', 'param2' ] );

// Flush rewrite rules
$router->flush_rules();
```

### Direct Component Access

```php
// Access components directly
$masterbar = $app->masterbar();
$masterbar->add_menu_item( 'custom', 'Custom Item', '/custom' );

// Render masterbar manually
echo $masterbar->render();
```

## API Reference

### Constructor Configuration

```php
$app = new WpApp( $template_directory, $url_path, $config );
```

**Configuration Options:**
- `show_masterbar_for_anonymous` - Show admin bar for logged-out users (default: true)
- `show_wp_logo` - Show WordPress logo in admin bar (default: true)
- `show_site_name` - Show site name in admin bar (default: true)
- `require_login` - Require users to be logged in (alias for `require_capability => 'read'`)
- `require_capability` - Minimum WordPress capability required to access app
- `minimal_capability` - Alias for `require_capability`
- `clear_admin_bar` - Remove all WordPress admin bar items (default: false)

### Routing Methods

- `route( $pattern, $template = '', $vars = [], $capability = null )` - Add a route
- `get_route_var( $var, $default = '' )` - Get route parameter value
- `is_app_request()` - Check if current request is for this app

### Database Methods

- `add_table( $table_name, $columns, $version, $indexes = [] )` - Add database table
- `database()` - Get database manager instance

### Menu/Admin Bar Methods

- `add_menu_item( $id, $title, $href, $args = [] )` - Add submenu item (default)
- `add_top_level_menu_item( $id, $title, $href, $args = [] )` - Add top-level menu item
- `add_user_menu_item( $id, $title, $href, $args = [] )` - Add user dropdown item
- `clear_menu_items()` - Remove all app menu items
- `clear_admin_bar()` - Remove all WordPress admin bar items
- `enable_masterbar( $enable = true )` - Enable/disable admin bar
- `show_masterbar_for_anonymous( $show = true )` - Control anonymous user visibility

### Access Control Methods

- `require_capability( $capability )` - Set minimum capability for entire app
- `add_role( $role_key, $display_name, $capabilities = [] )` - Create custom role

### Configuration Methods

- `get_config( $key = null, $default = null )` - Get app-specific configuration
- `set_config( $key, $value = null )` - Set app-specific configuration

### Component Access

- `router()` - Get router instance
- `masterbar()` - Get masterbar instance
- `database()` - Get database manager instance

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## License

MIT License