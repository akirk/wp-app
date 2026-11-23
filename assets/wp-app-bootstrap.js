/* A cacheable shell contains only safe offline configuration; live data stays in memory. */
(function () {
    'use strict';
    async function prepareWorker(settings) {
        if (!settings.worker) return;
        function emit(ready, error) { window.dispatchEvent(new CustomEvent('wp-app:offline-status', {detail: {ready: ready, error: error || ''}})); }
        if (!navigator.serviceWorker || !window.isSecureContext) { emit(false, 'Offline app support requires HTTPS and a compatible browser.'); return; }
        try {
            var worker = settings.worker;
            var registration = await navigator.serviceWorker.getRegistration(worker.scope);
            if (navigator.onLine || !registration) registration = await navigator.serviceWorker.register(worker.url, {scope: worker.scope, updateViaCache: 'none'});
            function check() {
                if (registration.active) registration.active.postMessage({type: worker.cacheMessageType, urls: navigator.onLine ? [worker.offlineUrl] : [], requiredUrls: worker.requiredUrls});
            }
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === worker.cacheStatusMessageType) emit(event.data.ok && event.data.version === worker.cacheName);
            });
            navigator.serviceWorker.addEventListener('controllerchange', check);
            await navigator.serviceWorker.ready;
            check();
        } catch (error) { emit(false, error.message); }
    }
    window.wpAppBootstrap = new Promise(function (resolve, reject) {
        async function boot() {
            var root = document.querySelector('[data-wp-app-bootstrap]');
            if (!root) return resolve(null);
            var settings;
            try {
                settings = JSON.parse(root.getAttribute('data-wp-app-bootstrap'));
                var controller = new AbortController();
                var timer = setTimeout(() => controller.abort(), 8000);
                var response;
                try {
                    response = await fetch(settings.url, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
                } catch (error) {
                    if (!(error instanceof TypeError) && error.name !== 'AbortError') throw error;
                    prepareWorker(settings);
                    resolve({data: settings.offline, offline: true});
                    return;
                } finally { clearTimeout(timer); }
                if (response.status >= 500) { prepareWorker(settings); resolve({data: settings.offline, offline: true}); return; }
                if (!response.ok || response.redirected) throw new Error('Sign in again before opening this app.');
                var data = await response.json();
                if (String(data.userId) !== String(settings.offline.userId)) throw new Error('The signed-in account changed. Reload online before continuing.');
                prepareWorker(settings);
                resolve({data: data, offline: false});
            } catch (error) { reject(error); }
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once: true});
        else boot();
    });
    // Applications attach their own visible error handling after their scripts load.
    window.wpAppBootstrap.catch(function () {});
})();
