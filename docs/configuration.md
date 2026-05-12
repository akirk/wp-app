# Configuration

WpApp can be configured via the constructor's config array or through method calls.

## Constructor Configuration

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'app_name'                     => 'My App',
	'show_masterbar_for_anonymous' => true,
	'show_wp_logo'                 => false,
	'show_site_name'               => true,
	'admin_bar_app_link'           => true,
	'require_login'                => false,
	'require_capability'           => null,
	'clear_admin_bar'              => false,
	'my_apps'                      => true,
	'my_apps_icon'                 => null,
] );
```

## All Options

### App Identity

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `app_name` | string | Auto-generated from URL path | Display name for your app in the admin bar |

### Masterbar Display

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `show_masterbar_for_anonymous` | bool | `true` | Show navigation bar for logged-out users |
| `show_wp_logo` | bool | `true` | Show WordPress logo in the masterbar |
| `show_site_name` | bool | `true` | Show site name in the masterbar |
| `admin_bar_app_link` | bool | `true` | Add the main app entry to the admin bar. Set to `false` if your CPT or plugin already adds its own admin bar entry |
| `clear_admin_bar` | bool | `false` | Remove all WordPress admin bar items, showing only app items |

WpApp automatically exposes the current user's WordPress admin color scheme as CSS custom properties on app pages. Use those variables in app styles instead of hard-coding brand colors.

### Access Control

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `require_login` | bool | `false` | Require users to be logged in (shortcut for `require_capability => 'read'`) |
| `require_capability` | string | `null` | WordPress capability required to access the app |

### My Apps Plugin Integration

Integrates with the [My Apps](https://wordpress.org/plugins/my-apps/) plugin to add your app to the launcher. Behind the scenes, this uses the `my_apps_plugins` filter.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `my_apps` | bool\|string | `true` | `false` to disable, `true` to enable with default name, or a string for custom name |
| `my_apps_icon` | string | `null` | URL to the app icon (e.g., `plugins_url( 'icon.png', __FILE__ )`) |

## Method Configuration

All options can also be set via methods after instantiation:

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app' );

// Masterbar settings
$app->show_masterbar_for_anonymous( true );
$app->admin_bar_app_link( false );  // Disable if CPT adds its own entry
$app->clear_admin_bar();

// Access control
$app->require_capability( 'edit_posts' );

// Via masterbar instance for additional control
$app->masterbar()->show_wp_logo( false );
$app->masterbar()->show_site_name( true );
```

## Common Configurations

### Public App (No Login Required)

```php
$app = new WpApp( __DIR__ . '/templates', 'public-app', [
	'show_masterbar_for_anonymous' => true,
] );
```

### Private App (Login Required)

```php
$app = new WpApp( __DIR__ . '/templates', 'private-app', [
	'require_login' => true,
	'show_masterbar_for_anonymous' => false,
] );
```

### Admin-Only App

```php
$app = new WpApp( __DIR__ . '/templates', 'admin-app', [
	'require_capability' => 'manage_options',
] );
```

### Clean App (No WordPress Branding)

```php
$app = new WpApp( __DIR__ . '/templates', 'clean-app', [
	'show_wp_logo'    => false,
	'clear_admin_bar' => true,
	'app_name'        => 'My Brand',
] );
```

### App with CPT Admin Bar Entry

If your custom post type already registers an admin bar entry, disable the automatic one:

```php
$app = new WpApp( __DIR__ . '/templates', 'my-cpt-app', [
	'admin_bar_app_link' => false,
] );
```

### App Styles Using Admin Colors

Use semantic variables for app UI and masterbar variables for navigation-specific overrides:

```css
.button-primary {
	background: var(--wp-app-color-primary);
}

.button-primary:hover {
	background: var(--wp-app-color-primary-hover);
}
```

To disable the automatic variable output, use the `wp_app_output_admin_color_scheme` filter:

```php
add_filter( 'wp_app_output_admin_color_scheme', '__return_false' );
```

WpApp also applies conservative defaults for app backgrounds, links, focus outlines, and standard `.button`/`.button-primary` elements. To keep the variables but disable those default styles:

```php
add_filter( 'wp_app_output_default_color_styles', '__return_false' );
```

### App with My Apps Integration

Register your app with the My Apps plugin launcher:

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'my_apps'      => 'My Custom App',  // or true for default name
	'my_apps_icon' => plugins_url( 'assets/icon.png', __FILE__ ),
] );
```

To disable My Apps integration:

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'my_apps' => false,
] );
```

## Related Documentation

- [Masterbar](masterbar.md) - Detailed admin bar customization
- [Access Control](access-control.md) - Capabilities and roles
