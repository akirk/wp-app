# Rendering Gutenberg content

Render post content through `the_content` **before** calling `wp_app_head()` (or including a header partial that calls it), then print the rendered HTML in the body. Set up the post context first if the blocks need it.

```php
<?php
wp_app_enqueue_block_styles();
$content = apply_filters( 'the_content', $post->post_content );
require __DIR__ . '/partials/header.php'; // Calls wp_app_head().
echo $content; // Already rendered through the_content, as in a theme template.
require __DIR__ . '/partials/footer.php'; // Calls wp_app_body_close().
```

Rendering blocks queues their styles, script modules, and block support styles. With an active block theme, WordPress prints module import maps and some block support styles in the head. Rendering content afterward can leave native image lightboxes without their interactivity dependencies, even when the lightbox markup and script appear in the page. Keep `wp_app_body_close()` so WordPress can print the lightbox overlay and footer assets too.

WpApp also removes the active theme's `global-styles` stylesheet for theme isolation. That stylesheet includes shared WordPress layout rules such as `.is-layout-flex { display: flex; }`, wrapping, and grid rules. A gallery can therefore stack vertically even when its full `wp-block-gallery` and `wp-block-image` styles are present. Call `wp_app_enqueue_block_styles()` from the app template before the head, as shown above, to restore core default CSS without restoring the active theme's global styles.

The helper uses WordPress's generated core CSS and enqueue API, requires WordPress 5.9+, and is safe to call repeatedly. It applies to the current request, so call it only on pages displaying block content. It does not render content or enqueue every block's assets: keep rendering through `the_content` before the head so WordPress discovers those dependencies. Templates retain control of the page structure.

When diagnosing a broken gallery, inspect the actual CSS rules and computed styles, not just stylesheet IDs. WordPress can inline the complete block stylesheets under `wp-block-gallery-inline-css` and `wp-block-image-inline-css`; those IDs do not imply that the styles are only overrides. Verify flex display and wrapping on the gallery, column sizing on its images, the interactivity entry in the module import map, and the lightbox script and overlay. Confirm the layout and zoom behavior in the browser.

