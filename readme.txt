=== MB Cookie Consent ===
Contributors: mb
Tags: cookie consent, gdpr, script blocker, bilingual, google consent mode
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bilingual Serbian Latin/English cookie banner with cache-safe script and iframe blocking.

== Description ==

MB Cookie Consent provides:

* Serbian Latin and English frontend text.
* Automatic language detection for WordPress, Polylang and WPML.
* Necessary, preferences, analytics and marketing categories.
* Necessary is enabled by default and can optionally be disabled in granular settings.
* Accept all, reject optional and granular settings.
* Blocking by WordPress script handle and external URL pattern.
* External iframe placeholders for services such as YouTube and Google Maps.
* Google Consent Mode v2 denied defaults.
* Four banner layouts: full-width bar and floating cards aligned left, right or centre.
* A persistent control for changing or withdrawing consent.
* Theme-isolated CSS suitable for standard, classic and Blogsy frontends.

This plugin is a technical consent tool. Site owners remain responsible for auditing services, configuring every non-essential script/cookie and obtaining legal advice appropriate to their site and visitors.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate MB Cookie Consent.
3. Open Settings > MB Cookie Consent.
4. Add both privacy-policy URLs and review all blocking rules.
5. Test in a private browser window before using the plugin on production.

== Frequently Asked Questions ==

= Does it work with Blogsy? =

Yes. The frontend is independent of theme templates and all CSS is scoped to mbcc-prefixed classes. Test after clearing page, optimization, CDN and browser caches.

= Does it automatically discover every cookie? =

No. Version 1.0 blocks configured handles and URL patterns. Dynamic scripts, proxy URLs, server-set cookies and scripts added after page load require a site-specific audit and rules.

= How do I manually block a script? =

Use inert markup, for example:

`<script type="text/plain" data-mbcc-category="analytics" data-mbcc-src="https://example.com/analytics.js"></script>`

Allowed category values are preferences, analytics and marketing.

= How do I add a settings button to a page? =

Use shortcode `[mbcc_cookie_settings]`.

== Changelog ==

= 1.0.2 =

* Added four selectable banner layouts with top/bottom placement.

= 1.0.1 =

* Added an enabled-by-default visitor switch for the necessary category.
* Reject optional keeps necessary enabled; visitors may disable it manually.

= 1.0.0 =

* Initial release.
