=== Bigloop Call Button & Contact Bar ===
Contributors: bigloop
Donate link: https://bigloop.dev/buy-us-coffee/
Tags: call button, click to call, contact bar, whatsapp, floating button
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Floating call buttons, contact menus and sticky contact bars with business hours, display rules and privacy-friendly click statistics.

== Description ==

Bigloop Call Button & Contact Bar helps visitors reach you in one tap: phone, SMS, WhatsApp, email, maps, directions, booking links and messaging apps. Everything in this plugin is free, with no account, no limits and no external services.

= Contact widgets =

* **Three widget types:** a single floating button, an expandable menu of contact options, and a sticky contact bar (top or bottom, full width or floating).
* **14 action types:** phone call, SMS, WhatsApp, email, map location, directions, appointment booking, website link, custom link (including app links such as facetime: or skype:), Telegram, Messenger, Viber, Signal and LINE.
* **Reusable action library:** edit a phone number once and every widget using it updates.
* **Unlimited widgets and actions.**

= Visual builder =

* A live preview on a sample site for desktop, tablet and mobile, drawn by the same code as your real site.
* Shapes: circle, squircle (true continuous corners), rounded, square, pill or a custom radius.
* Colours, gradients, borders, shadows, typography, hover and pressed states, entrance and attention animations.
* Separate sizes and positions for desktop, tablet and mobile.
* 10 ready-made design presets, plus your own saved presets.

= Business hours and display rules =

* **Schedules:** weekly hours with several time ranges per day, overnight hours, holidays, special opening hours and timezones.
* **After-hours actions:** hide a widget outside opening hours, or show different actions, for example WhatsApp instead of the phone.
* **Display rules:** show widgets only on chosen pages, posts, post types, categories or tags, special pages, URLs, for logged-in or logged-out visitors, or on WooCommerce shop, product, cart and checkout pages.
* **Campaign and location rules:** show a widget for a URL parameter such as `utm_source`, or by the visitor's country (from your host's or Cloudflare's country header).
* **Show after scrolling or after a delay.**
* Works with page caching: time, URL-parameter and country rules are checked in the visitor's browser.

= Privacy-friendly analytics =

* See clicks over time, by action type, by widget, by action, by device and by page.
* Statistics are stored in your own database. No cookies, no IP addresses, no personal data, so no consent banner is needed for them.
* Optional: send each click to Google Analytics 4 or Google Tag Manager as an event when your site already uses them.
* Choose how long detailed records are kept. Delete all statistics with one click.

= Works with your site =

* **Block editor:** the Contact Widget block.
* **Elementor:** the Contact Widget element.
* **Shortcode:** `[bigloop_contact id="12"]`, with `align="center"` or `align="right"`, or `display="floating"` to float the widget on that page only.
* **Accessible:** keyboard support, screen-reader labels, visible focus, reduced-motion support, and default colours that meet WCAG contrast.
* **Fast:** no jQuery, no icon fonts and no extra database queries on the front end. The script is about 2 KB gzipped and loads only on pages that show a widget.
* **For developers:** documented hooks, a front-end `bigloop:contact-click` event and a REST API. See `docs/developers.md` in the plugin folder.

= Privacy =

This plugin does not contact any external server and does not send data to Bigloop. It sets no cookies.

* **Click statistics** (optional, on by default) store the widget, the action, the device type (desktop, tablet or mobile), the page and the time of each click. No IP address, browser details or anything that identifies a visitor is stored. A short-lived, salted hash of the visitor's IP address is held in a transient for up to 10 minutes to limit abuse, and is never written to the statistics.
* **Country targeting** (off by default) reads a country header that your host or CDN already adds. The visitor's browser keeps the two-letter code in session storage until the tab is closed.
* **Google Analytics events** (on by default) are only sent when your site already loads Google Analytics or Tag Manager.
* Suggested text for your privacy policy is added under **Settings → Privacy → Policy Guide**.

Links you configure (for example WhatsApp or Google Maps) open only when a visitor taps them.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Open **Bigloop Contact → Actions** and add your phone number, WhatsApp, email and so on.
4. Open **Bigloop Contact → Create Widget**, add your actions, choose a style and enable the widget.
5. Optional: add your opening hours under **Bigloop Contact → Schedules** and choose them on the widget's Scheduling tab.

== Frequently Asked Questions ==

= Can I preview a widget before it goes live? =

Yes. The widget editor shows a live preview, and **View on site** or the eye icon in the widget list shows the widget on your real site. Only logged-in administrators see the preview, even while the widget is disabled.

= Does it work with caching plugins and CDNs? =

Yes. The widget markup is the same for every visitor of a page. Opening hours, URL-parameter rules, country rules and scroll/delay reveals are applied in the visitor's browser, so cached pages stay correct. Page-based rules such as "only on the Contact page" are decided when the page is generated.

= How do I show WhatsApp instead of the phone outside business hours? =

Create a schedule, open the widget's **Scheduling** tab, choose the schedule and "Show after-hours actions", then set the phone action to "Only during opening hours" and WhatsApp to "Only outside opening hours".

= Why don't I see my own clicks in Analytics? =

Clicks by logged-in administrators are not counted by default, so testing doesn't skew your numbers. You can change this under **Settings → Analytics**.

= How do I track clicks in Google Analytics? =

If your site already uses Google Analytics 4 (gtag.js) or Google Tag Manager, each click is sent as the event `bigloop_contact_click` with the parameters `widget_id`, `action_id`, `action_type` and `link_text`. In GA4, mark the event as a key event to count it as a conversion. You can rename the event under **Settings → Analytics**.

= Country targeting doesn't work. Why? =

The plugin reads the visitor's country from a header that your host or CDN provides. It doesn't call outside services. On Cloudflare, enable "IP Geolocation". With other hosts, ask for the name of the country server variable (for example `GEOIP_COUNTRY_CODE`) and enter it under **Settings → Privacy & location**. Without a country header, country rules never match.

= Is my data deleted when I remove the plugin? =

Only if you choose to. Go to **Bigloop Contact → Settings → General** and enable "Remove all data when the plugin is deleted". Otherwise your widgets and statistics are kept so you can reinstall without losing anything.

== Changelog ==

= 0.6.1 =
* Code quality: database queries use WordPress identifier placeholders throughout, and the plugin now passes WordPress.org Plugin Check.

= 0.6.0 =
* Developers: new extension points (admin screens, click-tracking data from extensions, a wider public API). See docs/developers.md.
* Fixed: buttons from an add-on stayed on the site after the add-on was turned off.

= 0.5.1 =
* Fixed: on WordPress 6.7 and newer, translations were loaded too early, which WordPress reports as a notice in debug mode.
* The "buy us a coffee" link in the plugin's screens now points to bigloop.dev (the bigloop_cb/donate_url filter can change or remove it).

= 0.5.0 =
* Analytics dashboard: clicks over time, change versus the previous period, and breakdowns by action type, widget, action, device and page.
* Click statistics are stored locally without cookies or personal data. They are rolled up into daily totals every night, and detailed records are kept for a configurable number of days.
* Optional Google Analytics 4 / Google Tag Manager click events.
* Elementor widget.
* Suggested privacy-policy text.
* Review reminder shown only inside the plugin's screens, after it has proven useful.

= 0.4.0 =
* Schedules: weekly hours with several time ranges per day, overnight hours, holidays and closures, special opening hours, an optional active period and per-schedule timezones. Daylight-saving safe.
* Widgets can follow a schedule: hide outside opening hours, or show different actions (for example WhatsApp instead of the phone after hours).
* Schedules stay correct on cached pages: the browser checks the opening times, and re-checks every 30 seconds.
* Display rules: show widgets on chosen pages, posts, post types, categories and tags, special pages, URLs, logged-in or logged-out visitors and WooCommerce pages, matching all or any rules.
* Browser-checked rules that work with page caching: URL parameters (for example utm_source) and visitor country.
* Optional country targeting from your host's or Cloudflare's country header. No external service, nothing stored.
* Show a widget after scrolling or after a delay.

= 0.3.0 =
* Visual builder with a live preview on a sample site (desktop, tablet, mobile; light and dark backgrounds).
* Typography, hover and pressed colours, gradients, borders, outline/minimal/transparent styles, tooltip and label colours.
* Per-device sizes and positions (desktop, tablet, mobile).
* Effects: hover, entrance and attention animations, with an off switch; reduced motion respected.
* 10 design presets plus your own saved presets.
* Squircle uses native continuous corners where supported, and an exact superellipse outline elsewhere.

= 0.2.0 =
* Contact widgets: single floating button, expandable menu and contact bar.
* Reusable action library with 14 action types, validation and live link checking.
* Icon library (80 icons), colours, shapes including squircle, shadows, pulse animation.
* Desktop/mobile positions and desktop/tablet/mobile visibility.
* Widget manager: search, filter, sort, enable/disable, reorder, duplicate, delete, preview on site.
* Shortcode `[bigloop_contact]` and the Contact Widget block.

= 0.1.0 =
* Foundation: plugin bootstrap, service container, database migrations, settings, admin dashboard and design system.

== Third-party resources ==

* Lucide icons (https://lucide.dev), ISC License.
* Simple Icons (https://simpleicons.org), CC0 1.0. Brand icons are trademarks of their respective owners and are used only to identify the service each link opens.
