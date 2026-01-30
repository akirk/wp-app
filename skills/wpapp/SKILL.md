---
name: wpapp
description: |
  Build WordPress web applications using the WpApp framework. Use when:
  (1) Creating new WpApp plugins or routes
  (2) Working with BaseApp/BaseStorage patterns
  (3) Configuring masterbar, access control, or routing
  (4) Adding database tables with schema management
  (5) User asks about WpApp features or configuration
---

# WpApp Development

WpApp builds web applications on WordPress with routing, theme isolation, and admin bar integration.

## Creating a New Project

Scaffold a new WpApp plugin:

```bash
composer create-project akirk/create-wp-app my-app
```

This prompts for plugin name, namespace, author, URL path, and setup type (minimal or full BaseApp structure).

## Project Structure

```
my-app/
├── my-app.php              # Main plugin file
├── src/
│   ├── App.php             # BaseApp subclass
│   └── Storage.php         # BaseStorage subclass (optional)
└── templates/
    ├── index.php           # Main route template
    └── dashboard.php       # Additional route templates
```

## Quick Patterns

### Minimal Setup (3 lines)

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app' );
$app->init();
```

### BaseApp Setup (recommended for larger apps)

```php
class App extends BaseApp {
    public function __construct() {
        $this->app = new WpApp( __DIR__ . '/templates', 'my-app', [
            'require_login' => true,
        ] );
    }

    protected function setup_routes(): void {
        $this->app->route( 'dashboard' );
        $this->app->route( 'user/{user_id}' );
    }

    protected function setup_menu(): void {
        $this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
    }

    protected function setup_database(): void {}
}
```

### Route with Parameters

```php
$app->route( 'user/{user_id}' );  // templates/user.php
$app->route( 'posts/{id}/edit' ); // templates/posts-edit.php
```

Access in template: `$user_id = get_query_var( 'user_id' );`

### Per-Route Access Control

```php
$app->route( 'admin', 'admin.php', [], 'manage_options' );
```

### BaseStorage Schema

```php
class Storage extends BaseStorage {
    protected function get_schema() {
        return [
            "CREATE TABLE {$this->wpdb->prefix}myapp_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                data longtext,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) {$this->wpdb->get_charset_collate()};",
        ];
    }
}
```

Call `$storage->create_tables()` on plugin activation.

### Template Structure

```php
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php wp_app_title( 'Page' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body>
    <?php wp_app_body_open(); ?>
    <!-- content -->
    <?php wp_app_body_close(); ?>
</body>
</html>
```

## Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `require_login` | `false` | Require authentication |
| `require_capability` | `null` | Required WordPress capability |
| `show_masterbar_for_anonymous` | `true` | Show nav for logged-out users |
| `show_wp_logo` | `true` | Show WordPress logo |
| `clear_admin_bar` | `false` | Remove all WP admin bar items |
| `add_app_node` | `true` | Add app entry to admin bar |
| `my_apps` | `true` | Register with My Apps plugin |

## Menu Methods

```php
$app->add_menu_item( 'id', 'Label', $url );           // Submenu under app
$app->add_top_level_menu_item( 'id', 'Label', $url ); // Top-level
$app->add_user_menu_item( 'id', 'Label', $url );      // User dropdown
```

## Detailed Documentation

For comprehensive guides, see the `docs/` directory:
- `docs/getting-started.md` - Installation and quick start
- `docs/configuration.md` - All configuration options
- `docs/routing.md` - URL patterns and parameters
- `docs/masterbar.md` - Admin bar customization
- `docs/access-control.md` - Capabilities and roles
- `docs/baseapp.md` - BaseApp and BaseStorage patterns
