'use strict';
// Execute the actual PHP-rendered early reader and production JS functions.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const rendered = execFileSync(php, ['-r', `define('ABSPATH', '.'); function wp_json_encode($v){return json_encode($v);} require 'includes/class-mbcc-frontend.php'; (new MBCC_Frontend(array('consent_version'=>'1.0','google_consent_mode'=>true)))->render_consent_mode();`], {cwd: root, encoding: 'utf8'});
const early = rendered.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const source = fs.readFileSync(path.join(root, 'assets/js/frontend.js'), 'utf8');
function productionFunction(name) {
    return source.match(new RegExp('    function ' + name + '\\([^]*?\\n    \\}'))[0];
}
const valid = {version: '1.0', necessary: true, preferences: false, analytics: true, marketing: false};
const cases = [valid, {...valid, necessary: false}, {...valid, version: 'old'}, null, [], 1, 'bad',
    {version: '1.0'}, ...['false', 1, null, {}, []].map(v => ({...valid, analytics: v}))];
for (const consent of cases) {
    let shownValid = false;
    const window = {};
    const document = {cookie: 'mbcc_consent=' + Buffer.from(JSON.stringify(consent)).toString('base64url'),
        documentElement: {classList: {add() { shownValid = true; }}}};
    const context = {window, document, config: {version: '1.0'}, atob: s => Buffer.from(s, 'base64').toString()};
    Object.defineProperty(context, 'dataLayer', {get: () => window.dataLayer});
    vm.createContext(context);
    vm.runInContext(productionFunction('validConsent'), context);
    const expected = context.validConsent(consent);
    vm.runInContext(early, context);
    assert.equal(shownValid, expected);
    const result = window.dataLayer[0][2];
    assert.equal(result.analytics_storage, expected && consent.analytics ? 'granted' : 'denied');
    assert.equal(result.ad_storage, expected && consent.marketing ? 'granted' : 'denied');
}

(async () => {
    for (const [value, expected] of [
        ['https://cdn.example/a.js', 'https://cdn.example/a.js'],
        ['http://cdn.example/a.js', 'http://cdn.example/a.js'],
        ['/a.js', 'https://example.com/a.js'],
        ['a.js', 'https://example.com/demo/a.js'],
        ['//cdn.example/a.js', 'https://cdn.example/a.js'],
        ...['', ' ', 'javascript:alert(1)', ' JaVaScRiPt:alert(1)', 'java\nscript:alert(1)',
            'data:text/html,<script>alert(1)</script>', 'blob:https://example.com/id', 'ftp://example.com/a', 'https://['].map(v => [v, null])
    ]) {
        let replaced = false;
        let removed = false;
        let loaded;
        const attributes = {'data-mbcc-src': value, 'data-mbcc-blocked': '1'};
        const element = {attributes: [], textContent: 'must not become inline fallback',
            getAttribute(k) { return Object.hasOwn(attributes, k) ? attributes[k] : null; },
            setAttribute(k, v) { if (k === 'src') loaded = v; },
            removeAttribute(k) { delete attributes[k]; },
            previousElementSibling: {classList: {contains: () => true}, remove() { removed = true; }},
            parentNode: {replaceChild(script) { replaced = true; loaded = script.src; script.onload(); }}
        };
        const context = {URL, document: {baseURI: 'https://example.com/demo/',
            createElement() { return {setAttribute(k, v) { this[k] = v; }}; }}};
        vm.createContext(context);
        vm.runInContext(['resourceURL', 'activateScript', 'activateIframe'].map(productionFunction).join('\n'), context);
        assert.equal(context.resourceURL(value), expected);
        await context.activateScript(element);
        assert.equal(replaced, expected !== null);
        assert.equal(loaded, expected === null ? undefined : expected);
        context.activateIframe(element);
        assert.equal(removed, expected !== null);
        if (expected === null) assert.equal(attributes['data-mbcc-blocked'], '1');
    }
    console.log('PASS: script/iframe URL protocol validation and retained blocked placeholders');
    for (const event of ['onload', 'onerror']) {
        const inserted = [];
        const scripts = [0, 1].map(index => ({
            attributes: [], textContent: '/* module fixture */',
            getAttribute(name) { return name === 'data-mbcc-type' ? 'module' : name === 'data-mbcc-category' ? 'analytics' : null; },
            parentNode: {replaceChild(script) { inserted.push({index, script}); }}
        }));
        const context = {document: {
            createElement() { return {setAttribute(k, v) { this[k] = v; }}; },
            querySelectorAll(selector) { return selector.startsWith('script') ? scripts : []; }
        }};
        vm.createContext(context);
        vm.runInContext(productionFunction('resourceURL') + '\n' + productionFunction('activateScript') + '\n' + productionFunction('activateAllowed'), context);
        const done = context.activateAllowed({analytics: true});
        await Promise.resolve();
        assert.equal(inserted.length, 1, 'Second module must wait');
        assert.equal(inserted[0].script.type, 'module');
        inserted[0].script[event]();
        for (let i = 0; i < 8; i++) await Promise.resolve();
        assert.equal(inserted.length, 2, 'Chain proceeds after load/error');
        inserted[1].script.onload();
        await done;
    }
    console.log('PASS: early/frontend reader parity and inline-module load/error ordering');
})().catch(error => { console.error(error); process.exitCode = 1; });
