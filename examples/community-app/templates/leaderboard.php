<?php
/**
 * Community App - Leaderboard Template
 * Top users by points; can be switched off in the admin settings.
 */

global $wpdb;

$enabled = CommunityApp::is_leaderboard_enabled();
$leaders = array();

if ( $enabled ) {
    $leaders = $wpdb->get_results(
        "SELECT pr.user_id, pr.level, pr.points, u.display_name,
                ( SELECT COUNT(*) FROM {$wpdb->prefix}webapp_posts p WHERE p.author_id = pr.user_id AND p.status = 'published' ) AS post_count
         FROM {$wpdb->prefix}webapp_progress pr
         LEFT JOIN {$wpdb->users} u ON u.ID = pr.user_id
         ORDER BY pr.points DESC, pr.last_activity DESC
         LIMIT 25"
    );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'Leaderboard' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="community-app-container">
    <h1>Leaderboard</h1>

    <?php if ( ! $enabled ) : ?>
        <div class="empty-state">
            <p>The leaderboard is turned off.</p>
        </div>
    <?php elseif ( ! empty( $leaders ) ) : ?>
        <table class="leaderboard">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Level</th>
                    <th>Points</th>
                    <th>Posts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $leaders as $rank => $leader ) : ?>
                    <tr<?php echo get_current_user_id() === intval( $leader->user_id ) ? ' class="is-you"' : ''; ?>>
                        <td><?php echo intval( $rank ) + 1; ?></td>
                        <td>
                            <a href="<?php echo esc_url( home_url( '/community/profile/' . $leader->user_id ) ); ?>">
                                <?php echo get_avatar( $leader->user_id, 28 ); ?>
                                <?php echo esc_html( $leader->display_name ?: 'Unknown' ); ?>
                            </a>
                        </td>
                        <td><?php echo intval( $leader->level ); ?></td>
                        <td><?php echo intval( $leader->points ); ?></td>
                        <td><?php echo intval( $leader->post_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="empty-state">
            <p>Nobody has earned points yet.</p>
        </div>
    <?php endif; ?>

    <p class="nav-buttons">
        <a href="<?php echo esc_url( home_url( '/community' ) ); ?>" class="button">← Home</a>
    </p>
</div>

</body>
</html>
