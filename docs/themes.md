# App Themes

[Documentation index](README.md)

An app or an add-on plugin can register named themes. WpApp adds a **Themes** submenu,
stores each logged-in user's choice separately for each app, and looks for route
templates in the selected theme directory before falling back to the app's regular
template directory.

Register themes on the app-specific initialization filter. Direct registration
is rejected so translating a theme name cannot trigger translation loading
before WordPress `init`:

```php
add_filter(
	'wp_app_init_reader',
	function ( $app ) {
		$app->register_theme(
			'compact',
			__( 'Compact', 'my-plugin' ),
			plugin_dir_path( __FILE__ ) . 'templates/compact'
		);
		return $app;
	}
);
```

When an app registers more than one theme, administrators can choose its site-wide
default under **Settings > WP Apps**. That default is used until a user makes a
personal selection; an existing personal selection continues to take precedence.

The built-in `default` theme always represents the template directory passed to the
`WpApp` constructor. A theme directory only needs to contain templates it changes:
if `templates/compact/index.php` exists but `details.php` does not, the compact theme
uses its own `index.php` and the app's normal `details.php`.

Add-on plugins can register themes without owning the app instance by using its
app-specific initialization filter:

```php
add_filter(
	'wp_app_init_reader',
	function ( $app ) {
		$app->register_theme(
			'newspaper',
			__( 'Newspaper', 'reader-newspaper' ),
			plugin_dir_path( __FILE__ ) . 'templates'
		);
		return $app;
	}
);
```

Theme-specific assets can be loaded when that theme is selected. The hook suffix is
the normalized app path followed by the theme slug:

```php
add_action(
	'wp_app_load_theme_reader_newspaper',
	function () {
		wp_app_enqueue_style(
			'reader-newspaper',
			plugins_url( 'assets/newspaper.css', __FILE__ ),
			[],
			'1.0.0',
			'reader'
		);
	}
);
```

Use `$app->get_selected_theme()` to inspect the current slug and
`$app->get_themes()` to retrieve all registrations.

## Related Documentation

- [Templates](templates.md) — page structure and template helpers
- [Assets](assets.md) — scoped CSS and JavaScript
- [Colors](colors.md) — admin color tokens and light/dark mode
