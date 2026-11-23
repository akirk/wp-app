/* App UI adapter for confirmed cached page snapshots. */
(function () {
    'use strict';
    function request(registration, data) {
        return new Promise(function (resolve, reject) {
            if (!registration.active) return reject(new Error('Offline worker is not active yet.'));
            var channel = new MessageChannel();
            var timer = setTimeout(function () { channel.port1.close(); reject(new Error('Offline page check timed out.')); }, 20000);
            channel.port1.onmessage = function (event) {
                clearTimeout(timer);
                channel.port1.close();
                if (event.data.error) reject(new Error(event.data.error));
                else resolve(event.data.pages);
            };
            registration.active.postMessage(Object.assign({type: 'wp-app-offline-pages'}, data), [channel.port2]);
        });
    }
    window.wpAppOfflinePages = {
        list: function (registration, identity) { return request(registration, {action: 'list', identity: String(identity)}); },
        remember: function (registration, identity) {
            var assets = Array.from(document.querySelectorAll('script[src], link[rel="stylesheet"][href]')).map(function (node) { return node.src || node.href; });
            return request(registration, {action: 'remember', identity: String(identity), url: location.href.split('#')[0], title: document.title, assets: assets});
        }
    };
})();
