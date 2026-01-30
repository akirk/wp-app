# Configuration

WpApp can be configured via the constructor's config array or through method calls.

## Constructor Configuration

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'app_name'                     => 'My App',
	'show_masterbar_for_anonymous' => true,
	'show_wp_logo'                 => false,
	'show_site_name'               => true,
	'show_dark_mode_toggle'        => false,
	'add_app_node'                 => true,
	'require_login'                => false,
	'require_capability'           => null,
	'clear_admin_bar'              => false,
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
| `show_dark_mode_toggle` | bool | `false` | Show dark mode toggle button |
| `add_app_node` | bool | `true` | Add the main app entry to the admin bar. Set to `false` if your CPT or plugin already adds its own admin bar entry |
| `clear_admin_bar` | bool | `false` | Remove all WordPress admin bar items, showing only app items |

### Access Control

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `require_login` | bool | `false` | Require users to be logged in (shortcut for `require_capability => 'read'`) |
| `require_capability` | string | `null` | WordPress capability required to access the app |

## Method Configuration

All options can also be set via methods after instantiation:

```php
$app = new WpApp( __DIR__ . '/templates', 'my-app' );

// Masterbar settings
$app->show_masterbar_for_anonymous( true );
$app->show_dark_mode_toggle( true );
$app->add_app_node( false );  // Disable if CPT adds its own entry
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
	'add_app_node' => false,
] );
```

## Related Documentation

- [Masterbar](masterbar.md) - Detailed admin bar customization
- [Access Control](access-control.md) - Capabilities and roles
