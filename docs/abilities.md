# Abilities

An ability is a named, described, schema-typed operation that your app exposes through the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/). Routes are how people use your app; abilities are how everything else does: AI assistants, automation tools like Pipes, other apps on the same site, and MCP clients — none of which have read your code.

That last point is what makes abilities different from a REST endpoint. A REST endpoint is called by a developer who read the docs. An ability is called by something that read only the ability's own description and schemas, and decided from those whether, when, and how to call it. **The description and schemas *are* the API.** Most of this page is about writing them well.

## Registering an ability from your app

Register on `wp_abilities_api_init`, and reuse the app's capability so an ability is never a way around the app's access control:

```php
add_action( 'wp_abilities_api_categories_init', function () {
	wp_register_ability_category( 'invoices', [
		'label'       => __( 'Invoices', 'invoices' ),
		'description' => __( 'Invoices, clients and payments managed by the Invoices app.', 'invoices' ),
	] );
} );

add_action( 'wp_abilities_api_init', function () use ( $app ) {
	wp_register_ability( 'invoices/get-invoice', [
		'label'               => __( 'Get invoice', 'invoices' ),
		'description'         => 'Returns one invoice by ID: number, client, status, line items, totals and pdf_url. Use invoices/list-invoices to find IDs.',
		'category'            => 'invoices',
		'input_schema'        => [
			'type'                 => 'object',
			'properties'           => [
				'id' => [ 'type' => 'integer', 'description' => 'Invoice ID, as returned by invoices/list-invoices.' ],
			],
			'required'             => [ 'id' ],
			'additionalProperties' => false,
		],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [
				'id'      => [ 'type' => 'integer', 'description' => 'Pass to invoices/update-invoice or invoices/delete-invoice.' ],
				'number'  => [ 'type' => 'string', 'description' => 'Human-readable invoice number, e.g. 2026-0042.' ],
				'status'  => [ 'type' => 'string', 'enum' => [ 'draft', 'sent', 'paid', 'void' ] ],
				'total'   => [ 'type' => 'number', 'description' => 'Gross total in the site currency.' ],
				'pdf_url' => [ 'type' => 'string', 'description' => 'Download link for the PDF, if one has been generated.' ],
			],
		],
		'execute_callback'    => 'invoices_get_invoice',
		'permission_callback' => function ( $input ) use ( $app ) {
			return current_user_can( $app->get_required_capability() ?: 'read' );
		},
		'meta'                => [
			'annotations' => [ 'readonly' => true, 'destructive' => false ],
		],
	] );
} );
```

Leave `meta.public` alone. Your `permission_callback` is the gate, so an ability is exactly as private as the app: [AI Assistant](https://github.com/akirk/ai-assistant) and [MCP Connect](https://github.com/akirk/mcp-connect) expose it to signed-in users who pass that check, and to nobody else. Core's `public` flag only controls whether the stock REST and MCP adapter *list* the ability and, despite the name, `true` never means anonymous — the permission callback still runs on every call.

In a `BaseApp` subclass, attach these hooks from `__construct()` and put the registrations in a method; don't call `wp_register_ability()` from the constructor itself (see [BaseApp Pattern](baseapp.md)).

Use the app's URL path as both the ability namespace (`invoices/…`) and the category, so a caller who lists categories sees one entry per app.

## Designing abilities worth calling

The test for every ability: **could a caller who has seen nothing but the output of `list` abilities in your category plan a whole task with it?** If the answer needs a look at your code, the description or schema is missing something.

### One ability, one verb, one noun

`list-invoices`, `get-invoice`, `create-invoice`, `mark-invoice-paid`. Not `manage-invoices` with an `action` parameter, and not `invoice` with a mode switch. A caller chooses between abilities by reading descriptions; a switch inside one ability hides the choice where it can't be read, and forces one permission and one annotation set onto operations that need different ones.

Name the domain object the way users say it, not the way your database does. `client`, not `wp_inv_party_row`.

### Describe what comes back, not just what happens

"Deletes an invoice" is half a description. The caller needs to know what it gets, what it can do with it, and what to do when things go sideways:

```
Bad:  Get an invoice.
Good: Returns one invoice by ID with line items, totals, status and pdf_url.
      Returns error code not_found if no invoice has that ID; do not create a
      replacement — report it instead.
```

Put in the description anything the schema can't say: ordering between abilities, what to do on ambiguity, side effects, limits.

### Schemas are the contract, so make them tight

- Describe every property. A property with a type and no description is a guess the caller has to make.
- `additionalProperties: false` on inputs. An unknown argument silently ignored is a call that "succeeded" while doing the wrong thing.
- Use `enum` for closed sets (`status`, `sort`), `format` for dates and URLs, `minimum`/`maximum` for counts.
- Always give an `output_schema`. Without one the caller has to parse an example result and hope.

### Connect abilities through IDs

The most useful sentence in a property description is "as returned by …" or "pass to …". `list-invoices` returns `id`s; `get-invoice`'s `id` says where it comes from; `get-invoice`'s output `id` says what accepts it. That is how a caller chains three abilities into one task without asking.

### Be honest about side effects

Annotate every ability:

| Annotation | Set it when |
|---|---|
| `readonly: true` | Nothing changes. Callers can run these freely and may skip confirmation. |
| `destructive: true` | Something is deleted, overwritten or otherwise not recoverable. Callers confirm before running these. |
| `idempotent: true` | Calling twice with the same input has the same effect as once. Lets callers retry safely. |

A write that's mislabelled `readonly` gets executed without asking. A read mislabelled `destructive` gets a confirmation dialog on every call and is soon avoided. Both are worse than no annotation.

### Errors are answers

Return `WP_Error` with a stable code the caller can act on — `not_found`, `invalid_status_transition`, `permission_denied` — and a message that says what to do. Never return `false`, `null` or an empty array to mean failure: those look like a valid result with nothing in it.

### Bound the output

A list ability without paging returns the whole table into a context window. Give every `list-*` ability `page`/`per_page` (with a sane maximum and default), a `search` or filter parameter, and a summary shape — ids, names, status, a date — rather than full records. `get-*` is where the full record lives.

### Permissions mirror the app

`permission_callback` uses the same capability the app's routes use, and per-object checks use the same meta caps you'd use in a template (see [Access Control](access-control.md#per-object-capabilities-meta-caps)). If a user couldn't see it in the app, an assistant acting for that user must not be able to fetch it through an ability.

### What not to build

- **An escape hatch.** `run-query`, `execute-php`, `call-method`: one ability that can do anything is one that can't be described, permissioned or annotated, and it teaches callers to bypass everything else you registered.
- **A mirror of your internals.** Abilities named after class methods, taking the arguments those methods take. Design from the task inward, not from the code outward.
- **A single mega-ability.** If the description needs a table of `action` values, it's several abilities.

## Making the app discoverable in assistants

Registering abilities makes them *callable*; assistants still need to know your app owns the words users use. The [AI Assistant](https://github.com/akirk/ai-assistant) plugin documents its integration points in [Integrating Plugins with AI Assistant](https://github.com/akirk/ai-assistant/blob/main/docs/plugin-integration.md). Two are worth doing for every app:

- **Domain terms** — `ai_assistant_ability_domains` maps your namespace to the vocabulary users reach for ("invoice, billing, payment, client"), so the assistant picks your abilities over generic tools.
- **Welcome tips** — `ai_assistant_welcome_tips` is keyed by the first URL path component, which is your app's route slug. One or two tips per app, phrased as things the user can ask for.

Structure the app's own markup so an assistant looking at the current page finds what the user means: headings with `aria-labelledby` on major regions, `<caption>` on tables, labels on forms, and a stable `id` on the region users call "this". That's the same structure that helps screen readers, so it's never wasted.

## Checklist

- [ ] One ability per verb-noun; no `action` switches.
- [ ] Description says what is returned and what to do on failure.
- [ ] Every input and output property has a description; inputs use `additionalProperties: false`.
- [ ] Closed sets use `enum`; `output_schema` is present.
- [ ] IDs say which ability produced them and which ones accept them.
- [ ] `readonly` / `destructive` / `idempotent` are accurate.
- [ ] Failures are `WP_Error` with stable codes.
- [ ] `list-*` abilities page and summarise; `get-*` returns the full record.
- [ ] `permission_callback` reuses the app's capability and meta caps.
- [ ] Namespace and category match the app's route slug; `meta.public` is left unset.

The [community app example](../examples/community-app/) registers four abilities this way.

## Related Documentation

- [Access Control](access-control.md) - The capability abilities should reuse
- [BaseApp Pattern](baseapp.md) - Where registration hooks belong
- [Routing](routing.md) - The route slug that names the namespace
- [WordPress Abilities API handbook](https://developer.wordpress.org/apis/abilities-api/) - Registration reference
