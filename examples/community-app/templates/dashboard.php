<?php
/**
 * My Web App - User Dashboard Template
 * Clean app template without WordPress theme interference
 */

// Redirect if not logged in
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/my-web-app/dashboard' ) ) );
    exit;
}

global $app, $wpdb;
$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'Dashboard' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<?php

// Get user progress
$progress = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
    $current_user->ID
) );

// Get user's posts
$user_posts = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}webapp_posts WHERE author_id = %d ORDER BY created_at DESC LIMIT 5",
    $current_user->ID
) );

// Get user progress
$progress = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
    $current_user->ID
) );

// Get user's posts
$user_posts = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}webapp_posts WHERE author_id = %d ORDER BY created_at DESC LIMIT 5",
    $current_user->ID
) );
?>

<div class="my-web-app-container">
    <div class="dashboard-header">
        <div class="user-profile">
            <?php echo get_avatar( $current_user->ID, 80 ); ?>
            <div class="user-details">
                <h1><?php echo esc_html( $current_user->display_name ); ?>'s Dashboard</h1>
                <p class="user-email"><?php echo esc_html( $current_user->user_email ); ?></p>
            </div>
        </div>

        <?php if ( $progress ) : ?>
            <div class="progress-summary">
                <div class="stat-box level">
                    <div class="stat-number"><?php echo intval( $progress->level ); ?></div>
                    <div class="stat-label">Level</div>
                </div>
                <div class="stat-box points">
                    <div class="stat-number"><?php echo intval( $progress->points ); ?></div>
                    <div class="stat-label">Points</div>
                </div>
                <div class="stat-box posts">
                    <div class="stat-number"><?php echo count( $user_posts ); ?></div>
                    <div class="stat-label">Posts</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-section">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="<?php echo esc_url( home_url( '/my-web-app/posts/create' ) ); ?>" class="button button-primary">
                    <span class="dashicon">+</span> Create New Post
                </a>
                <a href="<?php echo esc_url( home_url( '/my-web-app/profile/' . $current_user->ID ) ); ?>" class="button">
                    View My Profile
                </a>
                <button onclick="earnTestPoints()" class="button" id="earn-points-btn">
                    Earn Test Points
                </button>
            </div>
        </div>

        <div class="dashboard-section">
            <h2>Your Recent Posts</h2>
            <?php if ( ! empty( $user_posts ) ) : ?>
                <div class="posts-list">
                    <?php foreach ( $user_posts as $post ) : ?>
                        <div class="post-item">
                            <h3><a href="<?php echo esc_url( home_url( '/my-web-app/posts/' . $post->id ) ); ?>"><?php echo esc_html( $post->title ); ?></a></h3>
                            <p class="post-meta">
                                <span class="post-date"><?php echo esc_html( date( 'M j, Y', strtotime( $post->created_at ) ) ); ?></span>
                                <span class="post-status status-<?php echo esc_attr( $post->status ); ?>"><?php echo esc_html( ucfirst( $post->status ) ); ?></span>
                            </p>
                            <p class="post-excerpt"><?php echo esc_html( wp_trim_words( $post->content, 20 ) ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="view-all">
                    <a href="<?php echo esc_url( home_url( '/my-web-app/posts' ) ); ?>">View all posts →</a>
                </p>
            <?php else : ?>
                <div class="empty-state">
                    <p>You haven't created any posts yet.</p>
                    <a href="<?php echo esc_url( home_url( '/my-web-app/posts/create' ) ); ?>" class="button button-primary">Create Your First Post</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h2>Progress Overview</h2>
            <?php if ( $progress ) : ?>
                <div class="progress-details">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min( 100, ( $progress->points % 100 ) ); ?>%"></div>
                        <span class="progress-text"><?php echo $progress->points % 100; ?>/100 points to next level</span>
                    </div>

                    <div class="recent-activity">
                        <h4>Recent Activity</h4>
                        <p class="last-activity">Last seen: <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $progress->last_activity ) ) ); ?></p>
                    </div>

                    <?php if ( $progress->achievements ) : ?>
                        <div class="achievements">
                            <h4>Achievements</h4>
                            <div class="achievement-badges">
                                <?php
                                $achievements = json_decode( $progress->achievements, true );
                                if ( $achievements ) {
                                    foreach ( $achievements as $achievement ) {
                                        echo '<span class="achievement-badge">' . esc_html( $achievement ) . '</span>';
                                    }
                                } else {
                                    echo '<p class="no-achievements">No achievements yet. Keep posting to earn badges!</p>';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="no-progress">
                    <p>Start your journey by creating your first post!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.my-web-app-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 20px;
}

.user-details h1 {
    margin: 0 0 5px 0;
    color: #2c3e50;
}

.user-email {
    margin: 0;
    color: #7f8c8d;
}

.progress-summary {
    display: flex;
    gap: 20px;
}

.stat-box {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    min-width: 80px;
}

.stat-box.level { background: #3498db; color: white; }
.stat-box.points { background: #f39c12; color: white; }
.stat-box.posts { background: #27ae60; color: white; }

.stat-number {
    font-size: 1.8em;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 0.9em;
    opacity: 0.9;
    margin-top: 5px;
}

.dashboard-content {
    display: grid;
    gap: 30px;
}

.dashboard-section {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.dashboard-section h2 {
    margin: 0 0 20px 0;
    color: #2c3e50;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 12px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    border: none;
    cursor: pointer;
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
}

.posts-list {
    display: grid;
    gap: 20px;
}

.post-item {
    padding: 20px;
    border: 1px solid #ecf0f1;
    border-radius: 8px;
}

.post-item h3 {
    margin: 0 0 10px 0;
}

.post-item h3 a {
    color: #2c3e50;
    text-decoration: none;
}

.post-item h3 a:hover {
    color: #3498db;
}

.post-meta {
    display: flex;
    gap: 15px;
    margin: 0 0 10px 0;
    font-size: 0.9em;
    color: #7f8c8d;
}

.post-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    text-transform: uppercase;
}

.status-published { background: #d5f4e6; color: #27ae60; }
.status-draft { background: #fef5e7; color: #f39c12; }

.post-excerpt {
    margin: 0;
    color: #7f8c8d;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}

.progress-bar {
    position: relative;
    height: 30px;
    background: #ecf0f1;
    border-radius: 15px;
    margin-bottom: 20px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2980b9);
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.9em;
    font-weight: 500;
    color: #2c3e50;
}

.achievement-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.achievement-badge {
    background: #f39c12;
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8em;
}

.no-achievements {
    color: #7f8c8d;
    font-style: italic;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        text-align: center;
    }

    .progress-summary {
        justify-content: center;
    }

    .action-buttons {
        justify-content: center;
    }
}
</style>

<script>
function earnTestPoints() {
    const btn = document.getElementById('earn-points-btn');
    btn.disabled = true;
    btn.textContent = 'Earning...';

    const formData = new FormData();
    formData.append('points', '10');

    fetch('<?php echo esc_url( rest_url( 'my-web-app/v1/add-points' ) ); ?>', {
        method: 'POST',
        headers: {
            'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Refresh to show updated points
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error earning points');
        console.error(error);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Earn Test Points';
    });
}
</script>

</body>
</html>