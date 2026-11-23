/* Generic named collections using app/account-scoped durable storage. */
(function () {
    'use strict';
    window.WpAppLocalData = function (storage) {
        if (!storage.transaction) storage = new window.WpAppStorage(storage);
        function key(collection, id) { return JSON.stringify([collection, String(id)]); }
        return {
            close: function () { if (storage.close) return storage.close(); },
            all: async function (collection) { return (await storage.rows('records')).filter(row => row.collection === collection).map(row => row.value); },
            get: async function (collection, id) { var row = (await storage.rows('records')).find(row => row.id === key(collection, id)); return row && row.value; },
            put: function (collection, value) { return storage.transaction(['records'], 'readwrite', function (tx) { tx.objectStore('records').put({id: key(collection, value.id), collection: collection, value: value}); }); },
            remove: function (collection, id) { return storage.transaction(['records'], 'readwrite', function (tx) { tx.objectStore('records').delete(key(collection, id)); }); },
            replace: function (collection, values) {
                return storage.transaction(['records'], 'readwrite', function (tx) {
                    var store = tx.objectStore('records');
                    store.openCursor().onsuccess = function (event) {
                        var cursor = event.target.result;
                        if (cursor) { if (cursor.value.collection === collection) cursor.delete(); cursor.continue(); }
                        else values.forEach(value => store.put({id: key(collection, value.id), collection: collection, value: value}));
                    };
                });
            }
        };
    };
})();
