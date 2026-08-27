# Access Control

WpApp uses WordPress capabilities to control access to your app and individual routes. If the app registers post types or taxonomies, declare them with the `post_types` / `taxonomies` options so their REST reads are gated too — see [REST API Access Control](#rest-api-access-control).

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

The framework provides that lock, but it cannot know which types are yours: tell
it with the `post_types` / `taxonomies` app options (described below), and it gates
their REST reads with the app's capability. Treat REST access as part of the app's
access design, not an afterthought: for each post type and taxonomy the app
registers, decide who may read it over REST and declare it accordingly.

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
REST unless the REST layer is gated too.

### Declaring the app's types

List the post types and taxonomies the app owns in its config. The framework then
gates their REST reads with the app's capability, injecting `show_in_rest => true`
and its gated controller into your `register_post_type()` / `register_taxonomy()`
calls — those calls need no REST-specific arguments at all:

```php
$app = new WpApp( __DIR__ . '/templates', 'notes', [
    'require_capability' => 'read',
    'post_types'         => [ 'note' ],
    'taxonomies'         => [ 'note_tag' ],
] );

add_action( 'init', function () {
    register_post_type( 'note', [
        'public'   => false,
        'supports' => [ 'title', 'editor', 'author', 'custom-fields' ],
    ] );
    register_taxonomy( 'note_tag', 'note', [ 'public' => false ] );
} );
```

The capability is the one the app requires: `'read'` for a login-only app
(`require_login => true`, the default), `'edit_posts'` for an editor-only app, and
so on. A public app (`require_login => false`) gates its types to logged-in users.
After this, anonymous reads return `401`; a logged-in user without the capability
gets `403`. The block editor keeps working, since it reads as a logged-in user.

To give a type a capability different from the app's, use the map form:

```php
'post_types' => [
    'note'    => 'read',          // any logged-in reader
    'invoice' => 'manage_options', // administrators only
],
```

Declaring types this way also marks them as app-owned for launchers: OpenStation
hides their admin menus from its dock, because the app window is where that content
is managed (see [Launcher Integration](configuration.md#launcher-integration)).

### Gating a type by hand

`WpApp\Rest\Access` is the layer behind the config option, and can be called
directly — for a type registered outside the app's config, or when you need the
per-object form below. `Access::protect_post_type()` / `Access::protect_taxonomy()`
record the required capability and return the gated controller class:

```php
use WpApp\Rest\Access;

add_action( 'init', function () {
    register_post_type( 'note', [
        'public'                => false,
        'show_in_rest'          => true, // needed for the block editor
        'rest_controller_class' => Access::protect_post_type( 'note', 'read' ),
    ] );
} );
```

Pass `null` as the capability to require only that the caller be logged in.
(Requires this plugin to depend on `akirk/wp-app ^1.5` or newer, where
`WpApp\Rest\Access` exists.)

### Per-object capabilities (meta caps)

Single-item reads are checked **with the object id**, so you can gate on a
WordPress *meta* capability and let your existing `map_meta_cap` rules (ownership,
share tokens, …) apply to REST automatically. Pass the meta cap as the item
capability and a coarser primitive cap for the collection (which has no id):

```php
// Item read -> current_user_can( 'read_trip', $trip_id ) -> your map_meta_cap.
// Collection -> current_user_can( 'read' ) (login-level; a listing can't be
// per-object at the permission stage).
Access::protect_post_type( 'trip', 'read_trip', 'read' );
```

With a single primitive capability (e.g. `'read'`) the collection capability
defaults to the same value, so most apps pass just one.

**Compliance check:** every app-owned post type / taxonomy registered with
`show_in_rest => true` should be listed in the app's `post_types` / `taxonomies`,
or wire its `rest_controller_class` through `Access::protect_post_type()` /
`Access::protect_taxonomy()`. Auditing a plugin is then a grep: each
`show_in_rest` registration should match one of those (minus any type deliberately
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
