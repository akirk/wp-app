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

Tables are declared in the storage class, keyed by unprefixed table name;
`BaseStorage::create_tables()` wraps them in `CREATE TABLE` and runs
WordPress's native `dbDelta()` from the activation hook:

```php
class CommunityAppStorage extends BaseStorage {
    protected function get_schema() {
        return array(
            'webapp_progress' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY  (id)",
        );
    }
}

public function activate() {
    $this->storage->create_tables();
    flush_rewrite_rules();
}
```

## Learn More

- [WpApp Documentation](../../README.md)
- [BaseApp Pattern](../../README.md#baseapp-pattern)
- [WordPress Playground](https://wordpress.github.io/wordpress-playground/)
