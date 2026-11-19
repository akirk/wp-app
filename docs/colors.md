# Colors and Dark Mode

[Documentation index](README.md)

WpApp outputs CSS custom properties from the current user's WordPress admin color scheme and uses them for the WordPress admin bar and anonymous-user masterbar. This keeps app navigation aligned with each user's selected admin profile color.

Use these variables in app CSS:

| Variable | Purpose |
|----------|---------|
| `--wp-app-color-primary` | Primary app action color |
| `--wp-app-color-primary-hover` | Hover/focus color for primary actions |
| `--wp-app-color-on-primary` | Text/icon color on primary actions |
| `--wp-app-color-error` | Error/emphasis color derived from the admin accent |
| `--wp-app-color-scheme` | Current app color scheme hint, `light` or `dark` |
| `--wp-app-color-background` | Default app page background |
| `--wp-app-color-surface` | Card/panel background |
| `--wp-app-color-surface-alt` | Subtle secondary background |
| `--wp-app-color-text` | Primary text color |
| `--wp-app-color-muted` | Secondary text color |
| `--wp-app-color-border` | Border color |
| `--wp-app-color-link` | Link color |
| `--wp-app-color-link-hover` | Link hover/focus color |
| `--wp-app-masterbar-background` | Masterbar/admin-bar background |
| `--wp-app-masterbar-highlight` | Masterbar hover and accent color |
| `--wp-app-masterbar-text` | Masterbar text color |

```css
.button-primary {
	background: var(--wp-app-color-primary);
	color: var(--wp-app-color-on-primary);
}

.button-primary:hover {
	background: var(--wp-app-color-primary-hover);
}
```

Use tokens as pairs so text and backgrounds switch together between light and dark mode:

| Surface | Text | Supporting tokens |
|---------|------|-------------------|
| `--wp-app-color-background` | `--wp-app-color-text` | Page-level background and primary text |
| `--wp-app-color-surface` | `--wp-app-color-text` | Cards, panels, and primary content areas |
| `--wp-app-color-surface-alt` | `--wp-app-color-muted` or `--wp-app-color-text` | Secondary panels, callouts, and subdued areas |
| `--wp-app-color-primary` | `--wp-app-color-on-primary` | Primary buttons, selected states, and important actions |
| `--wp-app-color-secondary` | `--wp-app-color-secondary-text` | Secondary buttons and lower-emphasis actions |
| `--wp-app-masterbar-background` | `--wp-app-masterbar-text` | Masterbar and navigation chrome |

Avoid combining tokenized backgrounds with hard-coded foreground colors, or hard-coded backgrounds with tokenized foreground colors. Those combinations can look correct in one mode and fail in the other.

```css
/* Good: both values adapt together. */
.app-card {
	background: var(--wp-app-color-surface);
	color: var(--wp-app-color-text);
	border: 1px solid var(--wp-app-color-border);
}

/* Good: primary text color is chosen for the primary background. */
.button-primary {
	background: var(--wp-app-color-primary);
	color: var(--wp-app-color-on-primary);
}

/* Avoid: white text may disappear if the primary color changes. */
.button-primary {
	background: var(--wp-app-color-primary);
	color: white;
}

/* Avoid: this hard-coded light panel will stay light in dark mode. */
.app-card {
	background: #fff;
	color: var(--wp-app-color-text);
}
```

For lower-level access, `wp_app_get_admin_color_scheme()` returns the normalized WordPress scheme and `wp_app_get_admin_color_scheme_css()` returns the generated custom-property block.

By default, WpApp uses `auto` color mode: it emits light tokens and a `prefers-color-scheme: dark` override. Force a mode with the `wp_app_color_mode` filter:

```php
add_filter( 'wp_app_color_mode', function() {
	return 'dark'; // Accepts 'auto', 'light', or 'dark'.
} );
```

WpApp also applies conservative defaults for `body.wp-app-body`, links, focus outlines, `.button-primary`, and `.button` so simple app templates pick up the admin color scheme automatically. Disable those defaults with the `wp_app_output_default_color_styles` filter if your app has a fully custom design system.

To disable the automatic variable output entirely:

```php
add_filter( 'wp_app_output_admin_color_scheme', '__return_false' );
```

Keep app CSS in external files and load it as described in [Assets](assets.md). For navigation configuration, see [Masterbar](masterbar.md).
