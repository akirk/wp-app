(function () {
	'use strict';

	var encrypted = null;
	var sourcesNode = document.querySelector('[data-sources]');
	var sourceTemplate = document.querySelector('[data-source-template]');
	var form = document.querySelector('[data-source-form]');
	var unlockButton = document.querySelector('[data-unlock]');
	var saveButton = document.querySelector('[data-save-source]');
	var cancelEditButton = document.querySelector('[data-cancel-edit]');
	var statusNode = document.querySelector('[data-status]');
	var unlockedMessage = 'Encrypted fields are unlocked in this browser session. New and edited source details are encrypted before they are saved to WordPress. Reloading the page locks the fields again.';
	var sourcesById = {};
	var editingId = 0;

	function getClient() {
		if (!encrypted) {
			encrypted = window.WpAppEncryptedFields.fromGlobal();
		}

		return encrypted;
	}

	function setStatus(message, type) {
		if (!statusNode) {
			return;
		}

		statusNode.textContent = message || '';
		statusNode.hidden = !message;
		statusNode.classList.toggle('is-error', type === 'error');
		statusNode.classList.toggle('is-success', type === 'success');
	}

	function setEditMode(source) {
		editingId = source && source.id ? Number(source.id) : 0;

		if (saveButton) {
			saveButton.textContent = editingId ? 'Update encrypted source' : 'Save encrypted source';
		}

		if (cancelEditButton) {
			cancelEditButton.hidden = !editingId;
		}
	}

	function resetForm() {
		if (form) {
			form.reset();
		}
		setEditMode(null);
	}

	function fillForm(source) {
		if (!form || !source) {
			return;
		}

		form.elements.post_title.value = source.post_title || '';
		form.elements.contact.value = source.contact || '';
		form.elements.notes.value = source.notes || '';
		form.elements.private_tags.value = source.private_tags || '';
		form.elements.source_risk.value = source.source_risk || 'low';
		form.elements.source_workflow.value = source.source_workflow || 'active';
		setEditMode(source);
		form.scrollIntoView({ behavior: 'smooth', block: 'start' });
		form.elements.post_title.focus();
	}

	async function unlock() {
		setStatus('Unlocking encrypted fields...', '');
		await getClient().unlock();
		await loadSources();
		document.querySelectorAll('[data-locked]').forEach(function (node) {
			node.removeAttribute('data-locked');
		});
		if (unlockButton) {
			unlockButton.hidden = true;
		}
		setStatus(unlockedMessage, 'success');
	}

	function renderSource(source) {
		var node = sourceTemplate.content.firstElementChild.cloneNode(true);
		var editButton = node.querySelector('[data-edit-source]');

		node.querySelector('[data-source-field="post_title"]').textContent = source.post_title || 'Unnamed source';
		node.querySelector('[data-source-field="source_risk"]').textContent = source.source_risk || 'unclassified';
		node.querySelector('[data-source-field="contact"]').textContent = source.contact || '';
		node.querySelector('[data-source-field="notes"]').textContent = source.notes || '';
		node.querySelector('[data-source-field="private_tags"]').textContent = source.private_tags || '';

		if (editButton) {
			editButton.setAttribute('data-edit-source', source.id);
		}

		return node;
	}

	async function loadSources() {
		var records = await getClient().cpt('journalist_source').all().decrypt();
		var sourceRecords = records.toArray();
		var fragment = document.createDocumentFragment();
		sourcesById = {};
		sourceRecords.forEach(function (source) {
			sourcesById[source.id] = source;
			fragment.appendChild(renderSource(source));
		});

		sourcesNode.replaceChildren();
		if (sourceRecords.length) {
			sourcesNode.appendChild(fragment);
			return;
		}

		sourcesNode.appendChild(document.createElement('p')).textContent = 'No sources saved yet.';
	}

	if (unlockButton) {
		unlockButton.addEventListener('click', function () {
			unlock().catch(function (error) {
				setStatus(error.message, 'error');
			});
		});
	}

	if (form) {
		form.addEventListener('submit', async function (event) {
			event.preventDefault();

			try {
				var wasEditing = Boolean(editingId);
				setStatus(editingId ? 'Updating encrypted source...' : 'Saving encrypted source...', '');
				var data = new FormData(form);
				await getClient().cpt('journalist_source').save({
					id: editingId,
					post_title: data.get('post_title') || '',
					contact: data.get('contact') || '',
					notes: data.get('notes') || '',
					private_tags: data.get('private_tags') || '',
					source_risk: data.get('source_risk'),
					source_workflow: data.get('source_workflow')
				});

				resetForm();
				await loadSources();
				setStatus((wasEditing ? 'Encrypted source updated. ' : 'Encrypted source saved. ') + unlockedMessage, 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});
	}

	if (cancelEditButton) {
		cancelEditButton.addEventListener('click', function () {
			resetForm();
			setStatus(unlockedMessage, 'success');
		});
	}

	if (sourcesNode) {
		sourcesNode.addEventListener('click', function (event) {
			var button = event.target.closest('[data-edit-source]');
			var source;

			if (!button) {
				return;
			}

			source = sourcesById[button.getAttribute('data-edit-source')];
			if (!source) {
				setStatus('Source could not be found in the decrypted list.', 'error');
				return;
			}

			fillForm(source);
			setStatus('Editing encrypted source. Save to re-encrypt and update it.', 'success');
		});
	}
})();
