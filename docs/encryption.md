# Client-Side Encrypted Fields

WpApp can add client-side encrypted fields to existing WordPress post types. WordPress still owns the content model: register custom post types and taxonomies normally in PHP, then use a JSON manifest to mark selected fields as encrypted.

This protects against database disclosure. It does not protect against compromised plugin code, XSS, a compromised browser, or a server that serves malicious JavaScript.

## Register WordPress Normally

```php
register_post_type( 'journalist_source', [
	'public'   => false,
	'show_ui'  => true,
	'supports' => [ 'title', 'author' ],
] );

register_taxonomy( 'source_risk', 'journalist_source', [
	'public'  => false,
	'show_ui' => true,
] );
```

Then augment that model with encrypted fields:

```php
$encrypted_fields = wp_app_register_client_encrypted_fields(
	__DIR__ . '/wp-app-encrypted-fields.json',
	[
		'action_prefix' => 'sources_encrypted',
	]
);
```

On app pages, enqueue the stable JS runtime and manifest config:

```php
$encrypted_fields->enqueue_assets( 'sources' );
```

## Manifest

`wp-app-encrypted-fields.json` describes only the encrypted-field layer. It does not register post types or taxonomies.

```json
{
	"version": 1,
	"app": {
		"slug": "sources",
		"name": "Sources"
	},
	"cpts": {
		"journalist_source": {
			"encryptedFields": {
				"post_title": {
					"label": "Source alias",
					"storage": "post_field",
					"field": "post_title",
					"minBytes": 512
				},
				"contact": {
					"label": "Contact",
					"storage": "post_meta",
					"metaKey": "_encrypted_contact",
					"minBytes": 512
				},
				"notes": {
					"label": "Notes",
					"storage": "post_meta",
					"metaKey": "_encrypted_notes",
					"minBytes": 1024,
					"bucketBytes": 1024
				}
			},
			"taxonomies": [
				"source_risk"
			]
		}
	}
}
```

The `cpts` keys are WordPress post type names. They can reference custom post types or built-in post types.

## JavaScript API

The runtime exposes `window.WpAppEncryptedFields`. It receives encrypted JSON from `admin-ajax.php`, decrypts in the browser, and returns plain JSON to app code.

```js
const encrypted = WpAppEncryptedFields.fromGlobal();

await encrypted.unlock();

const sources = await encrypted
	.cpt('journalist_source')
	.all()
	.decrypt();

const highRisk = sources
	.where('source_risk', 'high')
	.whereContains('post_title', 'Ada')
	.toArray();
```

Saving accepts plain JSON. The runtime encrypts configured fields and sends encrypted envelopes to WordPress.

```js
await encrypted.cpt('journalist_source').save({
	post_title: 'Ada',
	contact: 'signal: ...',
	notes: 'Met near courthouse',
	source_risk: 'high'
});
```

Updating a single field:

```js
await encrypted
	.cpt('journalist_source')
	.set(123, 'notes', 'Updated note');
```

## Password Setup

The default unlock flow uses a password input dialog, not `window.prompt()`.

When no encrypted fields or verifier exist yet, the runtime shows a create-password flow. The user chooses an app-specific encryption password, confirms it, and the browser immediately stores a small encrypted verifier through AJAX.

On later visits, the runtime shows an unlock flow. It derives the key from the entered password and decrypts the verifier before app code can read records. If verifier decryption fails, the password is rejected immediately.

This password is not the user's WordPress password. WordPress stores the salt and encrypted verifier, but cannot recover the password.

## Storage

Encrypted fields can be stored in post fields or post meta.

```json
{
	"storage": "post_field",
	"field": "post_title"
}
```

```json
{
	"storage": "post_meta",
	"metaKey": "_encrypted_notes"
}
```

Encrypting standard fields such as `post_title` means WordPress admin screens and server-side search see ciphertext. Keep fields plaintext when WordPress needs to query, sort, or display them server-side.

## Padding

Short values leak useful metadata through length. The browser runtime encrypts a plaintext payload containing:

- field type
- real value
- random `prepad`
- random `postpad`

`minBytes` sets the minimum plaintext envelope size. `bucketBytes` rounds plaintext sizes up to buckets.

Padding does not make AES harder to break. It reduces metadata leakage from very short texts.

## Evolution

Adding an encrypted field is a manifest change. Existing records simply lack that field until the user saves a value.

Labels, padding, and UI type can change freely. Treat field IDs, `field`, and `metaKey` as compatibility identifiers; renaming them is a migration.

## Example

See `examples/encrypted-sources-app` for a hybrid app that registers a CPT and taxonomies in PHP, then uses a manifest to encrypt `post_title`, contact details, notes, and private tags client-side.
