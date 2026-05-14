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

WpApp builds web applications on WordPress with routing, theme isolation, admin bar integration, and admin color scheme tokens.

## Creating a New Project

Scaffold a new WpApp plugin:

```bash
composer create-project akirk/create-wp-app my-app
```

This prompts for plugin name, namespace, author, URL path, and setup type. The default setup type is Full, which creates a `BaseApp` scaffold. Choose Minimal only for a small direct `WpApp` setup.

## Generated Project Structure

Full setup creates:

```text
my-app/
├── my-app.php              # Main plugin file
├── src/
│   └── App.php             # BaseApp subclass with lifecycle hooks
├── templates/
│   └── index.php           # Main route template
├── composer.json
└── .gitignore
```

Minimal setup creates:

```text
my-app/
├── my-app.php              # Main plugin file with WpApp initialization
├── templates/
│   └── index.php
├── composer.json
└── .gitignore
```

## Generated App Lifecycle

- Read the generated files before modifying them.
- Keep `__construct()` focused on creating/configuring `WpApp`, assigning storage objects, and attaching WordPress hooks.
- Do not call `register_post_type()`, `register_taxonomy()`, `flush_rewrite_rules()`, `wp_add_dashboard_widget()`, REST route registration, or other WordPress-hooked feature registration directly from `__construct()`.
- Register custom post types and taxonomies on the WordPress `init` hook.
- Register dashboard widgets on the WordPress `wp_dashboard_setup` hook.
- Define WpApp routes in `setup_routes()` and WpApp menu/masterbar entries in `setup_menu()`.
- Run activation-only work, including custom table creation and rewrite flushing, from the plugin activation hook.

## Quick Patterns

### Minimal Setup

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app' );
$app->init();
```

### BaseApp Setup

```php
use WpApp\BaseApp;
use WpApp\WpApp;

class App extends BaseApp {
    public function __construct() {
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'require_login' => true,
            'app_name'      => 'My App',
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
    }

    protected function get_url_path(): string {
        return 'my-app';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    protected function setup_database(): void {}

    protected function setup_routes(): void {
        $this->app->route( 'dashboard' );
        $this->app->route( 'user/{user_id}' );
    }

    protected function setup_menu(): void {
        $this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
    }
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

## Storage Choices

- Prefer WordPress-native storage before custom tables:
  - Custom post types and post meta for content-like records.
  - Taxonomies, terms, and term meta for shared categories, labels, and groupings.
  - User meta for per-user settings, preferences, and profile data.
- Use custom tables and `BaseStorage` only when native WordPress storage does not fit, such as high-volume rows, relational data, or records that do not map cleanly to posts, terms, or users.
- If using `BaseStorage`, instantiate the storage class during app construction and call `create_tables()` during plugin activation.

### BaseStorage Schema

```php
class Storage extends BaseStorage {
    protected function get_schema() {
        $charset_collate = $this->wpdb->get_charset_collate();

        return [
            "CREATE TABLE {$this->wpdb->prefix}myapp_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                data longtext,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $charset_collate;",
        ];
    }
}
```

## Template Structure

```php
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'Page' ); ?></title>
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
| `app_name` | Auto-generated from URL path | Display name for your app in the admin bar |
| `require_login` | `false` | Require authentication |
| `require_capability` | `null` | Required WordPress capability |
| `show_masterbar_for_anonymous` | `true` | Show nav for logged-out users |
| `show_wp_logo` | `true` | Show WordPress logo |
| `show_site_name` | `true` | Show site name |
| `admin_bar_app_link` | `true` | Add app entry to admin bar |
| `clear_admin_bar` | `false` | Remove all WP admin bar items |
| `my_apps` | `true` | Register with My Apps plugin |
| `my_apps_icon` | `null` | URL to an app icon |

Use admin color variables in app CSS instead of hard-coding WordPress admin colors:

```css
.button-primary {
    background: var(--wp-app-color-primary);
    color: var(--wp-app-color-on-primary);
}

.button-primary:hover {
    background: var(--wp-app-color-primary-hover);
}
```

## Menu Methods

```php
$app->add_menu_item( 'id', 'Label', $url );           // Submenu under app
$app->add_top_level_menu_item( 'id', 'Label', $url ); // Top-level
$app->add_user_menu_item( 'id', 'Label', $url );      // User dropdown
```

## Verification

- After modifying PHP, run or request a syntax check before navigating the app.
- If a WordPress runtime is available, activate the plugin and load the configured app URL after the syntax check passes.

## Detailed Documentation

For comprehensive guides, see the `docs/` directory:
- `docs/getting-started.md` - Installation and quick start
- `docs/configuration.md` - All configuration options
- `docs/routing.md` - URL patterns and parameters
- `docs/masterbar.md` - Admin bar customization
- `docs/access-control.md` - Capabilities and roles
- `docs/baseapp.md` - BaseApp and BaseStorage patterns
