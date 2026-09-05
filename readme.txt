=== MB Cookie Consent ===
Contributors: marvikonvic
Tags: cookie consent, gdpr, script blocker, bilingual, google consent mode
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bilingual Serbian Latin/English cookie banner with cache-safe script and iframe blocking.

== Description ==

MB Cookie Consent provides:

* A main admin menu with Settings, Scanner, and Cookies and categories submenus.
* A grouped cookie inventory, category editing and manual cookie records.
* Informational server/HttpOnly records marked "Server control required"; no universal server-cookie blocking or deletion.

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
* A manual same-site audit scanner for new cookie names, scripts and iframes, with category suggestions and administrator-confirmed rule creation.

This plugin is a technical consent tool. Site owners remain responsible for auditing services, configuring every non-essential script/cookie and obtaining legal advice appropriate to their site and visitors.

== Privacy ==

MB Cookie Consent does not send telemetry, usage data or personal data to the plugin author or any external service. It stores the technically necessary `mbcc_consent` browser cookie to remember the visitor's choices. The plugin only blocks or releases scripts and external content configured by the site owner. Site owners remain responsible for documenting and lawfully configuring any third-party services used on their site.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate MB Cookie Consent.
3. Open MB Cookie Consent > Settings.
4. Add both privacy-policy URLs and review all blocking rules.
5. Test in a private browser window before using the plugin on production.

== Frequently Asked Questions ==

= Does it work with Blogsy? =

Yes. The frontend is independent of theme templates and all CSS is scoped to mbcc-prefixed classes. Test after clearing page, optimization, CDN and browser caches.

= Does it automatically discover every cookie? =

No. The plugin blocks configured handles and URL patterns. Dynamic scripts, proxy URLs, server-set cookies and scripts added after page load require a site-specific audit and rules.

= How does the manual scanner work? =

Open MB Cookie Consent > MB Cookie Scanner and start a scan. It checks up to 250 public same-site URLs in small batches, stores cookie names and resource patterns without cookie values, and asks an administrator to confirm a category before adding a rule. It does not run JavaScript and cannot guarantee discovery of third-party, HttpOnly or conditionally created cookies.

= How do I edit cookie categories? =

Open MB Cookie Consent > Cookies and categories. Edit a record to update its category, service/domain and optional linked script rule. A wildcard edit affects all matching names; conflicting overlapping patterns require review in Settings. Linked scripts keep their own categories. Server/HttpOnly entries only record your classification and do not create blocking/deletion rules. Cookies with the same name share one record. Clearing scan history retains these records and configured rules. No cookie values are stored.

= How do I manually block a script? =

Use inert markup, for example:

`<script type="text/plain" data-mbcc-category="analytics" data-mbcc-src="https://example.com/analytics.js"></script>`

Allowed category values are preferences, analytics and marketing.

= How do I add a settings button to a page? =

Use shortcode `[mbcc_cookie_settings]`.

== Screenshots ==

Screenshots show the 1.2.0 interface in a local WordPress demo. Scanner findings and inventory entries are illustrative demo data.

1. Consent banner with accept, reject and cookie-settings controls.
2. Granular cookie-settings dialog with four consent categories.
3. WordPress administration settings for language, appearance and consent behavior.
4. Cookie inventory grouped by category, including informational HttpOnly records.
5. Manual scanner with category suggestions and administrator review.
6. Expanded Edit form for a cookie record.
7. Serbian Latin translation of settings and the unified administration menu.

== Changelog ==

= 1.2.0 =
* Unified main admin menu with settings, scanner and cookie inventory submenus.
* Category editing, manual records, optional service-rule links and duplicate-rule prevention.
* Server and HttpOnly metadata marked as requiring server control; informational records only.
* Capability, nonce, stale-form and overlapping-rule checks for inventory edits.
* Serbian Latin translations and bilingual README updated; includes the 1.1.1 fixes below.

= 1.1.1 =

* Fix false blocking markers and text-element parser bypasses.
* Share parsing with the scanner and preserve ports in resource rules.
* Retain findings after failed rule writes and display failed scan URLs.

= 1.1.0 =

* Fix quoted tag boundaries and quoted/unquoted script and iframe attributes, preserving module type and source after consent.
* Add a manually started, same-site cookie and resource audit scanner.
* Show only new findings and suggest necessary, preferences, analytics or marketing categories.
* Require administrator confirmation before adding a cookie, script or iframe rule.
* Store no cookie values and add no scheduled scans or notifications.

= 1.0.7 =

* Correct the Serbian description of the necessary category when visitors are allowed to disable it.
* Upgrade only the exact obsolete stock description on existing installations; customized text remains unchanged.

= 1.0.6 =

* Prepare WordPress.org metadata and assets.
* Add Serbian Latin translation files and a translation template.
* Document plugin privacy behavior.

= 1.0.5 =

* Block Google Tag and Google Site Kit event scripts as analytics by default until consent.
* Add only missing stock rules once on existing installations, preserving custom categories, settings and consent records.
* Keep the Google Ads AW pattern classified as marketing.

= 1.0.4 =

* Initialize the frontend after DOM parsing, fixing the floating settings control without script-delay plugins.
* Keep the plugin's own runtime and inline configuration executable even when custom blocking rules match them.
* Preserve existing settings and consent records; includes the 1.0.3 cookie-domain and autoload fixes.

= 1.0.3 =

* Fixed cookie removal on parent domains such as primer.co.rs, primer.com.rs and example.co.uk, including nested subdomains.
* Enabled settings autoload for new installations; existing installations are updated on the next administrator dashboard request without changing saved settings.

= 1.0.2 =

* Added four selectable banner layouts with top/bottom placement.

= 1.0.1 =

* Added an enabled-by-default visitor switch for the necessary category.
* Reject optional keeps necessary enabled; visitors may disable it manually.

= 1.0.0 =

* Initial release.
