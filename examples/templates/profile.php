<?php
/**
 * Profile template example
 */

// Get the WpApp instance (this would be available globally in real usage)
global $app;

$params = $app->get_route_params();
$user_id = intval( $params['user_id'] );
$user = get_user_by( 'ID', $user_id );

if ( ! $user ) {
    wp_die( 'User not found' );
}
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
    <h1><?php echo esc_html( $user->display_name ); ?>'s Profile</h1>

    <div class="profile-info">
        <div class="avatar">
            <?php echo get_avatar( $user->ID, 80 ); ?>
        </div>

        <div class="user-details">
            <p><strong>Username:</strong> <?php echo esc_html( $user->user_login ); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html( $user->user_email ); ?></p>
            <p><strong>Registered:</strong> <?php echo esc_html( date( 'F j, Y', strtotime( $user->user_registered ) ) ); ?></p>
        </div>
    </div>

    <?php if ( get_current_user_id() == $user_id ) : ?>
        <div class="profile-actions">
            <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" class="button">Edit Profile in WordPress</a>
        </div>
    <?php endif; ?>

    <div class="app-navigation">
        <a href="<?php echo esc_url( home_url( '/app' ) ); ?>" class="button">Back to App Home</a>
        <a href="<?php echo esc_url( home_url( '/app/dashboard' ) ); ?>" class="button">Dashboard</a>
    </div>
</div>

<style>
.wp-app-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.profile-info {
    display: flex;
    gap: 20px;
    margin: 30px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 5px;
}

.user-details p {
    margin: 10px 0;
}

.profile-actions,
.app-navigation {
    margin-top: 20px;
}

.button {
    display: inline-block;
    margin-right: 10px;
    padding: 10px 20px;
    background: #0073aa;
    color: white;
    text-decoration: none;
    border-radius: 3px;
}

.button:hover {
    background: #005a87;
    color: white;
}
</style>

<script>
// Example of using the API endpoint
fetch('/api/user-data/<?php echo $user_id; ?>')
    .then(response => response.json())
    .then(data => {
        console.log('User app data:', data);
    });
</script>

<?php wp_app_body_close(); ?>
</body>
</html>