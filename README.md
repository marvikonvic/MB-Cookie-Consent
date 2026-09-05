![MB Cookie Consent banner](.wordpress-org/banner-1544x500.png)

# MB Cookie Consent 1.1.0

## English

Standalone WordPress plugin providing a bilingual cookie banner and prior blocking of optional scripts. It is designed for standard and classic WordPress frontends.

### Features

- Serbian Latin and English;
- automatic WordPress, Polylang and WPML language detection;
- necessary, preferences, analytics and marketing categories;
- necessary is selected by default but can be disabled manually by the visitor;
- accept all, reject optional and granular choices;
- blocking by WordPress script handle and URL pattern;
- placeholders for YouTube, Vimeo and Google Maps;
- Google Consent Mode v2;
- four layouts: full-width bar or a floating card aligned left, right or centre;
- consent withdrawal/change control;
- `[mbcc_cookie_settings]` shortcode.
- manually started same-site scanner for new cookie names, scripts and iframes;
- conservative category suggestions with administrator-confirmed rule creation.

### Installation and configuration

1. In WordPress Admin, open **Plugins → Add New → Upload Plugin**.
2. Select the ZIP, install it and activate the plugin.
3. Open **Settings → MB Cookie Consent**.
4. Enter the Serbian and English privacy-policy URLs.
5. Review the script handle, URL, iframe and cookie rules.
6. Change `Consent version` when a policy update requires renewed consent.
7. Clear the page/optimization cache, CDN/Cloudflare cache and browser cache, then test in a private window.

### Manual blocking

```html
<script
    type="text/plain"
    data-mbcc-category="analytics"
    data-mbcc-src="https://example.com/analytics.js"
></script>
```

Allowed categories are `preferences`, `analytics` and `marketing`.

### Important limitations

- The plugin does not automatically crawl the whole site.
- HttpOnly and server-side cookies cannot be removed by JavaScript.
- The `mbcc_consent` cookie remains technically exempt so the visitor's selection can persist between pages.
- Disabling genuinely necessary cookies may break login, security, cart or other essential site functions.
- Dynamically injected scripts and proxied resources require a dedicated rule or integration change.
- JavaScript minification or delay can change handles and URLs, so rules should be retested after enabling optimization.
- A technical plugin is not a legal guarantee of compliance.
- The manual scanner does not execute JavaScript and cannot guarantee discovery of third-party, HttpOnly or conditionally created cookies.

## Srpski (latinica)

Samostalan WordPress dodatak za dvojezični baner za kolačiće i blokiranje opcionih skripti pre saglasnosti. Namenjen je standardnim i klasičnim WordPress frontendima.

### Funkcije

- srpski latinica i engleski;
- automatska detekcija WordPress, Polylang i WPML jezika;
- kategorije: neophodni, podešavanja, analitika i marketing;
- neophodni su podrazumevano uključeni, ali ih posetilac može ručno isključiti;
- prihvatanje svih, odbijanje opcionih i granularan izbor;
- blokiranje preko WordPress script handle-a i URL obrasca;
- YouTube, Vimeo i Google Maps placeholderi;
- Google Consent Mode v2;
- izbor izgleda: puna traka, plutajuća kartica levo, desno ili u sredini;
- povlačenje ili promena saglasnosti;
- shortcode `[mbcc_cookie_settings]`.
- ručno pokretanje skenera javnih URL-ova za nove nazive kolačića, skripte i iframe-ove;
- predlozi kategorija uz obaveznu potvrdu administratora pre dodavanja pravila.

### Instalacija i podešavanje

1. U WordPress Adminu otvorite **Plugins → Add New → Upload Plugin**.
2. Izaberite ZIP, instalirajte i aktivirajte dodatak.
3. Otvorite **Settings → MB Cookie Consent**.
4. Unesite srpski i engleski URL politike privatnosti.
5. Pregledajte script handle, URL, iframe i cookie pravila.
6. Promenite `Consent version` kada izmena politike zahteva novu saglasnost.
7. Očistite page/optimization cache, CDN/Cloudflare i browser cache, pa testirajte u privatnom prozoru.

### Ručno blokiranje

```html
<script
    type="text/plain"
    data-mbcc-category="analytics"
    data-mbcc-src="https://example.com/analytics.js"
></script>
```

Dozvoljene kategorije su `preferences`, `analytics` i `marketing`.

### Važna ograničenja

- Dodatak ne skenira automatski ceo sajt.
- HttpOnly i server-side kolačići ne mogu se ukloniti JavaScriptom.
- Kolačić `mbcc_consent`, koji pamti izbor posetioca, ostaje tehnički izuzet; bez njega izbor ne može da se sačuva između stranica.
- Isključivanje stvarno neophodnih kolačića može prekinuti prijavu, bezbednost, korpu ili druge osnovne funkcije.
- Dinamički ubačene skripte i proxy URL-ovi zahtevaju posebno pravilo ili izmenu integracije.
- Minifikacija ili odlaganje JavaScripta može promeniti handle ili URL, pa pravila treba proveriti nakon uključivanja optimizacije.
- Tehnički dodatak ne predstavlja pravnu garanciju usklađenosti.
- Ručni skener ne izvršava JavaScript i ne može garantovati otkrivanje third-party, HttpOnly ili uslovno napravljenih kolačića.

## Screenshots

### Consent banner

![English consent banner](.wordpress-org/screenshot-1.png)

### Granular cookie settings

![English granular cookie settings](.wordpress-org/screenshot-2.png)

### Administration settings in English

![WordPress administration settings in English](.wordpress-org/screenshot-3.png)

### Administratorska podešavanja na srpskom

![WordPress administratorska podešavanja na srpskom](.wordpress-org/screenshot-4.png)

## Changelog

### 1.1.0 — Manual cookie audit / ručni pregled kolačića

- Fixes quoted tag boundaries and quoted/unquoted script and iframe attributes; preserves module type and source through consent activation.
- Adds a manually started scanner for up to 250 public URLs on the site's own domain, processed in small batches.
- Records cookie names and normalized script/iframe patterns without storing cookie values.
- Suggests a category but requires an administrator to confirm it before adding a rule.
- Includes no scheduled scanning, browser automation or notifications.

### 1.0.7 — Necessary-cookie description / opis neophodnih kolačića

- EN: Replaces the obsolete “cannot be disabled” wording with an explanation matching the configurable necessary-category switch.
- SR: Zastareli tekst „Ne mogu se isključiti” zamenjen je jasnim objašnjenjem da su neophodni kolačići podrazumevano uključeni, ali da ih posetilac može isključiti uz moguć gubitak pojedinih funkcija sajta.
- EN/SR: Existing installations update only the exact legacy stock text; customized content remains untouched. / Na postojećim instalacijama menja se samo identičan stari podrazumevani tekst; prilagođeni sadržaj ostaje netaknut.

### 1.0.6 — WordPress.org preparation / priprema

- EN: WordPress.org metadata, privacy documentation and directory listing assets are prepared.
- SR: Ažurirani su WordPress.org metapodaci, dokumentacija privatnosti i listing resursi.
- EN: A POT catalogue and a complete `sr_RS_latin` WordPress Admin translation are included. English remains the source language.
- SR: Dodat je POT katalog i kompletan prevod WordPress Admin interfejsa za `sr_RS_latin`. Engleski ostaje izvorni jezik.

### 1.0.5 — Google Site Kit defaults / podrazumevana pravila

- Google Tag (`googletagmanager.com/gtag/js`) and the Google Site Kit events provider are blocked as analytics by default until consent.
- Existing installations receive only missing rules once. Custom rules and categories, other settings and existing consent records remain unchanged.
- Google Ads `AW-` patterns remain classified as marketing.

### 1.0.4 — Frontend fixes / ispravke

- Initialization waits for DOM readiness so the floating settings control works without a script-delay plugin; scripts loaded after DOM readiness initialize immediately.
- The consent runtime and its inline configuration (`MBCC_CONFIG`) are exempt from MBCC blocking, including accidental matches from custom rules.
- Existing settings and consent records remain unchanged. Includes all 1.0.3 fixes.

#### Polylang and optimization plugins

- WordPress script handle: `pll_cookie_script|preferences`
- Cookie removed after rejection: `pll_language|preferences`
- Do not add the Polylang handle to Script URL patterns. URL patterns also search inline script contents.
- Test without WP Meteor or other optimizers first: they may reactivate blocked scripts. This release does not guarantee compatibility with rewritten HTML.
- Clear page, CDN and browser caches after updating.

### 1.0.3 — Cookie removal and settings autoload

- Cookie removal attempts all parent domains, including multipart suffixes and nested subdomains. The browser rejects public-suffix attempts; no manually maintained TLD list is used.
- `mbcc_settings` is autoloaded. Existing installations are migrated on the next administrator dashboard request without changing saved settings or consent records.
- Removal remains limited to configured, JavaScript-accessible cookies at path `/`. HttpOnly cookies, other paths and unrelated third-party domains are outside this fix.

## Verification

The plugin has been tested in staging and production environments. The following checks were performed:

### Staging and production

- first visit in a private browser window, including opening the initial banner and granular cookie-settings dialog;
- accepting all, rejecting optional cookies and saving individual category choices;
- persistence of consent after page reload and reopening the dialog from the floating settings button;
- Serbian Latin and English frontend presentation, plus the Serbian Latin WordPress Admin translation;
- rejection and removal of the Polylang `pll_language` preference cookie while retaining only the technically required `mbcc_consent` cookie;
- blocking Google Tag and Google Site Kit analytics scripts before analytics consent, then releasing them after analytics is allowed;
- operation with Cloudflare and Super Page Cache after clearing page, CDN and browser caches;
- the banner, granular dialog and floating settings control on the production Blogsy frontend.

Browser tracking protection may independently block analytics requests even after consent; that browser behavior is separate from the plugin's consent decision.

### Automated developer checks

- PHP syntax validation for every shipped PHP file;
- `php tests/blocker-regression.php` for runtime/configuration protection, Polylang blocking, quoted/unquoted attributes, quoted `>`, duplicate attributes, module metadata and idempotency;
- `php tests/settings-rules-regression.php` for default-rule precedence, preservation of custom settings, the 1.0.7 text migration, administrator authorization and migration idempotency;
- `php tests/scanner-regression.php` for cookie-name extraction, resource normalization and conservative category suggestions;
- `node tests/frontend-regression.cjs` for loading, interactive and complete document states, open/close/reopen/save flows, and external module type/source restoration with consent allowed and denied;
- JavaScript syntax validation for `assets/js/frontend.js`;
- activation and settings-page runtime checks on WordPress 6.4 with PHP 7.4 and WordPress 7.1 with PHP 8.1;
- WordPress Plugin Check with all error and warning categories enabled;
- release ZIP inspection for version metadata, expected root directory and exclusion of repository/development-only files.

The tests under `tests/` use standalone WordPress/DOM doubles and are excluded from the installation ZIP. The manual scanner still requires a staging test against the site's real public URLs before version 1.1.0 is deployed to production.
