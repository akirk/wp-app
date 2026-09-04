<?php
/**
 * Community App - Posts Template
 * All published posts, newest first
 */

global $wpdb;

$posts = $wpdb->get_results(
    "SELECT p.*, u.display_name AS author_name
     FROM {$wpdb->prefix}webapp_posts p
     LEFT JOIN {$wpdb->users} u ON u.ID = p.author_id
     WHERE p.status = 'published'
     ORDER BY p.created_at DESC
     LIMIT 50"
);
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php wp_app_the_title( 'Posts' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="community-app-container">
    <div class="page-header">
        <h1>Community Posts</h1>
        <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( home_url( '/community/posts/create' ) ); ?>" class="button button-primary">Create New Post</a>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $posts ) ) : ?>
        <div class="posts-list">
            <?php foreach ( $posts as $post ) : ?>
                <div class="post-item">
                    <h3><a href="<?php echo esc_url( home_url( '/community/posts/' . $post->id ) ); ?>"><?php echo esc_html( $post->title ); ?></a></h3>
                    <p class="post-meta">
                        <a href="<?php echo esc_url( home_url( '/community/profile/' . $post->author_id ) ); ?>"><?php echo esc_html( $post->author_name ?: 'Unknown' ); ?></a>
                        <span class="post-date"><?php echo esc_html( date( 'M j, Y', strtotime( $post->created_at ) ) ); ?></span>
                    </p>
                    <p class="post-excerpt"><?php echo esc_html( wp_trim_words( $post->content, 30 ) ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="empty-state">
            <p>No posts yet. Be the first!</p>
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url( '/community/posts/create' ) ); ?>" class="button button-primary">Create the First Post</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="nav-buttons">
        <a href="<?php echo esc_url( home_url( '/community' ) ); ?>" class="button">← Home</a>
    </p>
</div>

</body>
</html>
