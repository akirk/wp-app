<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'Minimal App' ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
<?php wp_app_body_open(); ?>

<h1>Hello from Minimal App!</h1>
<p>This page is rendered through WpApp.</p>

</body>
</html>
