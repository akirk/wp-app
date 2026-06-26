(function () {
	'use strict';

	var encrypted = null;
	var sourcesNode = document.querySelector('[data-sources]');
	var form = document.querySelector('[data-source-form]');
	var unlockButton = document.querySelector('[data-unlock]');

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

	function getClient() {
		if (!encrypted) {
			encrypted = window.WpAppEncryptedFields.fromGlobal();
		}

		return encrypted;
	}

	async function unlock() {
		await getClient().unlock();
		document.querySelectorAll('[data-locked]').forEach(function (node) {
			node.removeAttribute('data-locked');
		});
		await loadSources();
	}

	function renderSource(source) {
		return [
			'<article class="source-item">',
			'<div class="source-item__header">',
			'<h3>' + escapeHtml(source.post_title || 'Unnamed source') + '</h3>',
			'<span>' + escapeHtml(source.source_risk || 'unclassified') + '</span>',
			'</div>',
			'<p class="source-contact">' + escapeHtml(source.contact) + '</p>',
			'<p>' + escapeHtml(source.notes) + '</p>',
			'<p class="source-tags">' + escapeHtml(source.private_tags) + '</p>',
			'</article>'
		].join('');
	}

	async function loadSources() {
		var records = await getClient().cpt('journalist_source').all().decrypt();
		sourcesNode.innerHTML = records.toArray().map(renderSource).join('') || '<p>No sources saved yet.</p>';
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

			var data = new FormData(form);
			await getClient().cpt('journalist_source').save({
				post_title: data.get('post_title') || '',
				contact: data.get('contact') || '',
				notes: data.get('notes') || '',
				private_tags: data.get('private_tags') || '',
				source_risk: data.get('source_risk'),
				source_workflow: data.get('source_workflow')
			});

			form.reset();
			await loadSources();
		});
	}
})();
