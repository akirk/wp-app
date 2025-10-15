<?php
/**
 * App home template example
 */
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title(); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
<?php wp_app_body_open(); ?>

<div class="wp-app-container">
    <h1>Welcome to My App</h1>

    <?php if ( is_user_logged_in() ) : ?>
        <p>Hello, <?php echo esc_html( wp_get_current_user()->display_name ); ?>!</p>

        <div class="app-navigation">
            <a href="<?php echo esc_url( home_url( '/app/dashboard' ) ); ?>" class="button">Go to Dashboard</a>
            <a href="<?php echo esc_url( home_url( '/app/profile/' . get_current_user_id() ) ); ?>" class="button">View Profile</a>
        </div>
    <?php else : ?>
        <p>Please <a href="<?php echo esc_url( wp_login_url( home_url( '/app' ) ) ); ?>">log in</a> to access the app.</p>
    <?php endif; ?>
</div>

<style>
.wp-app-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.app-navigation {
    margin-top: 20px;
}

.app-navigation .button {
    display: inline-block;
    margin-right: 10px;
    padding: 10px 20px;
    background: #0073aa;
    color: white;
    text-decoration: none;
    border-radius: 3px;
}

.app-navigation .button:hover {
    background: #005a87;
    color: white;
}
</style>

<?php wp_app_body_close(); ?>
</body>
</html>