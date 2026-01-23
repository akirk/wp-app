# Community App Example

A complete example demonstrating the WpApp framework with the BaseApp pattern, including:

- **BaseApp pattern**: Structured app with separation of concerns
- **BaseStorage**: Custom storage layer for database operations
- **dbDelta**: WordPress-native database table creation
- **REST API**: Custom endpoints for frontend interactions
- **Admin integration**: WordPress admin pages for app management

## Quick Start with WordPress Playground

The fastest way to try this example is with WordPress Playground:

```bash
npx @wp-playground/cli run examples/community-app
```

This will spin up a local WordPress instance with the Community App plugin already installed and activated.

## Manual Installation

1. **Install dependencies:**
   ```bash
   cd examples/community-app
   composer install
   ```

2. **Copy to WordPress:**
   Copy this directory to `wp-content/plugins/community-app`

3. **Activate the plugin:**
   Go to WordPress Admin → Plugins → Activate "Community App"

4. **Visit the app:**
   Navigate to `/community` on your WordPress site

## Project Structure

```
community-app/
├── community-app.php     # Main plugin file with BaseApp pattern
├── templates/            # App template files
│   ├── index.php        # Home page
│   ├── dashboard.php    # User dashboard
│   └── ...
├── assets/              # CSS and JavaScript
│   └── app.css
├── composer.json        # Dependencies
└── blueprint.json       # WordPress Playground configuration
```

## Key Features Demonstrated

### 1. BaseApp Pattern

The app extends `WpApp\BaseApp` and implements three required methods:

```php
class CommunityApp extends BaseApp {
    protected function setup_database() { }
    protected function setup_routes() { }
    protected function setup_menu() { }
}
```

### 2. BaseStorage Pattern

Custom storage class for database operations:

```php
class CommunityAppStorage extends BaseStorage {
    public function get_user_progress( $user_id ) {
        return $this->wpdb->get_row( ... );
    }
}
```

### 3. WordPress dbDelta

Database tables created in activation hook using WordPress native `dbDelta()`:

```php
public function activate() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;

    $sql = "CREATE TABLE {$wpdb->prefix}webapp_progress (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};";

    dbDelta( $sql );
}
```

## Learn More

- [WpApp Documentation](../../README.md)
- [BaseApp Pattern](../../README.md#baseapp-pattern)
- [WordPress Playground](https://wordpress.github.io/wordpress-playground/)
