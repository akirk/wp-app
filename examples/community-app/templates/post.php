<?php
/**
 * Community App - Single Post Template
 * Serves /community/posts/{post_id}
 */

global $wpdb;

$post_id = intval( wp_app_get_route_var( 'post_id' ) );
$post    = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT p.*, u.display_name AS author_name
         FROM {$wpdb->prefix}webapp_posts p
         LEFT JOIN {$wpdb->users} u ON u.ID = p.author_id
         WHERE p.id = %d",
        $post_id
    )
);

if ( ! $post ) {
    status_header( 404 );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( $post ? $post->title : 'Post not found' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="community-app-container">
    <?php if ( $post ) : ?>
        <article class="post-single">
            <h1><?php echo esc_html( $post->title ); ?></h1>
            <p class="post-meta">
                <a href="<?php echo esc_url( home_url( '/community/profile/' . $post->author_id ) ); ?>"><?php echo esc_html( $post->author_name ?: 'Unknown' ); ?></a>
                <span class="post-date"><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $post->created_at ) ) ); ?></span>
            </p>
            <div class="post-content">
                <?php echo wp_kses_post( wpautop( $post->content ) ); ?>
            </div>
        </article>
    <?php else : ?>
        <div class="empty-state">
            <p>That post does not exist.</p>
        </div>
    <?php endif; ?>

    <p class="nav-buttons">
        <a href="<?php echo esc_url( home_url( '/community/posts' ) ); ?>" class="button">← All Posts</a>
    </p>
</div>

</body>
</html>
