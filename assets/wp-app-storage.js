/* Generic durable storage, scoped to an application and account. */
(function () {
    'use strict';
    window.WpAppStorage = function (config) {
        if (!config.scope || !config.userId) throw new Error('Local storage requires scope and userId.');
        var dbName = config.databaseName || ('wp-app-data:' + config.scope + ':' + config.userId);
        var dbPromise;
        function openDB() {
            if (!dbPromise) dbPromise = new Promise(function (resolve, reject) {
                if (!window.indexedDB) return reject(new Error('Local storage unavailable. Keep your text here and save online.'));
                var request = indexedDB.open(dbName, 2);
                request.onupgradeneeded = function () {
                    ['entries', 'drafts', 'meta', 'records'].forEach(function (name) { if (!request.result.objectStoreNames.contains(name)) request.result.createObjectStore(name, {keyPath: 'id'}); });
                };
                request.onsuccess = function () { request.result.onversionchange = function () { request.result.close(); dbPromise = null; }; resolve(request.result); };
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
        return {rows: rows, transaction: transaction, close: async function () { if (dbPromise) { try { (await dbPromise).close(); } catch (_) {} dbPromise = null; } }};
    };
})();
