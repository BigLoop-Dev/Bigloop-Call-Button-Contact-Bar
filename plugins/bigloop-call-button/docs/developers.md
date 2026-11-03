# Bigloop Call Button & Contact Bar: developer reference

Everything listed here is a supported extension point. Internal classes can change between releases, so build on these hooks, the public API object and the REST API instead.

## Public API

```php
add_action( 'bigloop_cb/loaded', function ( $api ) {
	if ( version_compare( $api->version(), '1.0.0', '<' ) ) {
		return;
	}
	$api->plugin_version();          // e.g. "0.5.0"
	$api->setting( 'analytics.enabled' );
	// API 1.1.0 (plugin 0.6.0):
	$api->can_manage();       // Current user may manage the plugin.
	$api->rest_namespace();   // "bigloop-call-button/v1"
	$api->widgets();          // [ [ id, title, enabled, type ], … ]
	$api->actions();
} );
```

`$api->version()` is the API version. It changes only when the API object itself changes.

## Front-end JavaScript

Every click on a contact action dispatches a DOM event:

```js
document.addEventListener( 'bigloop:contact-click', ( event ) => {
	const { widget, action, type, href, label } = event.detail;
	// widget/action: numeric IDs, type: "phone" | "whatsapp" | …, href: the link, label: visible text.
} );
```

Just before a click is sent to the click counter, the plugin dispatches `bigloop:before-track` on `document`. Listeners may add flat key/value pairs to `event.detail.extra` (at most 20, each up to 200 characters). They reach PHP listeners of `bigloop_cb/click_tracked` as the second argument; Free never stores them.

```js
document.addEventListener( 'bigloop:before-track', ( e ) => {
	e.detail.extra.campaign = 'spring-sale';
} );
```

When **Settings → Analytics → Send click events to Google Analytics** is on and the page already defines `gtag()` (GA4) or `dataLayer` (Google Tag Manager), the plugin sends the event `bigloop_contact_click` (configurable) with the parameters `widget_id`, `action_id`, `action_type` and `link_text`. The plugin never loads Google scripts itself.

## Actions

| Hook | Arguments | When |
|---|---|---|
| `bigloop_cb/loaded` | `$api` | Plugin booted. |
| `bigloop_cb/register_action_types` | `ActionTypeRegistry $registry` | Add action types: `$registry->register( new ActionType( … ) )`. |
| `bigloop_cb/register_conditions` | `ConditionRegistry $registry` | Add display-rule conditions. |
| `bigloop_cb/widgets_changed` · `actions_changed` · `schedules_changed` · `presets_changed` | `int $id, string $reason` | After an item is created, updated or deleted. |
| `bigloop_cb/settings_updated` | `array $values` | After settings are saved. |
| `bigloop_cb/runtime_compiled` | `array $snapshot` | After the front-end snapshot is rebuilt. |
| `bigloop_cb/click_tracked` | `array $event, array $extra` | After a click is stored. `$event` includes its `id`; `$extra` is the untrusted data added by `bigloop:before-track` listeners. |
| `bigloop_cb/analytics_aggregated` | `int $days, int $deleted, bool $remaining` | After the daily roll-up. |
| `bigloop_cb/analytics_purged` | none | After all statistics are deleted. |

## Filters

| Filter | Value | Purpose |
|---|---|---|
| `bigloop_cb/can_manage` | `bool $granted, WP_User $user` | Who may manage widgets and settings. |
| `bigloop_cb/analytics_cap` | `string $cap` (default `''`) | Capability to view analytics. Empty means the management permission. |
| `bigloop_cb/widget_visible` | `bool $visible, array $widget` | Hide a widget on the current request (server side). |
| `bigloop_cb/render_request` | `bool $render` | Disable floating widgets on a request. |
| `bigloop_cb/frontend_html` | `string $html, array $widgets` | Final footer markup. |
| `bigloop_cb/frontend_config` | `array { track, ga, obj }` | Front-end runtime config (beacon URL, GA event name, page ID). |
| `bigloop_cb/custom_url_protocols` | `string[]` | Schemes allowed in "Custom link" actions. |
| `bigloop_cb/geo_country` | `?string $code, array $server` | Supply the visitor's country from another source. |
| `bigloop_cb/track_event` | `array\|false $event` | Change or skip (return `false`) a click before it is stored. |
| `bigloop_cb/track_rate_limit` | `int` (default 30) | Max clicks recorded per visitor every 10 minutes. |
| `bigloop_cb/settings_schema` | `array` | Add settings sections. |
| `bigloop_cb/admin_pages` | `array` | Add or relabel admin pages. |
| `bigloop_cb/admin_links` | `array` | External links in the dashboard. |
| `bigloop_cb/admin_boot_notices` | `array` | Notices shown inside the plugin's screens. |
| `bigloop_cb/donate_url` | `string` (default `https://bigloop.dev/buy-us-coffee/`) | Link in the "buy us a coffee" notice. Return `''` to turn the notice off. |
| `bigloop_cb/service_providers` | `ServiceProvider[]` | Register additional service providers (used by Pro). |
| `bigloop_cb/pro_status` | `array` | Edition status shown on the dashboard. |

## REST API (`/wp-json/bigloop-call-button/v1/`)

Management routes need a logged-in user with the management permission and the standard `wp_rest` nonce.

| Route | Methods |
|---|---|
| `/widgets`, `/widgets/{id}`, `/widgets/{id}/duplicate`, `/widgets/{id}/status`, `/widgets/reorder`, `/widgets/defaults`, `/widgets/preview` | Widget CRUD and builder helpers |
| `/actions`, `/actions/{id}`, `/actions/{id}/duplicate`, `/actions/{id}/status`, `/actions/validate`, `/action-types` | Action library |
| `/schedules`, `/schedules/{id}`, `/schedules/{id}/duplicate`, `/schedules/preview`, `/schedules/meta` | Schedules |
| `/presets`, `/presets/{id}`, `/presets/{key}/duplicate` | Design presets |
| `/conditions`, `/lookup/posts`, `/lookup/terms` | Display-rule metadata and pickers |
| `/settings`, `/overview` | Settings and dashboard |
| `/analytics?from=Y-m-d&to=Y-m-d` (GET), `/analytics` (DELETE) | Statistics report, delete all statistics |
| `/track` (POST, **public**) | Click beacon `{ w, a, d, o }`. Validated against live widgets and rate-limited. |
| `/geo` (GET, **public**) | Visitor country, when country targeting is enabled |

## Admin screens for extensions

Add a page with the `bigloop_cb/admin_pages` filter (`[ 'route' => [ 'slug', 'title', 'menu', 'nav' (optional short label) ] ]`), then render it from a script that depends on the `bigloop-call-button-admin` handle:

```js
wp.hooks.addFilter( 'bigloop.admin.screen', 'my-ext', ( screen, route, ctx ) => route === 'leads' ? wp.element.createElement( Leads, ctx ) : screen );
wp.hooks.addFilter( 'bigloop.admin.nav', 'my-ext', ( nav ) => [ ...nav, 'leads' ] );
```

The `pro` route can also be replaced this way.

## Shortcode, block and Elementor

- `[bigloop_contact id="12"]`, with optional `align="left|center|right"` and `display="inline|floating"`.
- Block: **Contact Widget** (`bigloop/contact-widget`).
- Elementor: **Contact Widget** in the General category. It is registered only when Elementor is active.

## Data

| Data | Storage |
|---|---|
| Widgets, actions, schedules, custom presets | Private post types `blcb_widget`, `blcb_action`, `blcb_schedule` and `blcb_preset`, with JSON config in `_blcb_config` |
| Settings | Option `blcb_settings` |
| Front-end snapshot | Option `blcb_runtime` (autoloaded; rebuilt on change) |
| Raw clicks | Table `{prefix}blcb_events`: widget, action, type, device class, post ID and UTC time. No personal data. Deleted after the retention period. |
| Daily totals | Table `{prefix}blcb_stats_daily`, by site-local day. Kept indefinitely. |
| Roll-up watermark | Option `blcb_stats_aggregated_until` |

The daily job `blcb_daily_maintenance` rolls up complete days and applies retention. The analytics screen also catches up on demand when WP-Cron has not run.
