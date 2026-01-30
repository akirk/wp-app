# BaseApp Pattern

For larger applications, extend `BaseApp` for better organization. This pattern separates concerns into database setup, route registration, and menu configuration.

## Basic Structure

```php
<?php
use WpApp\WpApp;
use WpApp\BaseApp;
use WpApp\BaseStorage;

class MyApp extends BaseApp {

	public function __construct() {
		$this->storage = new MyAppStorage();

		$this->app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'my-app',
			[
				'require_login' => true,
				'app_name'      => 'My App',
			]
		);

		add_action( 'plugins_loaded', [ $this, 'init' ] );
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
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

## Abstract Methods

BaseApp requires you to implement three methods:

| Method | Purpose |
|--------|---------|
| `setup_database()` | Database initialization (often empty if using BaseStorage) |
| `setup_routes()` | Register all routes |
| `setup_menu()` | Add menu items to the masterbar |

## BaseStorage Pattern

BaseStorage provides database abstraction with schema management using WordPress's `dbDelta()`.

### Defining a Schema

```php
class MyAppStorage extends BaseStorage {

	protected function get_schema() {
		$charset_collate = $this->wpdb->get_charset_collate();

		return [
			"CREATE TABLE {$this->wpdb->prefix}myapp_items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				title varchar(255) NOT NULL,
				content longtext,
				status varchar(20) DEFAULT 'draft',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY status (status)
			) $charset_collate;",

			"CREATE TABLE {$this->wpdb->prefix}myapp_meta (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				item_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) NOT NULL,
				meta_value longtext,
				PRIMARY KEY  (id),
				KEY item_id (item_id),
				KEY meta_key (meta_key)
			) $charset_collate;",
		];
	}
}
```

### Adding Data Methods

```php
class MyAppStorage extends BaseStorage {

	protected function get_schema() {
		// ... schema definition
	}

	public function get_items_by_user( $user_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}myapp_items WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);
	}

	public function get_item( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}myapp_items WHERE id = %d",
				$id
			)
		);
	}

	public function create_item( $user_id, $title, $content = '' ) {
		$this->wpdb->insert(
			$this->wpdb->prefix . 'myapp_items',
			[
				'user_id' => $user_id,
				'title'   => $title,
				'content' => $content,
			],
			[ '%d', '%s', '%s' ]
		);

		return $this->wpdb->insert_id;
	}

	public function update_item( $id, $data ) {
		return $this->wpdb->update(
			$this->wpdb->prefix . 'myapp_items',
			$data,
			[ 'id' => $id ]
		);
	}

	public function delete_item( $id ) {
		return $this->wpdb->delete(
			$this->wpdb->prefix . 'myapp_items',
			[ 'id' => $id ],
			[ '%d' ]
		);
	}
}
```

### Creating Tables

Call `create_tables()` during plugin activation:

```php
public function activate() {
	$this->storage->create_tables();
	flush_rewrite_rules();
}
```

The `create_tables()` method uses `dbDelta()` which safely creates or updates tables.

## Accessing Components

BaseApp provides getters for the WpApp and storage instances:

```php
class MyApp extends BaseApp {
	// ...

	public function some_method() {
		// Access the WpApp instance
		$app = $this->get_app();

		// Access the storage instance
		$storage = $this->get_storage();
	}
}
```

## Using Storage in Templates

Make your storage accessible to templates:

```php
class MyApp extends BaseApp {

	private static $instance;

	public function __construct() {
		self::$instance = $this;
		// ... rest of constructor
	}

	public static function get_instance() {
		return self::$instance;
	}
}
```

In templates:

```php
<?php
$app = MyApp::get_instance();
$storage = $app->get_storage();
$items = $storage->get_items_by_user( get_current_user_id() );

foreach ( $items as $item ) {
	echo '<div class="item">';
	echo '<h2>' . esc_html( $item->title ) . '</h2>';
	echo '</div>';
}
```

## Complete Example

See the [Community App example](../examples/community-app/) for a full implementation with:

- BaseApp structure
- BaseStorage with schema
- REST API endpoints
- Admin integration
- Multiple routes and templates

## File Structure

A typical BaseApp project structure:

```
my-app-plugin/
├── my-app.php              # Main plugin file with MyApp class
├── class-myapp-storage.php # Storage class
├── templates/
│   ├── index.php
│   ├── dashboard.php
│   └── user.php
└── assets/
    ├── style.css
    └── script.js
```

## Related Documentation

- [Getting Started](getting-started.md) - Basic setup
- [Routing](routing.md) - Route patterns
- [Configuration](configuration.md) - WpApp options
