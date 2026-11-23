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
