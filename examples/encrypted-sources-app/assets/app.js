(function () {
	'use strict';

	var config = window.EncryptedSourcesConfig || {};
	var runtime = null;
	var settings = null;
	var sourcesNode = document.querySelector('[data-sources]');
	var form = document.querySelector('[data-source-form]');
	var unlockButton = document.querySelector('[data-unlock]');

	function request(path, options) {
		options = options || {};
		options.headers = Object.assign(
			{
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			options.headers || {}
		);

		return fetch(config.restUrl + path, options).then(function (response) {
			if (!response.ok) {
				throw new Error('Request failed with HTTP ' + response.status);
			}
			return response.json();
		});
	}

	function aad(field) {
		return {
			app: 'encrypted-sources',
			userId: config.userId,
			field: field
		};
	}

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, function (character) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[character];
		});
	}

	async function unlock() {
		if (!settings) {
			settings = await request('/settings');
		}

		runtime = window.WpAppCrypto.createRuntime({
			salt: settings.salt,
			iterations: settings.iterations,
			prompt: 'Enter the encryption password for your protected sources.'
		});

		await runtime.unlock();
		document.querySelectorAll('[data-locked]').forEach(function (node) {
			node.removeAttribute('data-locked');
		});
		await loadSources();
	}

	async function encryptFields(fields) {
		var encrypted = {};
		var fieldNames = Object.keys(fields);

		for (var i = 0; i < fieldNames.length; i++) {
			var field = fieldNames[i];
			encrypted[field] = await runtime.encrypt(fields[field], {
				type: field,
				aad: aad(field),
				minBytes: field === 'notes' ? 1024 : 512,
				bucketBytes: field === 'notes' ? 1024 : 512
			});
		}

		return encrypted;
	}

	async function decryptField(source, field) {
		if (!source.encrypted || !source.encrypted[field]) {
			return '';
		}

		return runtime.decrypt(source.encrypted[field], { aad: aad(field) });
	}

	async function renderSource(source) {
		var name = await decryptField(source, 'name');
		var contact = await decryptField(source, 'contact');
		var notes = await decryptField(source, 'notes');
		var privateTags = await decryptField(source, 'private_tags');

		return [
			'<article class="source-item">',
			'<div class="source-item__header">',
			'<h3>' + escapeHtml(name || 'Unnamed source') + '</h3>',
			'<span>' + escapeHtml((source.risk || []).join(', ') || 'unclassified') + '</span>',
			'</div>',
			'<p class="source-contact">' + escapeHtml(contact) + '</p>',
			'<p>' + escapeHtml(notes) + '</p>',
			'<p class="source-tags">' + escapeHtml(privateTags) + '</p>',
			'</article>'
		].join('');
	}

	async function loadSources() {
		var response = await request('/sources');
		var html = [];

		for (var i = 0; i < response.sources.length; i++) {
			html.push(await renderSource(response.sources[i]));
		}

		sourcesNode.innerHTML = html.join('') || '<p>No sources saved yet.</p>';
	}

	if (unlockButton) {
		unlockButton.addEventListener('click', function () {
			unlock().catch(function (error) {
				window.alert(error.message);
			});
		});
	}

	if (form) {
		form.addEventListener('submit', async function (event) {
			event.preventDefault();

			if (!runtime || !runtime.getSession()) {
				await unlock();
			}

			var data = new FormData(form);
			var encrypted = await encryptFields({
				name: data.get('name') || '',
				contact: data.get('contact') || '',
				notes: data.get('notes') || '',
				private_tags: data.get('private_tags') || ''
			});

			await request('/sources', {
				method: 'POST',
				body: JSON.stringify({
					risk: data.get('risk'),
					workflow: data.get('workflow'),
					encrypted: encrypted
				})
			});

			form.reset();
			await loadSources();
		});
	}
})();
