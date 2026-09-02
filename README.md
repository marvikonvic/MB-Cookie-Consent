# MB Cookie Consent 1.0.2

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
- izolovan CSS koji ne menja Blogsy elemente i raspored.

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
- isolated CSS that does not alter Blogsy layout elements.

### Important limitations

This release does not crawl the whole site. HttpOnly/server cookies, dynamically injected scripts, proxied resources and optimization-plugin rewrites require a site-specific audit. A technical plugin is not a legal guarantee of compliance.

The `mbcc_consent` preference cookie remains technically exempt so the visitor's selection can persist. Disabling genuinely necessary cookies may break login, security, cart or other essential site functions.
