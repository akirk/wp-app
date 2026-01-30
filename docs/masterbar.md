# Masterbar (Admin Bar)

WpApp integrates with the WordPress admin bar to provide navigation for your app. It also provides a custom masterbar for anonymous users when the WordPress admin bar isn't shown.

## Adding Menu Items

### Submenu Items (Default)

Items added with `add_menu_item()` appear as submenus under your app's name:

```php
$app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
$app->add_menu_item( 'settings', 'Settings', home_url( '/my-app/settings' ) );
$app->add_menu_item( 'help', 'Help', home_url( '/my-app/help' ) );
```

### Top-Level Items

Items added with `add_top_level_menu_item()` appear directly in the admin bar:

```php
$app->add_top_level_menu_item( 'notifications', 'Notifications (3)', home_url( '/my-app/notifications' ) );
```

### User Menu Items

Items added with `add_user_menu_item()` appear in the user dropdown (next to profile):

```php
$app->add_user_menu_item( 'profile', 'My Profile', home_url( '/my-app/profile' ) );
$app->add_user_menu_item( 'preferences', 'Preferences', home_url( '/my-app/preferences' ) );
```

## Disabling the App Node

By default, WpApp adds a main entry for your app in the admin bar. If you already have a custom post type or other mechanism that adds its own admin bar entry, you can disable the automatic one:

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'add_app_node' => false,
] );

// Or via method
$app->add_app_node( false );

// Or via masterbar instance
$app->masterbar()->add_app_node( false );
```

When disabled:
- No automatic app entry is added to the admin bar on app pages
- No app link is added when viewing regular WordPress admin/frontend
- Your custom menu items are still added (if any)

## Display Options

### WordPress Logo

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'show_wp_logo' => false,
] );

// Or via method
$app->masterbar()->show_wp_logo( false );
```

### Site Name

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'show_site_name' => false,
] );

// Or via method
$app->masterbar()->show_site_name( false );
```

### Dark Mode Toggle

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'show_dark_mode_toggle' => true,
] );

// Or via method
$app->show_dark_mode_toggle( true );
```

### Anonymous Users

Control whether the masterbar shows for logged-out users:

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'show_masterbar_for_anonymous' => true,
] );

// Or via method
$app->show_masterbar_for_anonymous( false );
```

## Clearing WordPress Items

### Remove Specific Items

WpApp automatically removes some WordPress admin bar items on app pages (like "New", "Comments", "Updates"). Customize which items are removed:

```php
$app->remove_admin_bar_items( [
	'new-content',
	'comments',
	'updates',
	'wp-logo',
] );
```

### Remove All WordPress Items

For a completely clean admin bar with only your app items:

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'clear_admin_bar' => true,
] );

// Or via method
$app->clear_admin_bar();
```

## Removing App Menu Items

```php
// Remove a specific item
$app->masterbar()->remove_menu_item( 'settings' );

// Remove a user menu item
$app->masterbar()->remove_user_menu_item( 'preferences' );

// Clear all app menu items
$app->clear_menu_items();
```

## Advanced: Menu Item Options

Menu items accept an optional `$args` array:

```php
$app->add_menu_item( 'external', 'External Link', 'https://example.com', [
	'target' => '_blank',
	'class'  => 'my-custom-class',
] );
```

## Hooks

### Adding Custom Items

```php
add_action( 'wp_app_admin_bar_menu', function( $wp_admin_bar ) {
	$wp_admin_bar->add_node( [
		'id'    => 'my-custom-item',
		'title' => 'Custom Item',
		'href'  => '/custom',
	] );
} );
```

### Custom Styles

```php
add_action( 'wp_app_masterbar_styles', function() {
	echo '
		.wp-app-masterbar {
			background: #1e3a5f;
		}
	';
} );
```

### Custom Scripts

```php
add_action( 'wp_app_masterbar_scripts', function() {
	echo '
		console.log("Masterbar loaded");
	';
} );
```

## Masterbar vs Admin Bar

WpApp uses two different systems depending on context:

| Context | System Used |
|---------|-------------|
| Logged-in users | WordPress admin bar (enhanced) |
| Anonymous users (masterbar enabled) | Custom HTML masterbar |

Both systems display the same menu items and respect the same configuration.

## Related Documentation

- [Configuration](configuration.md) - All masterbar config options
- [Access Control](access-control.md) - Who sees what
