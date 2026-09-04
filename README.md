# MB Cookie Consent 1.0.5

## 1.0.5 — Google Site Kit podrazumevana pravila / Google Site Kit defaults

- SR: Google Tag (`googletagmanager.com/gtag/js`) i Google Site Kit events provider sada su podrazumevano blokirani kao analitika do pristanka.
- EN: Google Tag (`googletagmanager.com/gtag/js`) and the Google Site Kit events provider are now blocked as analytics by default until consent.
- SR: Postojeće instalacije jednokratno dobijaju samo nedostajuća pravila. Ručna pravila, njihove kategorije, druga podešavanja i postojeće saglasnosti se ne menjaju.
- EN: Existing installations receive only missing rules once. Custom rules and categories, other settings, and existing consent records remain unchanged.
- SR/EN: Google Ads `AW-` obrazac ostaje marketing / The Google Ads `AW-` pattern remains marketing.
- SR/EN: Posle ažuriranja očistiti page/CDN/browser keš / Clear page, CDN and browser caches after updating.

## 1.0.4 — Ispravke / Fixes

- SR: Inicijalizacija čeka da DOM bude spreman, pa plutajuće dugme otvara podešavanja i bez odlaganja JavaScripta. Ako je DOM već spreman, inicijalizacija počinje odmah.
- EN: Initialization waits for DOM readiness so the floating settings control works without a script-delay plugin; scripts loaded after DOM readiness initialize immediately.
- SR: Sopstvena frontend skripta i njena inline konfiguracija (`MBCC_CONFIG`) izuzete su iz MBCC blokiranja, čak i kada ih pogodi prilagođeno pravilo.
- EN: The consent runtime and its inline configuration are exempt from MBCC blocking, including accidental matches from custom rules.
- SR/EN: Postojeća podešavanja i saglasnosti se čuvaju / Existing settings and consent records are preserved. Includes all 1.0.3 fixes.

### Polylang / Optimizatori / Optimizers

- WordPress script handles: `pll_cookie_script|preferences`
- Cookies to remove after rejection: `pll_language|preferences`
- SR: Polylang handle ne dodavati u Script URL patterns. URL obrasci pretražuju i sadržaj inline skripti. Testirati najpre bez WP Meteora i drugih optimizatora: oni mogu ponovo aktivirati blokirane skripte. Ovo izdanje ne garantuje kompatibilnost sa njihovim prepisivanjem HTML-a.
- EN: Do not add the Polylang handle to Script URL patterns. URL patterns also search inline script contents. Test without WP Meteor or other optimizers first: they may reactivate blocked scripts. This release does not guarantee compatibility with their HTML rewriting.
- SR/EN: Posle ažuriranja očistiti page/CDN/browser keš / Clear page, CDN and browser caches after updating.

### Developer verification

Run `php tests/blocker-regression.php` and `node tests/frontend-regression.cjs` from the repository root. These are standalone behavioral tests with WordPress/DOM doubles, not WordPress runtime or production certification. Tests are excluded from the installation ZIP.

## 1.0.3 — Ispravke / Fixes

- SR: Brisanje kolačića sada pokušava sve roditeljske domene, uključujući `.primer.co.rs`, `.primer.com.rs` i dublje poddomene. Browser odbija pokušaje na public suffix domenima; nije ugrađena niti potrebna ručno održavana lista TLD-ova.
- SR: `mbcc_settings` koristi autoload. Za postojeće instalacije promena se primenjuje pri sledećem otvaranju Admina korisnikom sa `manage_options`, bez menjanja sadržaja podešavanja. Postojeće saglasnosti ostaju važeće.
- EN: Cookie removal now tries all parent domains, including multipart suffixes and nested subdomains. The browser rejects public-suffix attempts; no manually maintained TLD list is used.
- EN: Settings are autoloaded on new installs. Existing installs are migrated on the next administrator dashboard request without altering saved settings or invalidating consent.
- Scope: removal remains limited to configured, JavaScript-accessible cookies at path `/`. HttpOnly cookies, other paths and cookies on unrelated third-party domains are outside this fix.

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
- shortcode `[mbcc_cookie_settings]`;

### Instalacija i podešavanje

1. U WordPress Adminu otvorite **Plugins → Add New → Upload Plugin**.
2. Izaberite ZIP, instalirajte i aktivirajte dodatak.
3. Otvorite **Settings → MB Cookie Consent**.
4. Unesite srpski i engleski URL politike privatnosti.
5. Pregledajte script handle, URL, iframe i cookie pravila.
6. Promenite `Consent version` kada izmena politike zahteva novu saglasnost.
7. Očistite Autoptimize/page cache, CDN/Cloudflare i browser cache, pa testirajte u privatnom prozoru.

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
- Minifikacija/odlaganje JavaScripta može promeniti handle ili URL, pa pravila treba proveriti nakon uključivanja optimizacije.
- Tehnički dodatak ne predstavlja pravnu garanciju usklađenosti.

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
- `[mbcc_cookie_settings]` shortcode;

### Important limitations

This release does not crawl the whole site. HttpOnly/server cookies, dynamically injected scripts, proxied resources and optimization-plugin rewrites require a site-specific audit. A technical plugin is not a legal guarantee of compliance.

The `mbcc_consent` preference cookie remains technically exempt so the visitor's selection can persist. Disabling genuinely necessary cookies may break login, security, cart or other essential site functions.
