# WpApp Documentation

Start with [Getting Started](getting-started.md) to install the framework and create a minimal app. Follow the [Tutorial](../TUTORIAL.md) for a walkthrough, or choose a topic below for the task at hand.

## Start an App

- [Getting Started](getting-started.md) — installation, minimal app, and local development
- [Tutorial](../TUTORIAL.md) — build from a single route to an app with storage and REST endpoints
- [BaseApp Pattern](baseapp.md) — app structure, BaseStorage, database schema, and data access

## Build Pages

- [Routing](routing.md) — URL patterns, parameters, template selection, and rewrite rules
- [Templates](templates.md) — page structure, template helpers, and rendering order
- [CSS and JavaScript Assets](assets.md) — external files, app scopes, and dynamic values
- [Rendering Gutenberg Content](gutenberg-content.md) — block CSS, galleries, and lightboxes

## Customize Appearance and Navigation

- [Masterbar](masterbar.md) — app menus, user menus, and visibility
- [App Themes](themes.md) — register template variants, choose defaults, and load theme assets
- [Colors and Dark Mode](colors.md) — admin color tokens, foreground/background pairs, and default styles
- [Configuration](configuration.md) — constructor options, site-wide anonymous access, launcher integration, and common configurations

## Data, Permissions, and Integrations

- [Access Control](access-control.md) — login, roles, capabilities, REST reads, and per-object permissions
- [Abilities](abilities.md) — expose app actions to assistants, automation, and other apps
- [Offline PWA Support](offline-pwa.md) — manifests, service workers, and offline caching
- [Client-Side Encryption](encryption.md) — encrypt fields in the browser before they reach the server

## Examples

- [`minimal-app`](https://github.com/akirk/wp-app-examples/tree/main/minimal-app) — the smallest working plugin
- [`community-app`](https://github.com/akirk/wp-app-examples/tree/main/community-app) — BaseApp, BaseStorage, custom tables, REST endpoints, and abilities
- [`encrypted-contacts-app`](https://github.com/akirk/wp-app-examples/tree/main/encrypted-contacts-app) — client-side encryption in practice

## For Agents

[`skills/wpapp/SKILL.md`](../skills/wpapp/SKILL.md) condenses the framework guidance into a skill for coding assistants; the scaffolder installs it into generated projects.
