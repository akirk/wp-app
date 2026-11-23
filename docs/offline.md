# Offline shells and outboxes

PWA support can cache a minimal offline shell without caching private app pages:

```php
$app->enable_pwa( [
    'cache_policy' => 'shell',
    'outbox' => true, // Optional durable operation queue.
    'offline_url' => home_url( '/my-app/capture/' ),
    'precache' => [ $capture_css_url, $capture_js_url ],
    'cache_prefix' => 'my-app-shell-',
    'cache_name' => 'my-app-shell-' . $asset_version,
] );
```

The shell policy automatically includes `offline_url`, but excludes the app root unless explicitly listed. Only exact URLs in that list can enter the cache; include version query strings exactly as enqueued. Failed navigation falls back to the shell. Failed assets do not receive an HTML fallback. Successful redirected responses are never cached, including during installation. The default `scope` policy retains broad app-scope caching for existing apps.

The shell must omit private content and durable credentials. A user identifier in the shell identifies local storage; it does not authorize replay. Declare the app’s own scripts and styles needed offline. wp-app automatically loads and precaches the runtime assets for enabled `outbox` and `visited_pages` features, resolving their URLs from the selected framework provider. Change `cache_name` when shell markup or assets change. Cache status and sync messages use the existing PWA configuration keys. Background Sync wakes open clients; it does not guarantee delivery after an app is closed.

## Durable outbox

Enable `'outbox' => true` in the app's PWA configuration, then construct one outbox per logical queue. wp-app loads its runtime through `wp_app_head()` before app scripts. Templates, including minimal fallback shells, should use `wp_app_head()` and `wp_app_body_close()`; apps do not enqueue framework assets or declare their filenames:

```js
const outbox = new WpAppOutbox({
    scope: appUrl,
    userId: currentUserId,
    ajaxUrl: ajaxUrl,
    sessionAction: 'my_app_session',
    syncAction: 'my_app_sync',
    payloadField: 'operation',
    acknowledge: (receipt, entry) => receipt.id === entry.id && !!receipt.savedId
});
await outbox.enqueue({
    id: crypto.randomUUID(),
    createdAt: new Date().toISOString(),
    userId: currentUserId,
    kind: 'create-task',
    text: 'Example task'
});
await outbox.sync();
```

`sessionAction` is a read-only, cookie-authenticated endpoint returning a fresh nonce and the authenticated `userId`. `syncAction` receives that nonce and the serialized operation. Both return WordPress JSON success/error envelopes. The client verifies the session account before sending queued content. Alternatively, supply async `session()` and `send(entry, session)` callbacks for other transports. Those callbacks return unwrapped session and receipt data.

The app owns payload validation, capabilities, persistence, and durable idempotency on the server. UUIDs and local Web Locks alone cannot prevent duplicate writes after a lost response. The server must recover acknowledgment after interrupted writes; retries reuse the same operation ID. Independent operations continue after an individual failure; applications requiring dependencies must handle those in their transport.

The default IndexedDB name includes the app scope and user ID. Use `databaseName` only to preserve a compatible existing database or separate multiple queues. The outbox stores are `entries`, `drafts`, and `meta`, keyed by `id`; the shared storage runtime also provides `records` for named local collections. `enqueue(entry, draftId, clearDraft)` optionally deletes a draft in the same transaction as queuing the operation; the optional synchronous predicate prevents clearing a draft edited during submission. It does not clear form inputs.

`rows(store)` reads records; `transaction(stores, mode, work)` supports draft persistence and restoration. `subscribe(listener)` returns an unsubscribe function. `status()` reports `{running, error}`. `changed()` notifies subscribers and other tabs after custom transactions. Last acknowledgment is stored in `meta` as `lastSync` with `value` and optional `url`. Replay uses creation order, validates acknowledgments before deleting entries, and retries on reconnect, focus, visibility, and a five-second visible-tab interval (`retryInterval` is configurable). Call `destroy()` to remove lifecycle listeners and close its broadcast channel when disposing an instance.

Local storage can fail or be cleared by the browser or user. Keep text visible until enqueue resolves; display local persistence and server acknowledgment as separate states. Apps own wording, form binding, and status UI.

Validation:

```sh
vendor/bin/phpunit --do-not-cache-result
node --test tests/OutboxTest.cjs tests/LocalDataTest.cjs tests/WorkerTest.cjs
```

Browser tests require Playwright and Chromium.

## Visited pages in the regular UI

Apps can opt into caching authenticated HTML snapshots alongside the minimal fallback:

```php
$app->enable_pwa( [
    'cache_policy' => 'shell',
    'offline_url' => home_url( '/my-app/capture/' ),
    'visited_pages' => true,
    'cache_identity' => (string) get_current_user_id(),
] );
```

This requires a non-empty account identity and adds its hash to the cache name. The worker activates one current cache per app prefix. Every cacheable HTML response must explicitly return `X-Wp-App-Offline` with that exact identity; do not add it to login screens, redirects, or action endpoints. Opt in only for safe page views. Apps should verify `WpApp\Pwa::OFFLINE_PAGES_SUPPORTED` before registering a worker when an older framework provider may be selected.

Enabling `visited_pages` automatically loads and precaches the page inventory runtime. After the worker becomes active, call:

```js
await wpAppOfflinePages.remember(registration, userId);
const pages = await wpAppOfflinePages.list(registration, userId);
// [{url, title, cachedAt}, ...]
```

`remember` fetches the current URL and its declared script/stylesheet assets, rejecting redirects, unsuccessful downloads, cross-origin assets, and account-mismatched HTML. It stores page metadata only after the page and assets have been cached. An unsuccessful refresh keeps the last complete snapshot. `list` reads the active cache and confirms that each page and its required scripts/styles still exist; it does not infer availability from browser history or fetch unvisited links. Both methods reject if the worker identity differs. Use a list-only request offline, on panel expansion, or when refreshing an availability indicator.

A listed page can be reopened at its original URL offline. Other navigation uses the configured fallback. Lists are snapshots of the cache at query time; storage can subsequently be cleared or evicted. This covers HTML and the document's declared scripts/styles. Images, attachments, fonts, dynamically imported resources, and server-side features require additional app-specific caching. Private snapshots remain local browser data, not an authentication mechanism or encrypted storage. Apps own logout cleanup and the visible explanation of offline limitations. Cached editors do not automatically gain offline saving: queue changes and resolve server conflicts separately, or prevent offline form submission so edits stay visible.

Runtime asset versions and cache invalidation are owned by wp-app. Updating the selected framework runtime changes its asset URLs and the cache version for apps using these features; app code only versions its own assets.

## Client-rendered applications

Apps can share wp-app's document, bootstrap, and local storage without adopting the same UI or persistence rules:

```php
$app->enable_pwa( [
    'cache_policy' => 'shell',
    'outbox' => true,
    'local_data' => true,
    'offline_shell' => [
        'content' => __DIR__ . '/templates/shell-content.php',
        'body_class' => 'wp-app-body my-app',
        'capability' => 'edit_posts',
        'bootstrap_url' => admin_url( 'admin-ajax.php?action=my_app_bootstrap' ),
        'offline_data' => [
            'userId' => get_current_user_id(),
            'scope' => home_url( '/my-app/' ),
        ],
    ],
] );
```

wp-app derives an `/offline` endpoint unless `offline_url` names another dedicated path inside the app scope. It owns fallback dispatch, the document, runtime asset registration, account cache identity, and worker startup. Normal app routes can render the same content with `WpApp\Pwa::render_shell( 'my-app' )`. They use normal WordPress head/body hooks for live integrations. The cached fallback uses isolated scoped asset hooks, omits global live head/footer injection, and defaults to `script-src 'self'; object-src 'none'; base-uri 'self'`. A trusted app can configure `offline_shell.csp` when it needs another policy. Content templates must provide static mount points rather than private records or request credentials. Continue declaring the app's own offline assets in `precache`.

`window.wpAppBootstrap` resolves to `{data, offline}`. The bootstrap endpoint returns a plain JSON object with a `userId` matching the safe offline data. Live configuration can include fresh credentials: it stays in memory and the bootstrap response is never automatically cached. Network failures and server outages use only the explicitly supplied offline data. Authentication failures, redirects, and account changes reject instead of opening another account's records. App scripts must handle rejection visibly. `wp-app:offline-status` reports worker setup readiness separately from local data availability, after checking the active worker version and all required cached assets. Online startup refreshes only the safe fallback HTML so offline preferences remain current; it does not cache the live bootstrap response.

```js
const boot = await wpAppBootstrap;
const store = new WpAppStorage({scope: boot.data.scope, userId: boot.data.userId});
const local = new WpAppLocalData(store);
await local.put('notes', {id: '123', title: 'Example', content: 'Text'});
const note = await local.get('notes', '123');
const notes = await local.all('notes');
```

Enable `local_data` without `outbox` when adopting local storage independently. Both features share the generic storage runtime, but neither requires an app to replace its own queue. Collections support `put`, `get`, `all`, `remove`, and atomic `replace`. Values use IndexedDB structured cloning, including Blobs. `WpAppLocalData` accepts a storage configuration directly or an adapter exposing `rows` and `transaction`; an outbox also exposes that adapter, allowing records and operations to share a database. Version-one wp-app outbox databases upgrade without deleting drafts or pending operations. Preserve an existing `databaseName` explicitly when migrating a compatible schema; migration of unrelated app schemas remains app-owned.

For optimistic edits, include the app's baseline version and a stable operation ID in each payload. The server must compare that baseline and persist its receipt atomically with the write. The optional async `onAcknowledged(receipt, entry)` hook lets an app persist canonical local records before the outbox removes the operation. A failed acknowledgment hook keeps the operation queued for retry. A transport error with `status: 409` and `details.code: 'conflict'` persists the operation as `state: 'conflict'` with the details and stops automatic replay of that operation. Apps present the versions and explicitly replace or discard conflicted work after review. Independent operations still replay.

Daymark can adopt the shell and bootstrap first while keeping its push worker (`register_service_worker: false`), bookmark store, uploads, and publishing queue. A small entry adapter can await `wpAppBootstrap` before starting its existing application. Later it can adopt shared storage or the outbox separately. The framework does not assume that a bookmark, a note edit, and a file upload have the same payload or conflict rules.


## Runtime organization

The JavaScript runtime lives under `assets/offline/`:

- `data.js` contains scoped IndexedDB storage and named collections. Both share the same database lifecycle and are available without a queue.
- `outbox.js` handles durable operations, transport, acknowledgment, and conflict states. It loads only when `outbox` is enabled.
- `client.js` coordinates bootstrap, worker readiness, and visited-page inventory in the browser window. Without a bootstrap mount it does not fetch app configuration.
- `worker.js` runs in the separate service-worker context and owns caching and navigation fallback.

Storage and collections share one module; bootstrap and browser-side worker messaging share another. Public JavaScript APIs remain available. Apps continue to enable features declaratively rather than enqueueing these files.

`outbox.enqueue(entry, draftId, clearDraft, persist)` accepts an optional synchronous persistence callback. Its transaction includes local records, so `persist(tx)` can call `local.write(tx, collection, value)` to commit an optimistic record with its queued operation. Throwing aborts all writes. Use the same storage adapter for the local data and outbox.

## App-specific service worker handlers

Add push notifications or other app events to the framework worker instead of registering a second worker:

```php
$app->enable_pwa( [
    'cache_policy' => 'shell',
    'offline_url' => home_url( '/my-app/offline/' ),
    'worker_scripts' => [ __DIR__ . '/assets/push-worker.js' ],
    'worker_data' => [ 'restUrl' => rest_url( 'my-app/v1/' ) ],
] );
```

`worker_scripts` is an ordered list of readable local filesystem paths, supplied by trusted app code. wp-app reads and bundles those files into its generated worker response after the framework runtime. They do not need public URLs, separate precache entries, or `importScripts()`. Each file executes synchronously inside its own function scope, with `self` as `this`. Use classic JavaScript files rather than module imports or exports. A syntax or startup error prevents the new worker from installing; the browser keeps the previous active worker.

Inside an extension:

```js
self.addEventListener('push', function (event) {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(self.registration.showNotification(data.title || 'My app', {
        body: data.body || '',
        data: {restUrl: self.wpAppWorker.appData.restUrl}
    }));
});
```

The framework retains registration, install/activate caching, navigation fallback, shell refresh, and readiness checks. Extensions register standard service worker event listeners such as `push`, `notificationclick`, and `pushsubscriptionchange`, using `event.waitUntil()` for asynchronous work. They share the same registration and scope; do not register a second worker, replace framework configuration, or independently call `skipWaiting()` or `clients.claim()`. Avoid overlapping fetch handlers: only one handler can call `respondWith()` for a request. Reserve `wp-app-` message names for the framework.

`worker_data` must be a JSON-serializable PHP array. Its values are exposed as `self.wpAppWorker.appData` and can be inspected in the worker response, so supply public configuration rather than credentials or private records. App-owned caches should use a separate prefix from the framework's `cache_prefix`, so framework activation leaves them intact. Apps remain responsible for those caches' lifecycle and logout cleanup.

Extension contents and data automatically contribute to the cache version; editing a file changes both worker bytes and the cache name even when its path and the app version stay the same. The framework runtime version also contributes when extensions are enabled. A framework or extension update therefore rebuilds the offline cache while preserving unrelated app caches.

Daymark can use this API to move push handling into an extension of the framework worker. Adopting that worker still requires declaring its exact offline asset URLs in `precache` and handling the transition from its previous cache/message protocol; storage and publishing queues can remain unchanged.
