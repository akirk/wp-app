# Client-Side Encryption

WpApp includes a small browser crypto runtime for apps that want WordPress to store structure while sensitive text is encrypted and decrypted client-side.

This protects against database disclosure. It does not protect against a compromised server, malicious plugin code, compromised browser, XSS, or an attacker who can alter the JavaScript delivered to the user.

## Model

Use WordPress for app structure:

- custom post types
- taxonomies
- authorship
- statuses
- capabilities
- routing
- REST endpoints

Use encrypted fields for text that carries sensitive human meaning:

- names
- contact details
- notes
- private tags
- comments
- descriptions

For example, a journalist source app can store each source as a custom post type and keep broad workflow terms queryable, while storing source names and notes as encrypted post meta.

## Enqueueing the Runtime

```php
wp_app_enqueue_crypto_runtime( 'my-app' );
```

This exposes `window.WpAppCrypto`.

## Creating a Runtime

```js
const runtime = WpAppCrypto.createRuntime({
	salt: settings.salt,
	iterations: 250000,
	prompt: 'Enter the encryption password for this app.'
});

await runtime.unlock();
```

The default runtime prompts for a password. Apps can provide their own unlock UI with `passwordProvider`:

```js
const runtime = WpAppCrypto.createRuntime({
	salt: settings.salt,
	passwordProvider: () => passwordInput.value
});
```

## Encrypting Fields

```js
const encryptedName = await runtime.encrypt('Confidential Alias', {
	type: 'source_name',
	aad: {
		app: 'sources',
		field: 'name'
	},
	minBytes: 512,
	bucketBytes: 512
});
```

The encrypted envelope is JSON-safe and can be stored in post meta, custom tables, or options:

```json
{
	"v": 1,
	"alg": "AES-GCM",
	"kdf": "PBKDF2-SHA-256",
	"iterations": 250000,
	"salt": "...",
	"iv": "...",
	"aad": "...",
	"ciphertext": "..."
}
```

## Decrypting Fields

```js
const name = await runtime.decrypt(encryptedName, {
	aad: {
		app: 'sources',
		field: 'name'
	}
});
```

If the password, salt, ciphertext, or authenticated data is wrong, decryption fails.

## Padding

Short values leak useful metadata through length. The runtime encrypts a plaintext payload containing:

- the field type
- the real value
- random `prepad`
- random `postpad`

`minBytes` sets the minimum plaintext envelope size. `bucketBytes` rounds plaintext sizes up to buckets.

```js
await runtime.encrypt('FBI', {
	type: 'private_tag',
	minBytes: 512,
	bucketBytes: 512
});
```

Padding does not make AES harder to break. It reduces metadata leakage from very short texts.

## Search Tradeoffs

Encrypted fields are not queryable by WordPress. Prefer this split:

- public/non-sensitive structure in taxonomies and post fields
- sensitive labels and details in encrypted fields
- client-side filtering after decryption for private tags

If server-side exact search is required, an app can store keyed blind indexes, but that leaks equality: the server can tell that two records share the same hidden value.

## Example

See `examples/encrypted-sources-app` for a custom post type app that stores source records structurally while encrypting names, notes, contact details, and private tags in the browser.
