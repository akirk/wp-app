# Masterbar (Admin Bar)

[Documentation index](README.md)

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

## Disabling the App Link

By default, WpApp adds a main entry for your app in the admin bar. If you already have a custom post type or other mechanism that adds its own admin bar entry, you can disable the automatic one:

```php
// Via config
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'admin_bar_app_link' => false,
] );

// Or via method
$app->admin_bar_app_link( false );

// Or via masterbar instance
$app->masterbar()->admin_bar_app_link( false );
```

When disabled:
- No automatic app entry is added to the admin bar on app pages
- No app link is added when viewing regular WordPress admin/frontend
- Your custom menu items are still added (if any)

## Display Options

### Apps, Launcher, and Desktop Pins

**Settings > WP Apps** has three collapsible sections:

- **Apps** edits shared names, icons, colors, themes, and navigation visibility.
- **Launcher** shows the same icon-and-name grid used on desktop and mobile. Select a tile to edit its app in a popover (a bottom sheet on phones). Changes update the app entry and both previews immediately.
- **Desktop top bar** manages icon-only pinned apps, with names available as tooltips and accessible labels.

**Show in launcher** and **Pin to desktop top bar** are independent. Pinning an app does not remove it from the launcher, and hiding it from navigation does not restrict its URL or permissions. Apps which disable their automatic app link, and apps the current user cannot access, do not appear in either navigation view.

Drag launcher tiles or pinned icons to reorder each view independently. You can also select a tile and use **Move earlier** / **Move later**, or focus a tile and use Alt+arrow keys. Changes save automatically, and **Save Changes** can retry a failed save.

Existing **Always show** choices initialize desktop pins. The previous app order initializes both sequences; an existing alphabetical overflow order initializes the launcher order. Explicit visibility and order changes are stored separately. The active app's own menu and custom menu items remain available on its pages.

### Admin Color Scheme

WpApp uses the current user's WordPress admin color scheme for navigation and exposes it to app styles. See [Colors and Dark Mode](colors.md) for the token reference, usage examples, and filters.

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

### Inside OpenStation

When an app is opened in an [OpenStation](https://wordpress.org/plugins/desktop-mode/) window, the shell already shows the site and user menu, so neither the WordPress admin bar nor the custom masterbar is rendered there. Instead the app's menu items with a link are exposed as the window's tabs, the same way an admin menu's submenu is, and the `<body>` gets a `wp-app-chromeless` class. See [Launcher Integration](configuration.md#launcher-integration).

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

Keep custom CSS in external files and register it with `wp_app_enqueue_style()` before the head is printed. See [Assets](assets.md) for examples.

### Custom Scripts

Load external JavaScript with `wp_app_enqueue_script()` and pass dynamic values through data attributes or a configuration response. See [Assets](assets.md#dynamic-values).

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
