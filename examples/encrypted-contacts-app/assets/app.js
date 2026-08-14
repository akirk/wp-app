(function () {
	'use strict';

	var cptName = null;
	var encrypted = null;
	var app = document.querySelector('[data-encrypted-contacts-app]');
	var elements = app ? {
		addButton: app.querySelector('[data-add-contact]'),
		cancelEditButton: app.querySelector('[data-cancel-edit]'),
		composer: app.querySelector('[data-contact-composer]'),
		contacts: app.querySelector('[data-contacts]'),
		contactTemplate: app.querySelector('[data-contact-template]'),
		count: app.querySelector('[data-contact-count]'),
		countLabel: app.querySelector('[data-contact-count-label]'),
		form: app.querySelector('[data-contact-form]'),
		formTitle: app.querySelector('[data-form-title]'),
		importFiles: app.querySelector('[data-import-files]'),
		importZone: app.querySelector('[data-import-dropzone]'),
		saveButton: app.querySelector('[data-save-contact]'),
		status: app.querySelector('[data-status]'),
		unlockButton: app.querySelector('[data-unlock]')
	} : {};
	var contactsById = {};
	var editingId = 0;
	var typeLabels = {};

	if (!app) {
		return;
	}

	function getClient() {
		if (!encrypted) {
			encrypted = window.WpAppEncryptedFields.fromGlobal('encrypted_contacts');
		}

		return encrypted;
	}

	function getCptName() {
		if (!cptName) {
			cptName = getClient().getCptNames()[0];
		}

		return cptName;
	}

	function getEncryptedFieldNames() {
		return Object.keys(getClient().getEncryptedFields(getCptName()));
	}

	function getTaxonomyNames() {
		return getClient().getTaxonomies(getCptName());
	}

	function getRecordFieldNames() {
		return getEncryptedFieldNames().concat(getTaxonomyNames());
	}

	function setStatus(message, type) {
		if (!elements.status) {
			return;
		}

		elements.status.textContent = message || '';
		elements.status.hidden = !message;
		elements.status.classList.toggle('is-error', type === 'error');
		elements.status.classList.toggle('is-success', type === 'success');
	}

	function setContactCount(count, state) {
		if (elements.count) {
			elements.count.textContent = String(count);
		}

		if (elements.countLabel) {
			elements.countLabel.textContent = count === 1 ? state + ' contact' : state + ' contacts';
		}
	}

	function setEditMode(contact) {
		editingId = contact && contact.id ? Number(contact.id) : 0;

		if (elements.formTitle) {
			elements.formTitle.textContent = editingId ? 'Edit contact' : 'New contact';
		}

		if (elements.saveButton) {
			elements.saveButton.textContent = editingId ? 'Update encrypted contact' : 'Save encrypted contact';
		}

		if (elements.cancelEditButton) {
			elements.cancelEditButton.textContent = editingId ? 'Cancel edit' : 'Cancel';
		}
	}

	function resetForm() {
		if (elements.form) {
			elements.form.reset();
		}
		setEditMode(null);
	}

	function closeComposer() {
		if (elements.composer) {
			elements.composer.hidden = true;
		}
		resetForm();
	}

	function openComposer(contact) {
		if (!elements.composer) {
			return;
		}

		elements.composer.hidden = false;
		if (contact) {
			fillForm(contact);
			return;
		}

		resetForm();
		elements.composer.scrollIntoView({ behavior: 'smooth', block: 'start' });
		if (elements.form && elements.form.elements.post_title) {
			elements.form.elements.post_title.focus();
		}
	}

	function fillForm(contact) {
		if (!elements.form || !contact) {
			return;
		}

		getRecordFieldNames().forEach(function (field) {
			if (elements.form.elements[field]) {
				elements.form.elements[field].value = contact[field] || '';
			}
		});
		setEditMode(contact);
		elements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
		elements.form.elements.post_title.focus();
	}

	function getTypeLabels() {
		var select = elements.form && elements.form.elements.contact_type;

		if (!select || Object.keys(typeLabels).length) {
			return typeLabels;
		}

		Array.prototype.forEach.call(select.options, function (option) {
			typeLabels[option.value] = option.textContent;
		});

		return typeLabels;
	}

	function getTypeLabel(value) {
		var labels = getTypeLabels();

		return labels[value] || value || 'Unclassified';
	}

	function getInitial(contact) {
		var name = (contact.post_title || contact.email || contact.phone || '?').trim();

		return name ? name.charAt(0).toUpperCase() : '?';
	}

	function getFormRecord() {
		var data = new FormData(elements.form);
		var record = {
			id: editingId
		};

		getRecordFieldNames().forEach(function (field) {
			record[field] = data.get(field) || '';
		});

		return record;
	}

	async function unlock() {
		setStatus('Decrypting contacts...', '');
		await getClient().unlock();
		await loadContacts();
		app.querySelectorAll('[data-locked]').forEach(function (node) {
			node.removeAttribute('data-locked');
		});
		if (elements.unlockButton) {
			elements.unlockButton.hidden = true;
		}
		if (elements.addButton) {
			elements.addButton.hidden = false;
		}
		if (elements.importZone) {
			elements.importZone.hidden = false;
		}
		setStatus('');
	}

	function unfoldVcardLines(text) {
		var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
		var unfolded = [];

		lines.forEach(function (line) {
			if ((line.charAt(0) === ' ' || line.charAt(0) === '\t') && unfolded.length) {
				unfolded[unfolded.length - 1] += line.slice(1);
				return;
			}

			unfolded.push(line);
		});

		return unfolded;
	}

	function decodeVcardValue(value) {
		return value
			.replace(/\\n/gi, '\n')
			.replace(/\\,/g, ',')
			.replace(/\\;/g, ';')
			.replace(/\\\\/g, '\\')
			.trim();
	}

	function parseVcardLine(line) {
		var separator = line.indexOf(':');
		var nameAndParams;

		if (separator === -1) {
			return null;
		}

		nameAndParams = line.slice(0, separator).split(';');

		return {
			name: nameAndParams[0].toUpperCase(),
			params: nameAndParams.slice(1).map(function (param) {
				return param.toUpperCase();
			}),
			value: decodeVcardValue(line.slice(separator + 1))
		};
	}

	function splitVcards(text) {
		var cards = [];
		var current = [];
		var inside = false;

		unfoldVcardLines(text).forEach(function (line) {
			if (/^BEGIN:VCARD$/i.test(line.trim())) {
				current = [];
				inside = true;
				return;
			}

			if (/^END:VCARD$/i.test(line.trim())) {
				if (inside) {
					cards.push(current);
				}
				current = [];
				inside = false;
				return;
			}

			if (inside) {
				current.push(line);
			}
		});

		return cards;
	}

	function parseVcardName(value) {
		var parts = value.split(';');
		var name = [parts[1], parts[2], parts[0]].filter(Boolean).join(' ').trim();

		return name || value;
	}

	function parseVcard(text) {
		return splitVcards(text).map(function (lines) {
			var contact = {
				post_title: '',
				email: '',
				phone: '',
				contact_type: 'other',
				private_tags: '',
				notes: ''
			};

			lines.forEach(function (line) {
				var property = parseVcardLine(line);

				if (!property) {
					return;
				}

				if (property.name === 'FN' && !contact.post_title) {
					contact.post_title = property.value;
				} else if (property.name === 'N' && !contact.post_title) {
					contact.post_title = parseVcardName(property.value);
				} else if (property.name === 'EMAIL' && !contact.email) {
					contact.email = property.value;
				} else if (property.name === 'TEL' && !contact.phone) {
					contact.phone = property.value;
				} else if (property.name === 'NOTE' && !contact.notes) {
					contact.notes = property.value;
				} else if (property.name === 'CATEGORIES' && !contact.private_tags) {
					contact.private_tags = property.value;
				}
			});

			return contact;
		}).filter(function (contact) {
			return contact.post_title || contact.email || contact.phone;
		});
	}

	async function importContactsFromFiles(files) {
		var imported = [];
		var fileList = Array.prototype.slice.call(files || []);

		for (var i = 0; i < fileList.length; i++) {
			imported = imported.concat(parseVcard(await fileList[i].text()));
		}

		if (!imported.length) {
			throw new Error('No vCard contacts found in the dropped files.');
		}

		setStatus('Importing ' + imported.length + ' encrypted contacts...', '');
		for (var j = 0; j < imported.length; j++) {
			await getClient().cpt(getCptName()).save(imported[j]);
		}

		await loadContacts();
		setStatus('Imported ' + imported.length + ' contacts.', 'success');
	}

	function getPhoneHref(phone) {
		var value = phone.replace(/[^\d+]/g, '');

		return value ? 'tel:' + value : '';
	}

	function setContactLink(node, value, href) {
		if (!node || !value) {
			if (node) {
				node.removeAttribute('href');
				node.textContent = '';
				if (node.parentNode) {
					node.parentNode.hidden = true;
				}
			}
			return;
		}

		if (node.parentNode) {
			node.parentNode.hidden = false;
		}
		node.href = href;
		node.textContent = value;
	}

	function renderContact(contact) {
		var node = elements.contactTemplate.content.firstElementChild.cloneNode(true);
		var expandButton = node.querySelector('[data-expand-contact]');
		var editButton = node.querySelector('[data-edit-contact]');
		var deleteButton = node.querySelector('[data-delete-contact]');
		var email = contact.email || '';
		var phone = contact.phone || '';
		var initial = node.querySelector('[data-contact-initial]');

		getRecordFieldNames().forEach(function (field) {
			var fieldNode = node.querySelector('[data-contact-field="' + field + '"]');

			if (fieldNode) {
				fieldNode.textContent = contact[field] || '';
			}
		});
		node.querySelector('[data-contact-field="post_title"]').textContent = contact.post_title || 'Unnamed contact';
		node.querySelector('[data-contact-field="contact_type"]').textContent = getTypeLabel(contact.contact_type);
		if (initial) {
			initial.textContent = getInitial(contact);
		}
		setContactLink(node.querySelector('[data-contact-link="email"]'), email, 'mailto:' + email);
		setContactLink(node.querySelector('[data-contact-link="phone"]'), phone, getPhoneHref(phone));

		if (expandButton) {
			expandButton.setAttribute('data-expand-contact', contact.id);
		}

		if (editButton) {
			editButton.setAttribute('data-edit-contact', contact.id);
		}

		if (deleteButton) {
			deleteButton.setAttribute('data-delete-contact', contact.id);
		}

		return node;
	}

	async function loadContacts() {
		var records = await getClient().cpt(getCptName()).all().decrypt();
		var contactRecords = records.toArray();
		var fragment = document.createDocumentFragment();
		contactsById = {};
		contactRecords.sort(function (a, b) {
			return (a.post_title || '').localeCompare(b.post_title || '', undefined, { sensitivity: 'base' });
		});
		contactRecords.forEach(function (contact) {
			contactsById[contact.id] = contact;
			fragment.appendChild(renderContact(contact));
		});

		setContactCount(contactRecords.length, 'decrypted');

		elements.contacts.replaceChildren();
		if (contactRecords.length) {
			elements.contacts.appendChild(fragment);
			return;
		}

		elements.contacts.appendChild(document.createElement('p')).textContent = 'No contacts saved yet.';
	}

	if (elements.addButton) {
		elements.addButton.addEventListener('click', function () {
			openComposer(null);
			setStatus('Adding a new encrypted contact.', 'success');
		});
	}

	if (elements.unlockButton) {
		elements.unlockButton.addEventListener('click', function () {
			unlock().catch(function (error) {
				setStatus(error.message, 'error');
			});
		});
	}

	if (elements.importZone) {
		elements.importZone.addEventListener('click', function () {
			if (elements.importFiles) {
				elements.importFiles.click();
			}
		});

		['dragenter', 'dragover'].forEach(function (eventName) {
			elements.importZone.addEventListener(eventName, function (event) {
				event.preventDefault();
				elements.importZone.classList.add('is-dragging');
			});
		});

		['dragleave', 'drop'].forEach(function (eventName) {
			elements.importZone.addEventListener(eventName, function (event) {
				event.preventDefault();
				elements.importZone.classList.remove('is-dragging');
			});
		});

		elements.importZone.addEventListener('drop', function (event) {
			importContactsFromFiles(event.dataTransfer.files).catch(function (error) {
				setStatus(error.message, 'error');
			});
		});
	}

	if (elements.importFiles) {
		elements.importFiles.addEventListener('change', function () {
			importContactsFromFiles(elements.importFiles.files).catch(function (error) {
				setStatus(error.message, 'error');
			}).finally(function () {
				elements.importFiles.value = '';
			});
		});
	}

	if (elements.form) {
		elements.form.addEventListener('submit', async function (event) {
			event.preventDefault();

			try {
				var wasEditing = Boolean(editingId);
				setStatus(editingId ? 'Updating encrypted contact...' : 'Saving encrypted contact...', '');
				await getClient().cpt(getCptName()).save(getFormRecord());

				closeComposer();
				await loadContacts();
				setStatus(wasEditing ? 'Contact updated.' : 'Contact saved.', 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});
	}

	if (elements.cancelEditButton) {
		elements.cancelEditButton.addEventListener('click', function () {
			closeComposer();
			setStatus('');
		});
	}

	if (elements.contacts) {
		elements.contacts.addEventListener('click', async function (event) {
			var expandButton = event.target.closest('[data-expand-contact]');
			var deleteButton = event.target.closest('[data-delete-contact]');
			var editButton = event.target.closest('[data-edit-contact]');
			var expandedNode;
			var contact;

			if (!expandButton && !deleteButton && !editButton) {
				return;
			}

			if (expandButton) {
				expandedNode = expandButton.closest('.contact-item').querySelector('[data-contact-expanded]');
				if (expandedNode) {
					expandedNode.hidden = !expandedNode.hidden;
					expandButton.setAttribute('aria-expanded', expandedNode.hidden ? 'false' : 'true');
				}
				return;
			}

			contact = contactsById[(deleteButton || editButton).getAttribute(deleteButton ? 'data-delete-contact' : 'data-edit-contact')];
			if (!contact) {
				setStatus('Contact could not be found in the decrypted list.', 'error');
				return;
			}

			if (deleteButton) {
				if (!window.confirm('Delete this encrypted contact?')) {
					return;
				}

				try {
					setStatus('Deleting encrypted contact...', '');
					await getClient().cpt(getCptName()).delete(contact.id);
					if (editingId === Number(contact.id)) {
						resetForm();
					}
					await loadContacts();
					setStatus('Contact deleted.', 'success');
				} catch (error) {
					setStatus(error.message, 'error');
				}
				return;
			}

			openComposer(contact);
			setStatus('Editing encrypted contact. Save to re-encrypt and update it.', 'success');
		});
	}
})();
