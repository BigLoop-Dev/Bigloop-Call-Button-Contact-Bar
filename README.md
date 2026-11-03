# Bigloop Call Button & Contact Bar

Floating call, WhatsApp, email and contact buttons for WordPress, with click analytics, schedules, a block, a shortcode and an accessible, fast front end.

- **Download:** [WordPress.org](https://wordpress.org/plugins/bigloop-call-button/)
- **Made by:** [BigLoop](https://bigloop.dev/)
- **License:** GPL-2.0-or-later

This repository is where the free plugin is developed. Bug reports and pull requests are welcome.

| Path | What |
|---|---|
| `plugins/bigloop-call-button/` | The plugin (this is what ships to WordPress.org) |
| `packages/ui/` | `@bigloop/ui`: the admin design system (tokens + React components) the plugin's settings app is built with |
| `tools/` | Icon library builder and PHP lint script |

## Requirements
- WordPress 6.5+, PHP 7.4+
- For development: Node 20+ and npm; Composer for PHPUnit, PHPCS and PHPStan

## Getting started
```bash
npm install            # JS dependencies for all workspaces
npm run build          # builds the admin app and front end into plugins/bigloop-call-button/assets/build
npm run start          # rebuild on change
npm run test:js        # Jest tests

cd plugins/bigloop-call-button
composer install       # PHP dev tools
composer test          # PHPUnit unit tests
composer lint          # WordPress Coding Standards + PHP 7.4 compatibility
composer analyse       # PHPStan level 6
```

No Composer? `npm run test:php` runs the unit suite with a built-in fallback runner, and `npm run lint:php` runs `php -l` on every file.

### Use it in WordPress
Symlink or copy `plugins/bigloop-call-button` into `wp-content/plugins/`, run `npm run build`, then activate **Bigloop Call Button & Contact Bar**.

### Integration smoke test
Runs against a real, throwaway WordPress install (it resets the plugin's data there):
```bash
php plugins/bigloop-call-button/tests/integration/smoke.php /path/to/wordpress
```

## Extending
The plugin has a versioned PHP API, passed to the `bigloop_cb/loaded` action, plus filters, actions and JavaScript hooks. See [`plugins/bigloop-call-button/docs/developers.md`](plugins/bigloop-call-button/docs/developers.md).

## Conventions
- PHP namespace `Bigloop\CallButton`, PSR-4 under `includes/`, WordPress Coding Standards.
- Hooks: `bigloop_cb/{name}`. Options/tables/CPTs: `blcb_` prefix. Capability: `manage_bigloop_contact`.
- JSX uses the classic runtime so the bundle runs on WordPress 6.5 (see `webpack.config.js`).
