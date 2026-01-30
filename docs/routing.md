# Routing

WpApp provides pattern-based URL routing using WordPress rewrite rules.

## Basic Routes

Routes automatically map to template files in your templates directory:

```php
// /my-app -> templates/index.php
$app->route( '' );

// /my-app/dashboard -> templates/dashboard.php
$app->route( 'dashboard' );

// /my-app/about -> templates/about.php
$app->route( 'about' );
```

## Routes with Parameters

Use `{parameter}` syntax to capture URL segments:

```php
// /my-app/user/123 -> templates/user.php
$app->route( 'user/{user_id}' );

// /my-app/posts/456/edit -> templates/posts-edit.php
$app->route( 'posts/{post_id}/edit' );

// /my-app/category/news/page/2 -> templates/category.php
$app->route( 'category/{slug}/page/{page_num}' );
```

## Accessing Parameters in Templates

Use WordPress's `get_query_var()` to access route parameters:

```php
// In templates/user.php
$user_id = get_query_var( 'user_id' );
$user = get_user_by( 'id', $user_id );

if ( ! $user ) {
	echo '<p>User not found.</p>';
	return;
}

echo '<h1>' . esc_html( $user->display_name ) . '</h1>';
```

```php
// In templates/posts-edit.php
$post_id = get_query_var( 'post_id' );
$post = get_post( $post_id );
```

## Custom Template Names

By default, routes map to template files based on the pattern. You can specify a custom template:

```php
// /my-app/u/123 -> templates/user-profile.php (not templates/u.php)
$app->route( 'u/{user_id}', 'user-profile.php' );

// /my-app/p/{post_id} -> templates/single-post.php
$app->route( 'p/{post_id}', 'single-post.php' );
```

## Template Naming Convention

When no custom template is specified, WpApp generates template names from the route pattern:

| Route Pattern | Template File |
|--------------|---------------|
| `''` | `index.php` |
| `dashboard` | `dashboard.php` |
| `user/{user_id}` | `user.php` |
| `posts/{post_id}/edit` | `posts-edit.php` |
| `settings/account` | `settings-account.php` |

## Route Variables

Pass additional variables to templates:

```php
$app->route( 'dashboard', 'dashboard.php', [
	'page_title' => 'Dashboard',
	'show_sidebar' => true,
] );
```

Access in template:

```php
$page_title = get_query_var( 'page_title', 'Default Title' );
$show_sidebar = get_query_var( 'show_sidebar', false );
```

## Per-Route Access Control

Restrict individual routes by capability:

```php
// Public route
$app->route( 'about' );

// Logged-in users only
$app->route( 'dashboard', 'dashboard.php', [], 'read' );

// Editors and above
$app->route( 'manage', 'manage.php', [], 'edit_others_posts' );

// Administrators only
$app->route( 'admin', 'admin.php', [], 'manage_options' );
```

See [Access Control](access-control.md) for more details.

## Using Route Parameters

The `get_route_var()` method provides a convenient way to access parameters with defaults:

```php
// In your app class or template
$user_id = $app->get_route_var( 'user_id', 0 );
$page = $app->get_route_var( 'page_num', 1 );
```

## Flushing Rewrite Rules

WpApp automatically schedules rewrite rule flushes when you add routes. For immediate flushing (e.g., during plugin activation):

```php
public function activate() {
	$this->setup_routes();
	flush_rewrite_rules();
}
```

You can also manually flush by visiting any page with `?wp_app_flush=1` as an administrator.

## Related Documentation

- [Getting Started](getting-started.md) - Basic setup
- [Access Control](access-control.md) - Route-level permissions
- [BaseApp Pattern](baseapp.md) - Organized route setup
