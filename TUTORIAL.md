# WpApp Tutorial: From Minimal to Full-Featured

This tutorial will guide you through building web applications using the WpApp framework, starting from the minimal example and gradually adding features using the BaseApp pattern.

## Why Build on WordPress?

Before diving into code, let's understand why WordPress is an excellent foundation for web applications:

- **🔐 User system included** - Login, registration, roles, and permissions work out of the box
- **🌐 Easy hosting** - Deploy anywhere WordPress runs (which is everywhere)
- **🔒 Security handled** - Get automatic security updates and proven security practices
- **🧩 Rich plugin ecosystem** - Integrate payments, email, analytics, and thousands of other features
- **📱 Admin interface** - Professional backend for managing users and content without building from scratch

The WpApp framework lets you build modern applications while getting all these benefits for free.

## Getting Started: The Minimal Example

The simplest possible WpApp requires just 3 lines of PHP. Check out `examples/minimal-app/`:

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

### With Configuration

For more control, pass a configuration array:

```php
$app = new WpApp( __DIR__ . '/templates', 'minimal', [
	'show_masterbar_for_anonymous' => true,  // Show navigation for logged-out users
	'show_wp_logo'                 => false, // Hide WordPress logo
	'show_site_name'               => true,  // Show your site name
	'require_login'                => true,  // Require login (alias for require_capability => 'read')
] );
$app->init();
```

This automatically gives you:
- A route at `/minimal` that loads `templates/index.php`
- A navigation bar that works for both logged-in and anonymous users
- Clean HTML output separate from your WordPress theme
- Professional styling and responsive design

## Step 1: Adding More Pages

Add routes for additional pages:

```php
$app = new WpApp( __DIR__ . '/templates', 'minimal' );

// Main page -> templates/index.php
$app->route( '' );

// About page -> templates/about.php
$app->route( 'about' );

// Contact page -> templates/contact.php
$app->route( 'contact' );

$app->init();
```

The framework automatically discovers template files based on route names.

## Step 2: Dynamic Routes with Parameters

Create routes with parameters:

```php
// User profile -> templates/user.php
$app->route( 'user/{user_id}' );

// Post view -> templates/post.php
$app->route( 'posts/{post_id}' );

// Nested parameters -> templates/user-posts.php
$app->route( 'user/{user_id}/posts/{post_id}' );
```

Access parameters in your templates:

```php
<?php
$user_id = get_query_var( 'user_id' );
$post_id = get_query_var( 'post_id' );
?>
<h1>User <?php echo intval( $user_id ); ?>, Post <?php echo intval( $post_id ); ?></h1>
```

## Step 3: Using BaseApp Pattern for Structured Apps

For larger applications, use the BaseApp pattern for better organization. This separates concerns into:
- **Storage** - Database schema and data access
- **App** - Routing and configuration
- **Views** - Template files

### Create a Storage Class

```php
<?php
use WpApp\BaseStorage;

class MyAppStorage extends BaseStorage {

	/**
	 * Define database schema
	 */
	protected function get_schema() {
		$charset_collate = $this->wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$this->wpdb->prefix}user_visits (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				page_visited varchar(255) NOT NULL,
				visit_time datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id)
			) $charset_collate;",
		);
	}

	/**
	 * Get user visit history
	 */
	public function get_user_visits( $user_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}user_visits WHERE user_id = %d ORDER BY visit_time DESC",
				$user_id
			)
		);
	}

	/**
	 * Log a visit
	 */
	public function log_visit( $user_id, $page ) {
		$this->wpdb->insert(
			$this->wpdb->prefix . 'user_visits',
			array(
				'user_id'      => $user_id,
				'page_visited' => $page,
			),
			array( '%d', '%s' )
		);
	}
}
```

### Create Your App Class

```php
<?php
use WpApp\WpApp;
use WpApp\BaseApp;

class MyApp extends BaseApp {

	public function __construct() {
		// Initialize storage
		$this->storage = new MyAppStorage();

		// Initialize WpApp
		$this->app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'my-app',
			array(
				'require_login' => true,
				'app_name'      => 'My App',
			)
		);

		// Hook into WordPress
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	protected function setup_database() {
		// Database schema is defined in storage class
		// Tables are created in activate() hook
	}

	protected function setup_routes() {
		$this->app->route( '' );
		$this->app->route( 'dashboard' );
		$this->app->route( 'history' );
	}

	protected function setup_menu() {
		$this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
		$this->app->add_menu_item( 'history', 'History', home_url( '/my-app/history' ) );
	}

	public function activate() {
		// Create/update database tables
		$this->storage->create_tables();

		// Set up routes before flushing
		$this->setup_routes();

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

new MyApp();
```

### Use Storage in Templates

```php
<?php
// templates/history.php
global $app; // Access via BaseApp pattern

$user_id = get_current_user_id();
$visits  = $app->get_storage()->get_user_visits( $user_id );
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<title><?php echo wp_app_title( 'Visit History' ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<h1>Your Visit History</h1>

	<?php if ( $visits ) : ?>
		<ul>
			<?php foreach ( $visits as $visit ) : ?>
				<li>
					<?php echo esc_html( $visit->page_visited ); ?>
					- <?php echo esc_html( $visit->visit_time ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p>No visits recorded yet.</p>
	<?php endif; ?>

	<?php wp_app_body_close(); ?>
</body>
</html>
```

## Step 4: Access Control and User Management

Control who can access your app with WordPress's capability system:

### Require Login for Entire App

```php
$this->app = new WpApp(
	plugin_dir_path( __FILE__ ) . 'templates',
	'my-app',
	array(
		'require_login' => true, // Alias for require_capability => 'read'
	)
);
```

### Per-Route Access Control

```php
protected function setup_routes() {
	// Public route - anyone can access
	$this->app->route( 'about' );

	// Logged-in users only
	$this->app->route( 'dashboard', 'dashboard.php', array(), 'read' );

	// Editors and above only
	$this->app->route( 'admin', 'admin.php', array(), 'edit_pages' );

	// Administrators only
	$this->app->route( 'settings', 'settings.php', array(), 'manage_options' );
}
```

### Custom App Roles

Create specific roles for your app that automatically appear in wp-admin:

```php
public function __construct() {
	// ... existing code ...

	// Create custom roles
	$this->app->add_role( 'member', 'Community Member', array( 'read' => true ) );
	$this->app->add_role( 'moderator', 'Community Moderator', array(
		'read'          => true,
		'moderate_app'  => true,
		'edit_app_posts' => true,
	) );
}

protected function setup_routes() {
	// Moderator-only route
	$this->app->route( 'moderate', 'moderate.php', array(), 'moderate_app' );
}
```

The custom roles will automatically appear in each user's profile in wp-admin, allowing administrators to grant app-specific access easily.

## Step 5: Customizing the Admin Bar

Add custom menu items and modify the admin bar:

```php
protected function setup_menu() {
	// Add submenu items (appears under your app name)
	$this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
	$this->app->add_menu_item( 'settings', 'Settings', home_url( '/my-app/settings' ) );

	// Add top-level menu items (appears directly in admin bar)
	$this->app->add_top_level_menu_item( 'important', 'Important', '/important' );

	// Add user menu items (shown when logged in)
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$this->app->add_user_menu_item(
			'profile',
			'My Profile',
			home_url( '/my-app/user/' . $current_user->ID )
		);
	}

	// For clean app-only admin bar, remove all WordPress items
	// $this->app->clear_admin_bar();
}
```

## Step 6: Adding REST API Endpoints

Integrate with WordPress REST API for AJAX functionality:

```php
protected function setup_routes() {
	// Regular routes
	$this->app->route( '' );
	$this->app->route( 'dashboard' );

	// Register REST API endpoints
	add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
}

public function register_rest_endpoints() {
	register_rest_route(
		'my-app/v1',
		'/user-stats',
		array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_user_stats' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		)
	);
}

public function check_permissions() {
	return is_user_logged_in();
}

public function get_user_stats() {
	$user_id = get_current_user_id();
	$visits  = $this->storage->get_user_visits( $user_id );

	return rest_ensure_response(
		array(
			'user_id'     => $user_id,
			'total_visits' => count( $visits ),
			'last_visit'  => $visits[0]->visit_time ?? null,
		)
	);
}
```

Access the endpoint at: `/wp-json/my-app/v1/user-stats`

## Step 7: WordPress Integration Best Practices

Use WordPress functions in your templates:

```php
<?php
// templates/dashboard.php

// Check authentication
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( home_url( '/my-app/dashboard' ) ) );
	exit;
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<title><?php echo wp_app_title( 'My Dashboard' ); ?></title>
	<?php wp_app_head(); ?>
	<?php wp_app_enqueue_style( 'my-app-style', plugin_dir_url( __FILE__ ) . '../assets/style.css' ); ?>
</head>
<body class="wp-app-body">
	<?php wp_app_body_open(); ?>

	<h1>Welcome, <?php echo esc_html( $current_user->display_name ); ?>!</h1>

	<p>Email: <?php echo esc_html( $current_user->user_email ); ?></p>

	<?php wp_app_body_close(); ?>
</body>
</html>
```

## Complete Example: Community App

For a full-featured example showcasing all these concepts, see `examples/community-app/`. This example demonstrates:

- **BaseApp Pattern** - Structured architecture with storage separation
- **BaseStorage** - Schema management with `get_schema()` and `dbDelta`
- **User Authentication** - Login/logout integration with WordPress
- **Database Usage** - Custom tables for user progress and posts
- **Dynamic Routing** - User profiles, post viewing, dashboard
- **Admin Bar Customization** - Context-aware menu items
- **REST API Integration** - AJAX functionality using WordPress REST API
- **Responsive Design** - Mobile-friendly interface
- **WordPress Coding Standards** - Follows WordPress best practices

Run it with WordPress Playground:

```bash
cd examples/community-app
npx @wp-playground/cli run .
```

## Best Practices

1. **Use BaseApp Pattern** - For structured apps with database needs
2. **Define Schema in Storage** - Keep database schema in `get_schema()` method
3. **Call create_tables() on Activation** - Use `$this->storage->create_tables()` in activation hook
4. **WordPress Functions** - Use WordPress functions for consistency (`esc_html()`, `wp_redirect()`, etc.)
5. **Security** - Always sanitize user input and check permissions
6. **Template Functions** - Use `wp_app_head()`, `wp_app_body_open()`, etc. in templates
7. **Coding Standards** - Follow WordPress coding standards (use `composer phpcs`)

## Next Steps

- Explore the `examples/community-app/` for advanced patterns
- Add custom CSS and JavaScript to your templates
- Integrate with WordPress plugins
- Create REST API endpoints for dynamic functionality
- Build user authentication flows
- Implement complex database relationships

The WpApp framework gives you the flexibility to build anything from simple landing pages to complex web applications, all while leveraging WordPress's powerful ecosystem.
