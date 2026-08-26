# Access Control

WpApp uses WordPress capabilities to control access to your app and individual routes. If you make use of post types, taxonomies or the REST API, don't forget to [secure that, too](#rest-api-access-control).

## App-Wide Access Control

### Require Login

Apps require a logged-in user by default. To make an app public, set `require_login => false`:

```php
// Explicit (also the default)
$app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'require_login' => true,
] );

// Public app - opt out of the default
$public_app = new WpApp( __DIR__ . '/templates', 'my-app', [
	'require_login' => false,
] );
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

### How `require_login` and `require_capability` work together

`require_login` is the simple switch: does a visitor need to be logged in at all?
`require_capability` is the precise one: which WordPress capability must the user
have? They resolve to a single requirement:

| `require_login` | `require_capability` | Result |
|-----------------|----------------------|--------|
| `true` (default) | not set | logged-in user (`read`) |
| `false` | not set | public, no login |
| any | `'manage_options'` | `manage_options` |

A capability always wins. Checking a capability only makes sense for a logged-in
user, so `require_capability` implies `require_login => true`; setting
`require_login => false` alongside a capability has no effect. The reverse also
holds: `require_login => true` never lowers an explicit capability to `read`.

`require_login => true` is therefore just shorthand for `require_capability =>
'read'` — every WordPress user, including subscribers, has `read`.

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
$public_app = new WpApp( __DIR__ . '/templates/public', 'public', [
	'require_login' => false,
] );
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

## REST API Access Control

`require_login` / `require_capability` gate the app's **front end** — its routes and
templates. They do **not** gate the WordPress REST API, because the REST API is not
part of your app: it is a second, independent door into the same data, served by
WordPress core at `/wp-json/`. Whenever your app stores data in a custom post type
or taxonomy registered with `show_in_rest => true` (which the block editor
requires), that door is open too, and it needs its own lock.

The framework provides that lock — `WpApp\Rest\Access`, described below — but it
cannot apply it for you, because only you know which of your types are private.
Treat REST access as part of the app's access design, not an afterthought: for each
post type and taxonomy the app registers, decide who may read it over REST and wire
the gate accordingly.

### What WordPress protects by default

Core's REST API was designed for a public blog, where published content is meant to
be seen by everyone. So the rule is simple:

- **Writes** are always checked against the user's capabilities.
- **Reads** of anything *published* are open to everyone, logged in or not.

For a private app that is the wrong default: an app record — a note, a trip, a
contact — is usually published the moment it is created, so to core it is public.

Three things about this are easy to get wrong:

1. **`public => false` doesn't help.** REST exposure is controlled by
   `show_in_rest` only. A post type hidden from the front end, search and the admin
   menu is still fully listed at `/wp-json/wp/v2/<type>`.
2. **You can't just turn `show_in_rest` off**, because the block editor needs it to
   load and save. Gate the reads instead of removing them.
3. **Post meta follows the post.** A meta field registered with `show_in_rest =>
   true` is readable by anyone who can read the post; its `auth_callback` only
   applies to writes.

So an app whose front end is login-only still leaks its notes/recipes/records over
REST unless the REST layer is gated too. Do that by pointing each type's
`rest_controller_class` at the framework's gate — `Access::protect_post_type()` /
`Access::protect_taxonomy()` record the required capability and return the gated
controller class, which requires that capability for reads while leaving the block
editor (which reads as a logged-in user) working:

```php
use WpApp\Rest\Access;

add_action( 'init', function () {
    register_post_type( 'note', [
        'public'                => false,
        'show_in_rest'          => true, // needed for the block editor
        'rest_controller_class' => Access::protect_post_type( 'note', 'read' ),
        'supports'              => [ 'title', 'editor', 'author', 'custom-fields' ],
    ] );

    register_taxonomy( 'note_tag', 'note', [
        'public'                => false,
        'show_in_rest'          => true,
        'rest_controller_class' => Access::protect_taxonomy( 'note_tag', 'read' ),
    ] );
} );
```

Pass the same capability you gave the app: `'read'` for a login-only app,
`'edit_posts'` for an editor-only app, and so on. Pass `null` to require only that
the caller be logged in. After this, anonymous reads return `401`; a logged-in user
without the capability gets `403`. (Requires this plugin to depend on
`akirk/wp-app ^1.5` or newer, where `WpApp\Rest\Access` exists.)

### Per-object capabilities (meta caps)

Single-item reads are checked **with the object id**, so you can gate on a
WordPress *meta* capability and let your existing `map_meta_cap` rules (ownership,
share tokens, …) apply to REST automatically. Pass the meta cap as the item
capability and a coarser primitive cap for the collection (which has no id):

```php
// Item read -> current_user_can( 'read_trip', $trip_id ) -> your map_meta_cap.
// Collection -> current_user_can( 'read' ) (login-level; a listing can't be
// per-object at the permission stage).
Access::protect_taxonomy( 'trip', 'read_trip', 'read' );
```

With a single primitive capability (e.g. `'read'`) the collection capability
defaults to the same value, so most apps pass just one.

**Compliance check:** every app-owned post type / taxonomy registered with
`show_in_rest => true` should wire its `rest_controller_class` through
`Access::protect_post_type()` / `Access::protect_taxonomy()`. Auditing a plugin is
then a grep: each `show_in_rest` registration should have a matching
`Access::protect_*` on its `rest_controller_class` (minus any type deliberately
left public).

### Deliberately public reads (share links)

If an app intentionally exposes some objects anonymously — e.g. a share-token
link — opt back in with the `wp_app_rest_public_read` filter, which runs before the
gate denies the request:

```php
add_filter( 'wp_app_rest_public_read', function ( $allow, $object_name, $request ) {
    if ( 'trip' === $object_name && my_app_request_has_valid_share_token( $request ) ) {
        return true;
    }
    return $allow;
}, 10, 3 );
```

Keep the default (`false`) for everything else so app data stays private.


## Related Documentation

- [Configuration](configuration.md) - Access control config options
- [Routing](routing.md) - Per-route capabilities
- [BaseApp Pattern](baseapp.md) - Organized access control setup
- [Abilities](abilities.md) - Reusing the app capability in ability permission callbacks
