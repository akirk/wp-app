# Access Control

WpApp uses WordPress capabilities to control access to your app and individual routes.

## App-Wide Access Control

### Require Login

The simplest access control - require users to be logged in:

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'require_login' => true,
] );

// Or via method
$app->require_capability( 'read' );
```

### Require Specific Capability

Restrict to users with specific capabilities:

```php
// Editors and above
$app->require_capability( 'edit_others_posts' );

// Administrators only
$app->require_capability( 'manage_options' );

// Custom capability
$app->require_capability( 'access_my_app' );
```

## Common WordPress Capabilities

| Capability | Who Has It |
|------------|-----------|
| `read` | All logged-in users |
| `edit_posts` | Contributors and above |
| `publish_posts` | Authors and above |
| `edit_others_posts` | Editors and above |
| `manage_options` | Administrators only |

See [WordPress Roles and Capabilities](https://wordpress.org/documentation/article/roles-and-capabilities/) for a complete list.

## Per-Route Access Control

Different routes can have different access requirements:

```php
// Public routes (no restriction)
$app->route( '' );
$app->route( 'about' );

// Logged-in users only
$app->route( 'dashboard', 'dashboard.php', [], 'read' );
$app->route( 'profile', 'profile.php', [], 'read' );

// Content creators
$app->route( 'create', 'create.php', [], 'edit_posts' );

// Administrators only
$app->route( 'admin', 'admin.php', [], 'manage_options' );
$app->route( 'settings', 'settings.php', [], 'manage_options' );
```

The fourth parameter to `route()` specifies the required capability.

## Custom App Roles

Create app-specific roles that appear in the WordPress user profile:

```php
// Basic app user role
$app->add_role( 'app_user', 'App User', [
	'read' => true,
] );

// Moderator with custom capability
$app->add_role( 'app_moderator', 'App Moderator', [
	'read'         => true,
	'moderate_app' => true,
] );

// Premium user
$app->add_role( 'app_premium', 'Premium User', [
	'read'              => true,
	'access_premium'    => true,
] );
```

Then use custom capabilities in routes:

```php
$app->route( 'moderate', 'moderate.php', [], 'moderate_app' );
$app->route( 'premium', 'premium.php', [], 'access_premium' );
```

### How Custom Roles Work

- Roles are prefixed with your app's URL path to avoid conflicts
- They appear in wp-admin user profiles under your app's section
- Administrators can assign these roles to users
- Users can have multiple roles (WordPress supports this)

## Checking Access in Templates

### Current User Capabilities

```php
if ( current_user_can( 'edit_posts' ) ) {
	echo '<a href="/my-app/create">Create Post</a>';
}

if ( current_user_can( 'manage_options' ) ) {
	echo '<a href="/my-app/admin">Admin Settings</a>';
}
```

### Login Status

```php
if ( is_user_logged_in() ) {
	$user = wp_get_current_user();
	echo 'Welcome, ' . esc_html( $user->display_name );
} else {
	echo '<a href="' . esc_url( wp_login_url() ) . '">Log In</a>';
}
```

## Access Denied Behavior

When a user lacks the required capability:

- **App-wide restriction**: User sees the WordPress login page (if not logged in) or a permission denied message
- **Per-route restriction**: Route returns a 403 status

## Combining with Masterbar

Menu items are only shown to users who can access the app:

```php
$app->require_capability( 'edit_posts' );

// These menu items only appear for users with edit_posts capability
$app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
```

## Multi-App Access

When running multiple WpApp instances, each can have different access requirements:

```php
// Public app
$public_app = new WpApp( __DIR__ . '/templates/public', 'public' );
$public_app->init();

// Members-only app
$members_app = new WpApp( __DIR__ . '/templates/members', 'members', [
	'require_login' => true,
] );
$members_app->init();

// Admin app
$admin_app = new WpApp( __DIR__ . '/templates/admin', 'admin-panel', [
	'require_capability' => 'manage_options',
] );
$admin_app->init();
```

## Related Documentation

- [Configuration](configuration.md) - Access control config options
- [Routing](routing.md) - Per-route capabilities
- [BaseApp Pattern](baseapp.md) - Organized access control setup
