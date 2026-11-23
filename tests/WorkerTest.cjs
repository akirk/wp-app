const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
function worker() {
    const handlers = {}, stored = new Map();
    let response = new Response('shell');
    const config = {scopeUrl: 'https://example.org/app/', cachePolicy: 'shell', cacheName: 'test-v1', cachePrefix: 'test-',
        precache: ['https://example.org/app/capture/', 'https://example.org/assets/app.js?v=1'],
        offlineUrl: 'https://example.org/app/capture/', cacheMessageType: 'cache', cacheStatusMessageType: 'status'};
    const cache = {put: async (key, value) => stored.set(typeof key === 'string' ? key : key.url, value),
        match: async key => stored.get(typeof key === 'string' ? key : key.url)};
    const self = {wpAppWorker: config, addEventListener: (type, fn) => handlers[type] = fn, skipWaiting: async () => {}};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/offline/worker.js'), 'utf8'), {
        self, URL, Response, location: {origin: 'https://example.org'},
        caches: {open: async () => cache}, fetch: async () => response.clone()
    });
    return {stored, handlers, response(value) { response = value; }};
}
test('shell worker caches explicit assets and excludes private navigation responses', async () => {
    const w = worker();
    let done;
    w.handlers.install({waitUntil: promise => done = promise}); await done;
    w.handlers.fetch({request: {method: 'GET', url: 'https://example.org/app/private', mode: 'navigate'}, respondWith: promise => done = promise}); await done;
    assert.deepEqual([...w.stored.keys()], ['https://example.org/app/capture/', 'https://example.org/assets/app.js?v=1']);
});
test('redirected login response prevents shell installation', async () => {
    const w = worker();
    w.response({clone: () => ({ok: true, redirected: true})});
    let done; w.handlers.install({waitUntil: promise => done = promise});
    await assert.rejects(done, /redirected/);
    assert.equal(w.stored.size, 0);
});
test('cache readiness rejects required URLs outside the shell allowlist', () => {
    const w = worker(); let status;
    w.handlers.message({data: {type: 'cache', urls: ['https://example.org/app/private'], requiredUrls: ['https://example.org/app/private']}, source: {postMessage: message => status = message}});
    assert.equal(status.ok, false);
});

// Run the PHP-generated response, including real extension bundling, in a worker context.
test('generated worker keeps caching and app push/message listeners in one registration', async () => {
    const {spawnSync} = require('node:child_process');
    const os = require('node:os');
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-app-worker-'));
    try {
        fs.writeFileSync(path.join(dir, 'push.js'), `
            const cacheName = 'extension-local';
            self.addEventListener('push', function (event) {
                event.waitUntil(self.registration.showNotification(self.wpAppWorker.appData.title));
            });
            self.addEventListener('message', function (event) {
                if (event.data.type === 'app-ping') event.source.postMessage({type: cacheName});
            });
        `);
        fs.writeFileSync(path.join(dir, 'click.js'), `
            const cacheName = 'another-extension-local';
            self.addEventListener('notificationclick', function (event) {
                event.notification.close();
                event.waitUntil(self.clients.openWindow(self.wpAppWorker.appData.url));
            });
        `);
        const generated = spawnSync('php', ['-r', String.raw`
            require $argv[1] . '/tests/bootstrap.php';
            \WpApp\Pwa::register('app', [
                'cache_policy' => 'shell', 'offline_url' => 'https://example.org/app/offline/',
                'precache' => ['https://example.org/assets/app.js?v=1'],
                'worker_scripts' => [$argv[2] . '/push.js', $argv[2] . '/click.js'],
                'worker_data' => ['title' => 'My app', 'url' => 'https://example.org/app/']
            ]);
            \WpApp\Pwa::maybe_handle_app_request('app', 'wp-app-service-worker');
        `, path.resolve(__dirname, '..'), dir], {encoding: 'utf8'});
        assert.equal(generated.status, 0, generated.stderr);
        const listeners = {}, stored = new Map(), notifications = [], opened = [];
        const cache = {put: async (url, response) => stored.set(url, response), match: async url => stored.get(url)};
        const self = {
            addEventListener(type, fn) { (listeners[type] ||= []).push(fn); },
            registration: {showNotification: async title => notifications.push(title)},
            clients: {openWindow: async url => opened.push(url)}, skipWaiting: async () => {}
        };
        vm.runInNewContext(generated.stdout, {
            self, URL, Response, location: {origin: 'https://example.org'},
            caches: {open: async () => cache}, fetch: async () => new Response('shell')
        });
        async function dispatch(type, event = {}) {
            const pending = [];
            for (const listener of listeners[type]) listener({...event, waitUntil: promise => pending.push(promise)});
            await Promise.all(pending);
        }
        await dispatch('install');
        assert.equal(stored.size, 2);
        await dispatch('push');
        assert.deepEqual(notifications, ['My app']);
        let closed = false;
        await dispatch('notificationclick', {notification: {close() {closed = true;}}});
        assert.equal(closed, true);
        assert.deepEqual(opened, ['https://example.org/app/']);
        const messages = [];
        const source = {postMessage: message => messages.push(message)};
        await dispatch('message', {data: {type: 'app-ping'}, source});
        await dispatch('message', {data: {type: 'wp-app-version'}, source});
        assert.deepEqual(messages.map(message => message.type), ['extension-local', 'wp-app-version']);
        let result;
        await dispatch('fetch', {request: {method: 'GET', url: 'https://example.org/app/private', mode: 'navigate'}, respondWith: promise => result = promise});
        assert.equal(await (await result).text(), 'shell');
        assert.equal(stored.size, 2, 'Private navigation was not cached by an extension');
    } finally { fs.rmSync(dir, {recursive: true, force: true}); }
});
