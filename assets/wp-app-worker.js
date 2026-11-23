(function(config) {
const cacheName = config.cacheName;
const cachePrefix = config.cachePrefix;
const precache = config.precache || [];
const cacheablePaths = config.cacheablePaths || [];
const cacheableSearchParams = config.cacheableSearchParams || [];

function uniqueUrls(items) {
	const seen = {};
	return items.filter(function(url) {
		if (seen[url.href]) {
			return false;
		}
		seen[url.href] = true;
		return true;
	});
}

function isCacheableRequest(url) {
	if (config.cachePolicy === "shell") {
		return precache.some(function(item) { return new URL(item, location.origin).href === url.href; });
	}
	if (url.origin !== location.origin) {
		return false;
	}

	if (cacheablePaths.some(function(path) {
		return path && url.pathname.indexOf(path) === 0;
	})) {
		return true;
	}

	return cacheableSearchParams.some(function(param) {
		return param && url.search.indexOf(param) !== -1;
	});
}

function sendMessage(source, message) {
	if (source && source.postMessage) {
		source.postMessage(message);
	}
}

self.addEventListener("install", function(event) {
	event.waitUntil(
		caches.open(cacheName).then(function(cache) {
			return Promise.all(precache.map(async function(url) {
				const response = await fetch(url, {credentials: "same-origin", cache: "no-store"});
				if (!response.ok || response.redirected) throw new Error("Precache response unavailable or redirected");
				await cache.put(url, response);
			}));
		}).then(function() {
			return self.skipWaiting();
		})
	);
});

self.addEventListener("activate", function(event) {
	event.waitUntil(
		caches.keys().then(function(keys) {
			return Promise.all(keys.map(function(key) {
				if (key !== cacheName && key.indexOf(cachePrefix) === 0) {
					return caches.delete(key);
				}
				return Promise.resolve();
			}));
		}).then(function() {
			return self.clients.claim();
		})
	);
});

self.addEventListener("fetch", function(event) {
	const request = event.request;
	const requestUrl = new URL(request.url);

	if (request.method !== "GET" || (!isCacheableRequest(requestUrl) && !(config.cachePolicy === "shell" && request.mode === "navigate"))) {
		return;
	}

	event.respondWith(
		fetch(request).then(async function(response) {
			const copy = response.clone();
			if (isCacheableRequest(requestUrl) && response.ok && !response.redirected) {
				const cache = await caches.open(cacheName);
				await cache.put(request, copy);
			}
			return response;
		}).catch(function() {
			return caches.open(cacheName).then(async function(cache) {
				if (config.cachePolicy === "shell") {
					return (await cache.match(request)) || (request.mode === "navigate" ? await cache.match(config.offlineUrl) : null) || Response.error();
				}
				return caches.match(request);
			}).then(function(response) {
				if (response) {
					return response;
				}

				if (request.mode === "navigate") {
					return caches.match(requestUrl.href).then(function(urlResponse) {
						if (urlResponse) {
							return urlResponse;
						}

						return caches.match(config.offlineUrl).then(function(offlineResponse) {
							return offlineResponse || caches.match(config.scopeUrl);
						});
					});
				}

				return caches.match(config.offlineUrl).then(function(offlineResponse) {
					return offlineResponse || caches.match(config.scopeUrl);
				});
			});
		})
	);
});

self.addEventListener("message", function(event) {
	if (!event.data) {
		return;
	}

	if (event.data.type === config.versionMessageType) {
		sendMessage(event.source, {
			type: config.versionMessageType,
			version: cacheName
		});
		return;
	}

	if (event.data.type !== config.cacheMessageType) {
		return;
	}

	let urls = Array.isArray(event.data.urls) ? event.data.urls : [event.data.url];
	let requiredUrls = Array.isArray(event.data.requiredUrls) ? event.data.requiredUrls : urls;
	const requiredUrlsAllowed = requiredUrls.filter(Boolean).every(function(url) { return isCacheableRequest(new URL(url, location.origin)); });

	urls = uniqueUrls(urls.filter(Boolean).map(function(url) {
		return new URL(url, location.origin);
	}).filter(isCacheableRequest));
	requiredUrls = uniqueUrls(requiredUrls.filter(Boolean).map(function(url) {
		return new URL(url, location.origin);
	}).filter(isCacheableRequest));

	function sendCacheStatus(ok, cachedCount, totalCount, cachedUrls) {
		sendMessage(event.source, {
			type: config.cacheStatusMessageType,
			ok: !!ok,
			cachedCount: cachedCount || 0,
			totalCount: totalCount || 0,
			cachedUrls: cachedUrls || []
		});
	}

	if (!requiredUrls.length || !requiredUrlsAllowed) {
		sendCacheStatus(false);
		return;
	}

	event.waitUntil(caches.open(cacheName).then(function(cache) {
		return Promise.all(urls.map(function(url) {
			return fetch(url.href, {
				credentials: "same-origin",
				cache: "reload"
			}).then(function(response) {
				if (response.ok && !response.redirected) {
					return cache.put(url.href, response);
				}
			}).catch(function() {});
		})).then(function() {
			const cachedUrlResults = Promise.all(urls.map(function(url) {
				return cache.match(url.href).then(function(cached) {
					return {
						href: url.href,
						cached: !!cached
					};
				});
			}));
			const cachedUrlStatus = cachedUrlResults.then(function(results) {
				const cachedUrls = [];
				const cachedCount = results.reduce(function(total, result) {
					if (result.cached) {
						cachedUrls.push(result.href);
						return total + 1;
					}
					return total;
				}, 0);
				return {
					count: cachedCount,
					urls: cachedUrls
				};
			});
			const requiredReady = Promise.all(requiredUrls.map(function(url) {
				return cache.match(url.href).then(function(cached) {
					if (cached) {
						return;
					}
					return Promise.reject(new Error("Not cached"));
				});
			}));
			return Promise.all([cachedUrlStatus, requiredReady]).then(function(results) {
				sendCacheStatus(true, results[0].count, urls.length, results[0].urls);
			}, function() {
				return cachedUrlStatus.then(function(status) {
					sendCacheStatus(false, status.count, urls.length, status.urls);
				}, function() {
					sendCacheStatus(false, 0, urls.length, []);
				});
			});
		});
	}).catch(function() {
		sendCacheStatus(false, 0, urls.length, []);
	}));
});

self.addEventListener("sync", function(event) {
	if (event.tag === config.syncTag && self.clients) {
		event.waitUntil(self.clients.matchAll().then(function(clients) {
			clients.forEach(function(client) {
				client.postMessage({ type: config.syncMessageType });
			});
		}));
	}
});
})(self.wpAppWorker);
