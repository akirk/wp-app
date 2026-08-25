<?php
/**
 * Community App - Create Post Template
 * The form posts back to this URL; CommunityApp::maybe_handle_create_post()
 * stores the post, awards points and redirects to it.
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( home_url( '/community/posts/create' ) ) );
    exit;
}

$missing = ! empty( $_GET['missing'] );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'Create Post' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<div class="community-app-container">
    <h1>Create a Post</h1>

    <?php if ( $missing ) : ?>
        <p class="form-error">Please fill in both a title and some content.</p>
    <?php endif; ?>

    <form method="post" class="post-form">
        <?php wp_nonce_field( 'community_create_post' ); ?>
        <input type="hidden" name="community_create_post" value="1">

        <p>
            <label for="post-title">Title</label>
            <input type="text" id="post-title" name="title" required>
        </p>
        <p>
            <label for="post-content">Content</label>
            <textarea id="post-content" name="content" rows="10" required></textarea>
        </p>
        <p class="action-buttons">
            <button type="submit" class="button button-primary">Publish</button>
            <a href="<?php echo esc_url( home_url( '/community/dashboard' ) ); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

</body>
</html>
