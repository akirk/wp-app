# Offline shells and outboxes

PWA support can cache a minimal offline shell without caching private app pages:

```php
$app->enable_pwa( [
    'cache_policy' => 'shell',
    'offline_url' => home_url( '/my-app/capture/' ),
    'precache' => [ $capture_css_url, $capture_js_url ],
    'cache_prefix' => 'my-app-shell-',
    'cache_name' => 'my-app-shell-' . $asset_version,
] );
```

The shell policy automatically includes `offline_url`, but excludes the app root unless explicitly listed. Only exact URLs in that list can enter the cache; include version query strings exactly as enqueued. Failed navigation falls back to the shell. Failed assets do not receive an HTML fallback. Successful redirected responses are never cached, including during installation. The default `scope` policy retains broad app-scope caching for existing apps.

The shell must omit private content and durable credentials. A user identifier in the shell identifies local storage; it does not authorize replay. Declare every script and stylesheet needed offline, including `wp-app-outbox.js` when using the outbox. Change `cache_name` when shell markup or assets change. Cache status and sync messages use the existing PWA configuration keys. Background Sync wakes open clients; it does not guarantee delivery after an app is closed.

## Durable outbox

Enqueue `wp_app_get_asset_url( 'wp-app-outbox.js' )` using `wp_app_enqueue_script()` with an explicit app scope, then construct one outbox per logical queue:

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

The default IndexedDB name includes the app scope and user ID. Use `databaseName` only to preserve a compatible existing database or separate multiple queues. Stores are `entries`, `drafts`, and `meta`, keyed by `id`. `enqueue(entry, draftId, clearDraft)` optionally deletes a draft in the same transaction as queuing the operation; the optional synchronous predicate prevents clearing a draft edited during submission. It does not clear form inputs.

`rows(store)` reads records; `transaction(stores, mode, work)` supports draft persistence and restoration. `subscribe(listener)` returns an unsubscribe function. `status()` reports `{running, error}`. `changed()` notifies subscribers and other tabs after custom transactions. Last acknowledgment is stored in `meta` as `lastSync` with `value` and optional `url`. Replay uses creation order, validates acknowledgments before deleting entries, and retries on reconnect, focus, visibility, and a five-second visible-tab interval (`retryInterval` is configurable). Call `destroy()` to remove lifecycle listeners and close its broadcast channel when disposing an instance.

Local storage can fail or be cleared by the browser or user. Keep text visible until enqueue resolves; display local persistence and server acknowledgment as separate states. Apps own wording, form binding, and status UI.

Validation:

```sh
vendor/bin/phpunit --do-not-cache-result
node --test tests/OutboxTest.cjs tests/WorkerTest.cjs
```

Browser tests require Playwright and Chromium.
