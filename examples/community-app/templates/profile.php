<?php
/**
 * Community App - Profile Template
 * Serves /community/profile/{user_id}
 */

global $wpdb;

$user_id = intval( wp_app_get_route_var( 'user_id' ) );
$user    = get_user_by( 'ID', $user_id );

if ( ! $user ) {
    status_header( 404 );
} else {
    $progress = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
        $user->ID
    ) );

    $user_posts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}webapp_posts WHERE author_id = %d AND status = 'published' ORDER BY created_at DESC LIMIT 20",
        $user->ID
    ) );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( $user ? $user->display_name : 'User not found' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="community-app-container">
    <?php if ( $user ) : ?>
        <div class="dashboard-header">
            <div class="user-profile">
                <?php echo get_avatar( $user->ID, 80 ); ?>
                <div class="user-details">
                    <h1><?php echo esc_html( $user->display_name ); ?></h1>
                    <p class="user-email">Member since <?php echo esc_html( date( 'F Y', strtotime( $user->user_registered ) ) ); ?></p>
                </div>
            </div>

            <div class="progress-summary">
                <div class="stat-box level">
                    <div class="stat-number"><?php echo $progress ? intval( $progress->level ) : 1; ?></div>
                    <div class="stat-label">Level</div>
                </div>
                <div class="stat-box points">
                    <div class="stat-number"><?php echo $progress ? intval( $progress->points ) : 0; ?></div>
                    <div class="stat-label">Points</div>
                </div>
                <div class="stat-box posts">
                    <div class="stat-number"><?php echo count( $user_posts ); ?></div>
                    <div class="stat-label">Posts</div>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <h2>Posts by <?php echo esc_html( $user->display_name ); ?></h2>
            <?php if ( ! empty( $user_posts ) ) : ?>
                <div class="posts-list">
                    <?php foreach ( $user_posts as $post ) : ?>
                        <div class="post-item">
                            <h3><a href="<?php echo esc_url( home_url( '/community/posts/' . $post->id ) ); ?>"><?php echo esc_html( $post->title ); ?></a></h3>
                            <p class="post-meta">
                                <span class="post-date"><?php echo esc_html( date( 'M j, Y', strtotime( $post->created_at ) ) ); ?></span>
                            </p>
                            <p class="post-excerpt"><?php echo esc_html( wp_trim_words( $post->content, 20 ) ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <p>No posts yet.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="empty-state">
            <p>That user does not exist.</p>
        </div>
    <?php endif; ?>

    <p class="nav-buttons">
        <a href="<?php echo esc_url( home_url( '/community/leaderboard' ) ); ?>" class="button">Leaderboard</a>
        <a href="<?php echo esc_url( home_url( '/community' ) ); ?>" class="button">← Home</a>
    </p>
</div>

</body>
</html>
