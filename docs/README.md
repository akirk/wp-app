# WpApp Documentation

Start with the [README](../README.md) for the two-command quick start, then work through these in order. Each page is short and single-topic.

## Build your first app

1. [Getting Started](getting-started.md) — install, the minimal `WpApp` setup, template functions
2. [Tutorial](../TUTORIAL.md) — step by step from a single route to a full app
3. [BaseApp Pattern](baseapp.md) — the structure the scaffolder generates for larger apps

## Reference

- [Configuration](configuration.md) — every constructor option
- [Routing](routing.md) — URL patterns, parameters, templates, per-route capabilities
- [Masterbar](masterbar.md) — admin bar menu, icon, app switcher
- [Access Control](access-control.md) — login, capabilities, gating REST reads, per-object meta caps

## Make the app do more

- [Abilities](abilities.md) — expose the app to assistants, automation and other apps, and design abilities worth calling
- [Offline PWA Support](offline-pwa.md) — manifest, service worker, offline caching
- [Client-Side Encryption](encryption.md) — encrypt fields in the browser before they reach the server

## Examples

- [`examples/community-app/`](../examples/community-app/) — BaseApp, BaseStorage, custom tables, REST endpoints, abilities
- [`examples/encrypted-contacts-app/`](../examples/encrypted-contacts-app/) — client-side encryption in practice
- [`examples/minimal-app/`](../examples/minimal-app/) — the smallest working plugin

## For agents

[`skills/wpapp/SKILL.md`](../skills/wpapp/SKILL.md) condenses these pages into a skill for coding assistants; the scaffolder installs it into generated projects.
