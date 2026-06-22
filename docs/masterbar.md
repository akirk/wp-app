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

### Global App Visibility

Use **Settings > WP Apps** to control how registered app links appear across the masterbar. `Only show the active app by default` keeps inactive apps out of the top-level masterbar unless an individual app is marked `Always show this menu entry`.

Enable `Show inactive apps in the overflow menu on app pages` to keep the current app visible while collecting the other registered app links under the `Apps` overflow menu.

### Admin Color Scheme

WpApp outputs CSS custom properties from the current user's WordPress admin color scheme and uses them for the WordPress admin bar and anonymous-user masterbar. This keeps app navigation aligned with each user's selected admin profile color.

Use these variables in app CSS:

| Variable | Purpose |
|----------|---------|
| `--wp-app-color-primary` | Primary app action color |
| `--wp-app-color-primary-hover` | Hover/focus color for primary actions |
| `--wp-app-color-on-primary` | Text/icon color on primary actions |
| `--wp-app-color-error` | Error/emphasis color derived from the admin accent |
| `--wp-app-color-scheme` | Current app color scheme hint, `light` or `dark` |
| `--wp-app-color-background` | Default app page background |
| `--wp-app-color-surface` | Card/panel background |
| `--wp-app-color-surface-alt` | Subtle secondary background |
| `--wp-app-color-text` | Primary text color |
| `--wp-app-color-muted` | Secondary text color |
| `--wp-app-color-border` | Border color |
| `--wp-app-color-link` | Link color |
| `--wp-app-color-link-hover` | Link hover/focus color |
| `--wp-app-masterbar-background` | Masterbar/admin-bar background |
| `--wp-app-masterbar-highlight` | Masterbar hover and accent color |
| `--wp-app-masterbar-text` | Masterbar text color |

```css
.button-primary {
	background: var(--wp-app-color-primary);
	color: var(--wp-app-color-on-primary);
}

.button-primary:hover {
	background: var(--wp-app-color-primary-hover);
}
```

Use tokens as pairs so text and backgrounds switch together between light and dark mode:

| Surface | Text | Supporting tokens |
|---------|------|-------------------|
| `--wp-app-color-background` | `--wp-app-color-text` | Page-level background and primary text |
| `--wp-app-color-surface` | `--wp-app-color-text` | Cards, panels, and primary content areas |
| `--wp-app-color-surface-alt` | `--wp-app-color-muted` or `--wp-app-color-text` | Secondary panels, callouts, and subdued areas |
| `--wp-app-color-primary` | `--wp-app-color-on-primary` | Primary buttons, selected states, and important actions |
| `--wp-app-color-secondary` | `--wp-app-color-secondary-text` | Secondary buttons and lower-emphasis actions |
| `--wp-app-masterbar-background` | `--wp-app-masterbar-text` | Masterbar and navigation chrome |

Avoid combining tokenized backgrounds with hard-coded foreground colors, or hard-coded backgrounds with tokenized foreground colors. Those combinations can look correct in one mode and fail in the other.

```css
/* Good: both values adapt together. */
.app-card {
	background: var(--wp-app-color-surface);
	color: var(--wp-app-color-text);
	border: 1px solid var(--wp-app-color-border);
}

/* Good: primary text color is chosen for the primary background. */
.button-primary {
	background: var(--wp-app-color-primary);
	color: var(--wp-app-color-on-primary);
}

/* Avoid: white text may disappear if the primary color changes. */
.button-primary {
	background: var(--wp-app-color-primary);
	color: white;
}

/* Avoid: this hard-coded light panel will stay light in dark mode. */
.app-card {
	background: #fff;
	color: var(--wp-app-color-text);
}
```

For lower-level access, `wp_app_get_admin_color_scheme()` returns the normalized WordPress scheme and `wp_app_get_admin_color_scheme_css()` returns the generated custom-property block.

By default, WpApp uses `auto` color mode: it emits light tokens and a `prefers-color-scheme: dark` override. Force a mode with the `wp_app_color_mode` filter:

```php
add_filter( 'wp_app_color_mode', function() {
	return 'dark'; // Accepts 'auto', 'light', or 'dark'.
} );
```

WpApp also applies conservative defaults for `body.wp-app-body`, links, focus outlines, `.button-primary`, and `.button` so simple app templates pick up the admin color scheme automatically. Disable those defaults with the `wp_app_output_default_color_styles` filter if your app has a fully custom design system.

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
