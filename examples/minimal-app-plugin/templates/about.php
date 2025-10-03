<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'About' ); ?></title>
    <?php wp_app_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .back-link { margin: 20px 0; }
        .back-link a {
            padding: 10px 20px;
            background: #ddd;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<h1>About This App</h1>

<p>This demonstrates the minimal setup required for a wp-app.</p>

<p>The framework automatically provided:</p>
<ul>
    <li>Routing system</li>
    <li>Template discovery</li>
    <li>WordPress admin bar integration</li>
    <li>App menu item</li>
</ul>

<div class="back-link">
    <a href="<?php echo home_url( '/my-minimal-app' ); ?>">← Back to App Home</a>
</div>

</body>
</html>