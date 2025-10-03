# WP-App Tutorial: From Minimal to Full-Featured

This tutorial will guide you through building web applications using the wp-app framework, starting from the minimal example and gradually adding more features.

## Why Build on WordPress?

Before diving into code, let's understand why WordPress is an excellent foundation for web applications:

- **🔐 User system included** - Login, registration, roles, and permissions work out of the box
- **🌐 Easy hosting** - Deploy anywhere WordPress runs (which is everywhere)
- **🔒 Security handled** - Get automatic security updates and proven security practices
- **🧩 Rich plugin ecosystem** - Integrate payments, email, analytics, and thousands of other features
- **📱 Admin interface** - Professional backend for managing users and content without building from scratch

The wp-app framework lets you build modern applications while getting all these benefits for free.

## Getting Started: The Minimal Example

The simplest possible wp-app requires just 3 lines of PHP. Check out `examples/minimal-app/`:

```php
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
    'show_wp_logo' => false,                 // Hide WordPress logo
    'show_site_name' => true,               // Show your site name
    'require_login' => true                 // Require login to access (alias for require_capability => 'read')
] );
$app->init();
```

This automatically gives you:
- A route at `/minimal` that loads `templates/index.php`
- A navigation bar that works for both logged-in and anonymous users
- Clean HTML output separate from your WordPress theme
- Professional styling and responsive design

## Step 1: Adding More Pages

Create additional templates in your `templates/` directory:

```
templates/
├── index.php          # Handles /minimal
├── about.php          # Handles /minimal/about
└── contact.php        # Handles /minimal/contact
```

The framework auto-discovers these templates - no configuration needed!

## Step 2: Dynamic Routes with Variables

Create templates with variables in their names:

```
templates/
├── user/{id}.php      # Handles /minimal/user/123
├── posts/{slug}.php   # Handles /minimal/posts/my-post
└── profile/{username}/edit.php  # Handles /minimal/profile/john/edit
```

Access variables in your templates:
```php
$user_id = get_query_var( 'id' );
$post_slug = get_query_var( 'slug' );
$username = get_query_var( 'username' );
```

## Step 3: Using the Database Manager

Add database functionality to track user data:

```php
$app = new WpApp( __DIR__ . '/templates', 'minimal' );

// Create a simple table
$app->database()->create_table( 'user_visits', [
    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
    'user_id' => 'bigint(20) unsigned NOT NULL',
    'page_visited' => 'varchar(255) NOT NULL',
    'visit_time' => 'datetime NOT NULL',
    'PRIMARY KEY' => '(id)'
] );

$app->init();
```

## Step 4: Customizing the Admin Bar

Add custom menu items and modify the admin bar:

```php
$app = new WpApp( __DIR__ . '/templates', 'minimal' );

// Add custom menu items
$app->add_menu_item( 'dashboard', 'My Dashboard', home_url( '/minimal/dashboard' ) );
$app->add_menu_item( 'settings', 'Settings', home_url( '/minimal/settings' ) );

// For clean app-only admin bar, remove all WordPress items
$app->clear_admin_bar();

// Or just clear your app's menu items
$app->clear_menu_items();

$app->init();
```

## Step 5: Access Control and User Management

Control who can access your app with WordPress's capability system:

### Require Login for Entire App

```php
$app = new WpApp( __DIR__ . '/templates', 'minimal' );

// Require users to be logged in (have 'read' capability)
$app->require_capability( 'read' );

$app->init();
```

### Per-Route Access Control

```php
// Public route - anyone can access
$app->route( 'about', 'about.php' );

// Logged-in users only
$app->route( 'dashboard', 'dashboard.php', [], 'read' );

// Editors and above only
$app->route( 'admin', 'admin.php', [], 'edit_pages' );
```

### Custom App Roles

Create specific roles for your app that automatically appear in wp-admin:

```php
$app = new WpApp( __DIR__ . '/templates', 'community' );

// Create custom roles for your app
$app->add_role( 'member', 'Community Member', [ 'read' => true ] );
$app->add_role( 'moderator', 'Community Moderator', [
    'read' => true,
    'moderate_community' => true,
    'edit_community_posts' => true
] );

// Require minimum member access
$app->require_capability( 'read' );

// Moderator-only route
$app->route( 'moderate', 'moderate.php', [], 'moderate_community' );

$app->init();
```

The custom roles will automatically appear in each user's profile in wp-admin, allowing administrators to grant app-specific access easily.

## Step 6: WordPress Integration

Use WordPress functions in your templates:

```php
// templates/dashboard.php
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/minimal/dashboard' ) ) );
    exit;
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'My Dashboard' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
<?php wp_app_body_open(); ?>

<h1>Welcome, <?php echo esc_html( $current_user->display_name ); ?>!</h1>

</body>
</html>
```

## Step 7: Adding REST API Endpoints

Integrate with WordPress REST API:

```php
// In your main plugin file
add_action( 'rest_api_init', function() {
    register_rest_route( 'minimal-app/v1', '/user-stats', [
        'methods' => 'GET',
        'callback' => 'get_user_stats',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ] );
} );

function get_user_stats() {
    $current_user = wp_get_current_user();
    // Return user statistics
    return [
        'user_id' => $current_user->ID,
        'visits' => get_user_visit_count( $current_user->ID ),
        'last_login' => get_user_last_login( $current_user->ID )
    ];
}
```

## Complete Example: Community App

For a full-featured example showcasing all these concepts, see `examples/community-app/`. This mid-complexity example demonstrates:

- **User Authentication**: Login/logout integration with WordPress
- **Database Usage**: Custom tables for user progress and posts
- **Dynamic Routing**: User profiles, post viewing, dashboard
- **Admin Bar Customization**: Context-aware menu items
- **REST API Integration**: AJAX functionality using WordPress REST API
- **Responsive Design**: Mobile-friendly interface
- **User Progress Tracking**: Points system and achievements

The community app shows how to build a complete social platform with user profiles, content creation, and gamification features - all integrated seamlessly with WordPress while maintaining clean separation from your site's theme.

## Best Practices

1. **Template Organization**: Group related templates in subdirectories
2. **Security**: Always sanitize user input and check permissions
3. **WordPress Integration**: Use WordPress functions for consistency
4. **Database**: Let the framework handle table creation and updates
5. **Styling**: Use `wp_app_enqueue_style()` for CSS files
6. **URLs**: Keep URL paths short and meaningful

## Next Steps

- Explore the `examples/community-app/` for advanced patterns
- Add custom CSS and JavaScript to your templates
- Integrate with WordPress plugins and themes
- Build user authentication flows
- Create rich interactive experiences

The wp-app framework gives you the flexibility to build anything from simple landing pages to complex web applications, all while leveraging WordPress's powerful ecosystem.