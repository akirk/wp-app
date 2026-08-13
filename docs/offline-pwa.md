# Offline PWA Support

WpApp can provide the WordPress-specific plumbing needed to make an app installable and offline capable:

- app-scoped web app manifest URL
- app-scoped service worker URL
- manifest and theme-color tags in `wp_app_head()`
- service worker registration in `wp_app_body_close()`
- a small generated service worker with precache and same-app runtime caching

The service worker is served from the app path, not from the plugin assets directory. This matters because browser service worker scope is based on the service worker URL. A worker served from `/my-app/wp-app-service-worker` can control `/my-app/`, while one served from `/wp-content/plugins/my-plugin/` cannot.

## Quick Start

```php
use WpApp\WpApp;

$app = new WpApp(
	__DIR__ . '/templates',
	'field-notes',
	[
		'app_name' => 'Field Notes',
		'pwa'      => [
			'short_name'  => 'Notes',
			'theme_color' => '#14532d',
			'icons'       => [
				[
					'src'   => plugins_url( 'assets/icon-192.png', __FILE__ ),
					'sizes' => '192x192',
					'type'  => 'image/png',
				],
				[
					'src'   => plugins_url( 'assets/icon-512.png', __FILE__ ),
					'sizes' => '512x512',
					'type'  => 'image/png',
				],
			],
			'precache'    => [
				plugins_url( 'assets/app.css', __FILE__ ),
				plugins_url( 'assets/app.js', __FILE__ ),
			],
		],
	]
);

$app->init();
```

You can also enable it after constructing the app:

```php
$app->enable_pwa(
	[
		'name'        => 'Field Notes',
		'offline_url' => home_url( '/field-notes/offline' ),
		'precache'    => [
			home_url( '/field-notes/' ),
			home_url( '/field-notes/offline' ),
		],
	]
);
```

## Generated URLs

For an app at `/field-notes/`, WpApp generates:

| URL | Purpose |
|-----|---------|
| `/field-notes/wp-app-manifest` | Web app manifest JSON |
| `/field-notes/wp-app-service-worker` | Service worker JavaScript |

The app's existing rewrite rule handles these extensionless URLs, so no extra physical files are required.

## Configuration

| Option | Default | Description |
|--------|---------|-------------|
| `name` | App name | Manifest `name`. |
| `short_name` | `name` | Manifest `short_name` and iOS title. |
| `description` | unset | Manifest `description`. |
| `start_url` | App root URL | Manifest `start_url`. |
| `scope` | App root URL | Manifest `scope` and service worker registration scope. |
| `display` | `standalone` | Manifest display mode. |
| `theme_color` | `#2271b1` | Manifest and page `theme-color`. |
| `background_color` | `#ffffff` | Manifest background color. |
| `orientation` | unset | Optional manifest orientation. |
| `icons` | unset | Manifest icons array. |
| `offline_url` | App root URL | Fallback page for failed navigation requests. |
| `precache` | App root/offline URLs | URLs cached during service worker install. |
| `cache_name` | `wp-app-{app}-v1` | Cache Storage bucket name. Change this to expire old caches. |
| `manifest_path` | `wp-app-manifest` | App-relative manifest endpoint. |
| `service_worker_path` | `wp-app-service-worker` | App-relative service worker endpoint. |
| `service_worker_allowed` | Manifest scope path | `Service-Worker-Allowed` response header. Use `/` only when the app intentionally controls same-origin URLs outside its app path. |
| `cache_prefix` | `wp-app-{app}-` | Cache prefix used when deleting old app caches. |
| `cacheable_paths` | Manifest scope path | Same-origin URL paths the generated worker may cache. |
| `cacheable_search_params` | none | Search fragments that make a same-origin URL cacheable, for example public share URLs. |
| `cache_message_type` | `wp-app-cache-url` | Service worker message type for asking the worker to cache one or more URLs. |
| `cache_status_message_type` | `wp-app-cache-status` | Service worker response message type for URL cache status. |
| `version_message_type` | `wp-app-version` | Service worker message type for reporting the active cache/version. |
| `sync_tag` | `wp-app-sync` | Background Sync tag the service worker listens for. |
| `sync_message_type` | `wp-app-sync` | Client message posted when the sync event fires. |
| `client_cache_urls` | none | Extra URLs the browser helper asks the worker to cache/check after registration. |
| `client_cache_selector` | `[data-wp-app-cache-url]` | Elements whose URL availability should be reflected in markup. |
| `client_cache_url_attribute` | `data-wp-app-cache-url` | Attribute containing the URL to cache/check. Falls back to `href` or `src`. |
| `client_cache_available_attribute` | `data-wp-app-cache-available` | Attribute toggled on cacheable elements when the worker reports them cached. |
| `client_cache_status_event` | `wp-app-pwa-cache-status` | Browser event fired when cache status is reported. |
| `client_version_event` | `wp-app-pwa-version` | Browser event fired when the service worker reports its cache/version. |
| `client_sync_event` | `wp-app-pwa-sync` | Browser event fired when Background Sync asks clients to replay queued work. |
| `manifest_callback` | unset | Callable that returns manifest overrides for contextual manifests. |
| `head_tags` | `true` | Set to `false` when templates output contextual manifest tags themselves. |
| `register_service_worker` | `true` | Set to `false` when an app-specific runtime registers the generated worker itself. |

## Cache Status UI

For simple “available offline” indicators, mark links or media with `data-wp-app-cache-url`:

```php
<a href="<?php echo esc_url( $download_url ); ?>" data-wp-app-cache-url>
	Download itinerary
</a>
```

After service worker registration, WpApp asks the worker to cache/check those URLs. When a URL is cached, the element gets `data-wp-app-cache-available`:

```css
[data-wp-app-cache-url]::after {
	content: 'Not offline';
}

[data-wp-app-cache-available]::after {
	content: 'Available offline';
}
```

Apps that need a richer status panel can listen for events:

```js
window.addEventListener('wp-app-pwa-cache-status', function(event) {
	console.log(event.detail.cachedCount, event.detail.totalCount);
});
```

The browser helper is also exposed as `window.wpAppPwa`:

```js
window.wpAppPwa.cacheUrls([
	window.location.href,
	'/my-app/download.pdf'
]);
```

## Runtime Messages

The generated service worker supports the same primitives that more advanced apps can build on:

```js
registration.active.postMessage({
	type: 'wp-app-cache-url',
	urls: [window.location.href, '/my-app/attachment.pdf'],
	requiredUrls: [window.location.href]
});
```

It responds with `wp-app-cache-status` and reports whether the requested URLs are cached. It also listens for the configured Background Sync tag and posts `sync_message_type` to open clients. App-specific JavaScript can use those messages to drive IndexedDB mutation queues, attachment indicators, or offline status panels.

## Author Responsibilities

WpApp supplies the WordPress integration, but the app still needs an offline-aware data model. Plugin authors should decide which content can be cached, what can be edited offline, and how conflicts are resolved when the browser comes back online.

For read-mostly apps, precaching the shell and relying on same-app runtime caching may be enough. For apps that create or edit records offline, add an app script that queues writes in IndexedDB and replays them to WordPress REST or AJAX endpoints when connectivity returns.
