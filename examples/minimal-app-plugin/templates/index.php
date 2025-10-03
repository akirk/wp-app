<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
    <title><?php echo wp_app_title( 'My Minimal App' ); ?></title>
    <?php wp_app_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .app-nav { margin: 20px 0; }
        .app-nav a {
            display: inline-block;
            margin-right: 20px;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body class="wp-app-body">

<?php wp_app_body_open(); ?>

<h1>Welcome to My Minimal App</h1>

<p>This is the simplest possible wp-app implementation.</p>

<div class="app-nav">
    <a href="<?php echo home_url( '/my-minimal-app/about' ); ?>">About</a>
    <a href="<?php echo home_url( '/my-minimal-app/contact' ); ?>">Contact</a>
</div>

<p><strong>Features provided automatically by wp-app:</strong></p>
<ul>
    <li>✅ Route for app home (this page)</li>
    <li>✅ WordPress admin bar integration</li>
    <li>✅ App menu item in masterbar</li>
    <li>✅ Clean HTML head generation</li>
    <li>✅ Template discovery</li>
</ul>

</body>
</html>