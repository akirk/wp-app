/* Durable app/account-scoped storage and acknowledged operation replay. */
(function () {
    'use strict';
    window.WpAppOutbox = function (config) {
        if (!config.scope || !config.userId || !config.acknowledge) throw new Error('Outbox requires scope, userId and acknowledge.');
        var dbPromise, channel;
        var running = false, lastError = '';
        var dbName = config.databaseName || ('wp-app-outbox:' + config.scope + ':' + config.userId);
        var listeners = [];
        function changed() { listeners.forEach(function (listener) { listener(); }); if (channel) channel.postMessage('changed'); }
        function openDB() {
            if (!dbPromise) dbPromise = new Promise(function (resolve, reject) {
                if (!window.indexedDB) return reject(new Error('Local storage unavailable. Keep your text here and save online.'));
                var request = indexedDB.open(dbName, 1);
                request.onupgradeneeded = function () {
                    ['entries', 'drafts', 'meta'].forEach(function (name) { request.result.createObjectStore(name, {keyPath: 'id'}); });
                };
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
                request.onblocked = function () { reject(new Error('Local storage is blocked. Close other app tabs and retry.')); };
            });
            return dbPromise;
        }
        function transaction(stores, mode, work) {
            return openDB().then(function (db) {
                return new Promise(function (resolve, reject) {
                    var tx = db.transaction(stores, mode);
                    var value;
                    tx.oncomplete = function () { resolve(value); };
                    tx.onerror = tx.onabort = function () { reject(tx.error || new Error('Local save failed. Your text has not been cleared.')); };
                    try { work(tx, function (result) { value = result; }); }
                catch (error) { tx.abort(); reject(error); }
                });
            });
        }
        function rows(store) {
            return transaction([store], 'readonly', function (tx, done) {
                tx.objectStore(store).getAll().onsuccess = function (event) { done(event.target.result); };
            });
        }
        async function request(data) {
            var controller = new AbortController();
            var timer = setTimeout(function () { controller.abort(); }, 20000);
            try {
                var response = await fetch(config.ajaxUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                    body: new URLSearchParams(data), signal: controller.signal
                });
                var result;
                try { result = await response.json(); } catch (_) { throw new Error('Could not verify saving. Entry kept for retry; log in if your session expired.'); }
                if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Could not sync. Your entry remains on this device.');
                return result.data;
            } finally { clearTimeout(timer); }
        }
        async function replay() {
            var entries = await rows('entries');
            if (!entries.length) return;
            var session = config.session ? await config.session() : await request({action: config.sessionAction});
            if (Number(session.userId) !== Number(config.userId)) throw new Error('Sign in to the account that saved these entries. They have not been sent.');
            var firstError;
            for (var entry of entries.sort(function (a, b) { return a.createdAt.localeCompare(b.createdAt); })) {
                try {
                    var receipt = config.send ? await config.send(entry, session) : await request({action: config.syncAction, nonce: session.nonce, [config.payloadField || 'operation']: JSON.stringify(entry)});
                    if (!config.acknowledge(receipt, entry)) throw new Error('Server did not acknowledge this entry. Kept for retry.');
                } catch (error) {
                    // Keep this operation for retry without blocking independent entries.
                    firstError = firstError || error;
                    continue;
                }
                await transaction(['entries', 'meta'], 'readwrite', function (tx) {
                    tx.objectStore('entries').delete(entry.id);
                    tx.objectStore('meta').put({id: 'lastSync', value: new Date().toISOString(), url: receipt.url});
                });
                changed();
            }
            if (firstError) throw firstError;
        }
        async function sync() {
            if (running || !navigator.onLine) { changed(); return; }
            running = true;
            lastError = '';
            changed();
            try {
                if (navigator.locks) await navigator.locks.request(dbName + ':sync', replay);
                else await replay(); // Server receipts also protect browsers without Web Locks.

            } catch (error) {
                lastError = error.message;

            } finally { running = false; changed(); }
        }

        if (window.BroadcastChannel) { channel = new BroadcastChannel(dbName); channel.onmessage = function () { listeners.forEach(function (listener) { listener(); }); }; }
        window.addEventListener('online', sync);
        window.addEventListener('focus', sync);
        function visible() { if (!document.hidden) sync(); }
        document.addEventListener('visibilitychange', visible);
        var timer = setInterval(function () { if (!document.hidden) sync(); }, config.retryInterval || 5000);
        async function enqueue(entry, draftId, clearDraft) {
            if (!entry.id || !entry.createdAt || Number(entry.userId) !== Number(config.userId)) throw new Error('Operation requires id, createdAt and matching userId.');
            await transaction(['entries', 'drafts'], 'readwrite', function (tx) {
                tx.objectStore('entries').put(entry);
                if (draftId && (!clearDraft || clearDraft())) tx.objectStore('drafts').delete(draftId);
            });
            if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});
            changed();
        }
        return {enqueue: enqueue, rows: rows, transaction: transaction, sync: sync, changed: changed,
            subscribe: function (listener) { listeners.push(listener); return function () { listeners = listeners.filter(function (item) { return item !== listener; }); }; },
            status: function () { return {running: running, error: lastError}; },
            destroy: function () { clearInterval(timer); document.removeEventListener('visibilitychange', visible); listeners = []; window.removeEventListener('online', sync); window.removeEventListener('focus', sync); if (channel) channel.close(); }
        };
    };
})();
