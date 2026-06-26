(function (root) {
	'use strict';

	function WpAppEncryptedFields(options) {
		this.options = options || {};
		this.ajaxUrl = this.options.ajaxUrl || '';
		this.actionPrefix = this.options.actionPrefix || '';
		this.nonce = this.options.nonce || '';
		this.manifest = this.options.manifest || {};
		this.runtime = null;
		this.settings = null;
	}

	WpAppEncryptedFields.fromGlobal = function () {
		return new WpAppEncryptedFields(root.WpAppEncryptedFieldsConfig || {});
	};

	WpAppEncryptedFields.prototype.unlock = async function () {
		if (this.runtime && this.runtime.getSession()) {
			return this.runtime;
		}

		if (!this.settings) {
			this.settings = await this.request('settings', {});
		}

		this.runtime = root.WpAppCrypto.createRuntime({
			salt: this.settings.salt,
			iterations: this.settings.iterations,
			prompt: this.options.prompt || 'Enter the encryption password for this app.',
			passwordProvider: this.options.passwordProvider
		});

		await this.runtime.unlock();

		return this.runtime;
	};

	WpAppEncryptedFields.prototype.lock = function () {
		if (this.runtime) {
			this.runtime.lock();
		}
	};

	WpAppEncryptedFields.prototype.cpt = function (name) {
		return new WpAppEncryptedFieldsCpt(this, name);
	};

	WpAppEncryptedFields.prototype.request = async function (action, payload) {
		var body = Object.assign({}, payload || {}, {
			action: this.actionPrefix + '_' + action,
			nonce: this.nonce
		});

		var response = await fetch(this.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: new URLSearchParams(body)
		});
		var json = await response.json();

		if (!response.ok || !json.success) {
			throw new Error(json && json.data && json.data.message ? json.data.message : 'Encrypted fields request failed.');
		}

		return json.data;
	};

	WpAppEncryptedFields.prototype.postJson = async function (action, payload) {
		var response = await fetch(this.ajaxUrl + '?action=' + encodeURIComponent(this.actionPrefix + '_' + action) + '&nonce=' + encodeURIComponent(this.nonce), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload || {})
		});
		var json = await response.json();

		if (!response.ok || !json.success) {
			throw new Error(json && json.data && json.data.message ? json.data.message : 'Encrypted fields request failed.');
		}

		return json.data;
	};

	WpAppEncryptedFields.prototype.getCptDefinition = function (cpt) {
		var cpts = this.manifest.cpts || {};

		if (!cpts[cpt]) {
			throw new Error('Unknown encrypted fields post type: ' + cpt);
		}

		return cpts[cpt];
	};

	WpAppEncryptedFields.prototype.getEncryptedFields = function (cpt) {
		return this.getCptDefinition(cpt).encryptedFields || {};
	};

	WpAppEncryptedFields.prototype.getTaxonomies = function (cpt) {
		return this.getCptDefinition(cpt).taxonomies || [];
	};

	WpAppEncryptedFields.prototype.getAdditionalData = function (cpt, field) {
		return {
			app: this.manifest.app && this.manifest.app.slug ? this.manifest.app.slug : this.actionPrefix,
			cpt: cpt,
			field: field,
			version: 1
		};
	};

	WpAppEncryptedFields.prototype.getPaddingOptions = function (fieldDefinition) {
		return {
			type: fieldDefinition.type || 'text',
			minBytes: fieldDefinition.minBytes || 512,
			bucketBytes: fieldDefinition.bucketBytes || fieldDefinition.minBytes || 512
		};
	};

	WpAppEncryptedFields.prototype.encryptRecord = async function (cpt, record) {
		await this.unlock();

		var encrypted = {};
		var post = {};
		var taxonomies = {};
		var fields = this.getEncryptedFields(cpt);
		var taxonomyNames = this.getTaxonomies(cpt);

		Object.keys(record || {}).forEach(function (key) {
			if (key === 'id' || fields[key]) {
				return;
			}

			if (taxonomyNames.indexOf(key) !== -1) {
				taxonomies[key] = record[key];
				return;
			}

			if (key.indexOf('post_') === 0) {
				post[key] = record[key];
			}
		});

		for (var field in fields) {
			if (!Object.prototype.hasOwnProperty.call(fields, field) || !Object.prototype.hasOwnProperty.call(record, field)) {
				continue;
			}

			var options = this.getPaddingOptions(fields[field]);
			options.aad = this.getAdditionalData(cpt, field);
			encrypted[field] = await this.runtime.encrypt(record[field], options);
		}

		return {
			id: record && record.id ? record.id : 0,
			cpt: cpt,
			post: post,
			taxonomies: taxonomies,
			encrypted: encrypted
		};
	};

	WpAppEncryptedFields.prototype.decryptRecord = async function (record) {
		await this.unlock();

		var cpt = record.cpt;
		var decrypted = {
			id: record.id,
			cpt: cpt
		};
		var taxonomies = record.taxonomies || {};
		var post = record.post || {};
		var fields = this.getEncryptedFields(cpt);

		Object.keys(post).forEach(function (key) {
			decrypted[key] = post[key];
		});

		Object.keys(taxonomies).forEach(function (key) {
			decrypted[key] = Array.isArray(taxonomies[key]) && taxonomies[key].length === 1 ? taxonomies[key][0] : taxonomies[key];
		});

		for (var field in fields) {
			if (!Object.prototype.hasOwnProperty.call(fields, field)) {
				continue;
			}

			decrypted[field] = record.encrypted && record.encrypted[field]
				? await this.runtime.decrypt(record.encrypted[field], { aad: this.getAdditionalData(cpt, field) })
				: '';
		}

		return decrypted;
	};

	function WpAppEncryptedFieldsCpt(client, cpt) {
		this.client = client;
		this.cptName = cpt;
	}

	WpAppEncryptedFieldsCpt.prototype.all = function (args) {
		return new WpAppEncryptedFieldsQuery(this, 'list', args || {});
	};

	WpAppEncryptedFieldsCpt.prototype.get = function (id) {
		return new WpAppEncryptedFieldsQuery(this, 'get', { id: id });
	};

	WpAppEncryptedFieldsCpt.prototype.save = async function (record) {
		var payload = await this.client.encryptRecord(this.cptName, record || {});
		var response = await this.client.postJson('save', payload);

		return this.client.decryptRecord(response.record);
	};

	WpAppEncryptedFieldsCpt.prototype.set = async function (id, field, value) {
		var record = { id: id };
		record[field] = value;

		return this.save(record);
	};

	WpAppEncryptedFieldsCpt.prototype.delete = function (id) {
		return this.client.postJson('delete', {
			cpt: this.cptName,
			id: id
		});
	};

	function WpAppEncryptedFieldsQuery(cptClient, action, args) {
		this.cptClient = cptClient;
		this.action = action;
		this.args = args || {};
	}

	WpAppEncryptedFieldsQuery.prototype.decrypt = async function () {
		var payload = Object.assign({}, this.args, {
			cpt: this.cptClient.cptName
		});
		var response = await this.cptClient.client.request(this.action, payload);

		if (response.records) {
			var records = [];
			for (var i = 0; i < response.records.length; i++) {
				records.push(await this.cptClient.client.decryptRecord(response.records[i]));
			}
			return new WpAppDecryptedRecordSet(records);
		}

		return this.cptClient.client.decryptRecord(response.record);
	};

	function WpAppDecryptedRecordSet(records) {
		this.records = records || [];
	}

	WpAppDecryptedRecordSet.prototype.where = function (field, value) {
		return new WpAppDecryptedRecordSet(this.records.filter(function (record) {
			return record[field] === value;
		}));
	};

	WpAppDecryptedRecordSet.prototype.whereContains = function (field, value) {
		return new WpAppDecryptedRecordSet(this.records.filter(function (record) {
			var fieldValue = record[field];

			if (Array.isArray(fieldValue)) {
				return fieldValue.indexOf(value) !== -1;
			}

			return String(fieldValue || '').indexOf(value) !== -1;
		}));
	};

	WpAppDecryptedRecordSet.prototype.sortBy = function (field) {
		return new WpAppDecryptedRecordSet(this.records.slice().sort(function (a, b) {
			return String(a[field] || '').localeCompare(String(b[field] || ''));
		}));
	};

	WpAppDecryptedRecordSet.prototype.toArray = function () {
		return this.records.slice();
	};

	root.WpAppEncryptedFields = WpAppEncryptedFields;
	root.WpAppDecryptedRecordSet = WpAppDecryptedRecordSet;
})(typeof window !== 'undefined' ? window : globalThis);
