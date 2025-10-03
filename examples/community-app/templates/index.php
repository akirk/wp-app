<?php
/**
 * My Web App - Home Template
 * Clean app template without WordPress theme interference
 */

global $app;
$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'My Web App' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="my-web-app-container">
    <div class="app-header">
        <h1>Welcome to My Web App</h1>
        <p class="app-tagline">Connect, create, and compete with other users!</p>
    </div>

    <?php if ( is_user_logged_in() ) : ?>
        <?php
        $current_user = wp_get_current_user();
        global $wpdb;

        $progress = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
            $current_user->ID
        ) );
        ?>

        <div class="user-welcome">
            <div class="user-avatar">
                <?php echo get_avatar( $current_user->ID, 60 ); ?>
            </div>
            <div class="user-info">
                <h2>Hello, <?php echo esc_html( $current_user->display_name ); ?>!</h2>
                <?php if ( $progress ) : ?>
                    <p class="user-stats">
                        <span class="level">Level <?php echo intval( $progress->level ); ?></span>
                        <span class="points"><?php echo intval( $progress->points ); ?> points</span>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="app-features">
            <div class="feature-card">
                <h3>Your Dashboard</h3>
                <p>View your progress, manage your posts, and track your achievements.</p>
                <a href="<?php echo esc_url( home_url( '/my-web-app/dashboard' ) ); ?>" class="button button-primary">Go to Dashboard</a>
            </div>

            <div class="feature-card">
                <h3>Community Posts</h3>
                <p>Read posts from other users and share your own content.</p>
                <a href="<?php echo esc_url( home_url( '/my-web-app/posts' ) ); ?>" class="button">Browse Posts</a>
            </div>

            <div class="feature-card">
                <h3>Leaderboard</h3>
                <p>See how you rank against other users in the community.</p>
                <a href="<?php echo esc_url( home_url( '/my-web-app/leaderboard' ) ); ?>" class="button">View Leaderboard</a>
            </div>
        </div>

    <?php else : ?>
        <div class="guest-welcome">
            <h2>Join Our Community</h2>
            <p>Create an account to start building your profile, posting content, and competing with other users!</p>

            <div class="auth-buttons">
                <a href="<?php echo esc_url( wp_login_url( home_url( '/my-web-app' ) ) ); ?>" class="button button-primary">Log In</a>
                <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="button">Sign Up</a>
            </div>
        </div>

        <div class="features-preview">
            <h3>What You Can Do</h3>
            <ul>
                <li>Create and share posts with the community</li>
                <li>Earn points and level up your profile</li>
                <li>Compete on the leaderboard</li>
                <li>Track your progress and achievements</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>
.my-web-app-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.app-header {
    text-align: center;
    margin-bottom: 40px;
}

.app-header h1 {
    font-size: 2.5em;
    color: #2c3e50;
    margin-bottom: 10px;
}

.app-tagline {
    font-size: 1.2em;
    color: #7f8c8d;
}

.user-welcome {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #f8f9fa;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 40px;
}

.user-info h2 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}

.user-stats {
    margin: 0;
}

.user-stats .level {
    background: #3498db;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.9em;
    margin-right: 10px;
}

.user-stats .points {
    background: #f39c12;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.9em;
}

.app-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.feature-card {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.feature-card h3 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.feature-card p {
    color: #7f8c8d;
    margin-bottom: 20px;
}

.guest-welcome {
    text-align: center;
    background: #ecf0f1;
    padding: 40px;
    border-radius: 10px;
    margin-bottom: 40px;
}

.auth-buttons {
    margin-top: 20px;
}

.auth-buttons .button {
    margin: 0 10px;
}

.features-preview {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.features-preview ul {
    list-style: none;
    padding: 0;
}

.features-preview li {
    padding: 10px 0;
    border-bottom: 1px solid #ecf0f1;
}

.features-preview li:before {
    content: "✓";
    color: #27ae60;
    font-weight: bold;
    margin-right: 10px;
}

.button {
    display: inline-block;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.button-primary {
    background: #3498db;
    color: white;
}

.button-primary:hover {
    background: #2980b9;
    color: white;
}

.button:not(.button-primary) {
    background: #ecf0f1;
    color: #2c3e50;
}

.button:not(.button-primary):hover {
    background: #bdc3c7;
    color: #2c3e50;
}
</style>

<script>
// Load user progress via WordPress REST API if logged in
<?php if ( is_user_logged_in() ) : ?>
fetch('<?php echo esc_url( rest_url( 'my-web-app/v1/user-progress/' . get_current_user_id() ) ); ?>', {
    method: 'GET',
    headers: {
        'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
    }
})
    .then(response => response.json())
    .then(data => {
        console.log('User progress:', data);
        // Could update UI with real-time progress data
    })
    .catch(error => console.log('Could not load user progress:', error));
<?php endif; ?>
</script>

</body>
</html>