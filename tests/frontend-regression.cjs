'use strict';
// Full production script with a minimal DOM double; not a WordPress/browser E2E test.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/frontend.js'), 'utf8');

async function run(readyState, allowed, missingConfig = false) {
    let markupReady = readyState !== 'loading';
    let activated = 0;
    let reloads = 0;
    let focusCount = 0;
    const handlers = {};
    const classes = new Set();
    const classList = {add: name => classes.add(name), remove: name => classes.delete(name)};
    const dialog = {focus: () => { focusCount++; }};
    const modal = {hidden: true, querySelector: () => dialog};
    const banner = {hidden: false};
    const inputs = Object.fromEntries(['necessary', 'preferences', 'analytics', 'marketing'].map(k => [k, {checked: false}]));
    const oldScript = {
        attributes: [{name: 'id', value: 'pll_cookie_script-js-after'}, {name: 'type', value: 'text/plain'},
            {name: 'data-mbcc-blocked', value: '1'}, {name: 'data-mbcc-category', value: 'preferences'}],
        getAttribute(name) { return this.attributes.find(a => a.name === name)?.value || null; },
        textContent: '/* Polylang fixture */',
        parentNode: {replaceChild(script) {
            activated++;
            assert.equal(script.id, 'pll_cookie_script-js-after');
            assert.equal(script['data-mbcc-blocked'], undefined);
        }}
    };
    let cookie = 'mbcc_consent=' + Buffer.from(JSON.stringify({version: '1.0', necessary: allowed,
        preferences: allowed, analytics: allowed, marketing: allowed})).toString('base64url');
    const document = {
        readyState,
        get cookie() { return cookie; },
        set cookie(value) { cookie = value.split(';')[0]; },
        getElementById(id) {
            if (!markupReady) return null;
            return id === 'mbcc-modal' ? modal : id === 'mbcc-banner' ? banner : inputs[id.replace('mbcc-', '')] || null;
        },
        querySelector(selector) { return markupReady ? inputs[/="(\w+)"/.exec(selector)?.[1]] || null : null; },
        querySelectorAll(selector) { return markupReady && selector === 'script[data-mbcc-blocked="1"]' ? [oldScript] : []; },
        createElement() { return {setAttribute(name, value) { this[name] = value; }}; },
        addEventListener(name, fn, options) { (handlers[name] ||= []).push({fn, options}); },
        dispatchEvent() {},
        documentElement: {classList}, body: {classList}, activeElement: {focus() {}}
    };
    const window = {
        MBCC_CONFIG: missingConfig ? undefined : {cookieName: 'mbcc_consent', version: '1.0', cookiePatterns: []},
        atob: s => Buffer.from(s, 'base64').toString('binary'),
        btoa: s => Buffer.from(s, 'binary').toString('base64'),
        location: {protocol: 'https:', reload() { reloads++; }}
    };
    vm.runInNewContext(source, {window, document, CustomEvent: function () {} });
    if (readyState === 'loading') {
        markupReady = true;
        document.readyState = 'interactive';
        for (const {fn, options} of handlers.DOMContentLoaded || []) {
            assert.equal(options.once, true);
            fn();
        }
    }
    if (missingConfig) {
        assert.equal(handlers.click, undefined);
        return;
    }
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(activated, allowed ? 1 : 0, 'Only explicitly allowed preferences may activate');
    assert.equal(handlers.click.length, 1);
    const click = selector => handlers.click[0].fn({preventDefault() {}, target: {closest: s => s === selector ? {} : null}});
    click('[data-mbcc-open]');
    assert.equal(modal.hidden, false, readyState + ': floating control must open modal');
    assert.equal(inputs.preferences.checked, allowed);
    click('[data-mbcc-close]');
    assert.equal(modal.hidden, true);
    click('[data-mbcc-open]');
    assert.equal(modal.hidden, false, 'Reopen must work');
    assert.equal(focusCount, 2);
    for (const input of Object.values(inputs)) input.checked = false;
    click('[data-mbcc-save]');
    const saved = JSON.parse(Buffer.from(decodeURIComponent(cookie.split('=')[1]), 'base64url').toString());
    for (const key of Object.keys(inputs)) assert.equal(saved[key], false);
    assert.equal(reloads, 1);
    assert.equal(modal.hidden, true);
    console.log('PASS:', readyState, 'preferences=' + allowed, 'open/close/reopen/save');
}
(async () => {
    for (const state of ['loading', 'interactive', 'complete']) {
        for (const allowed of [false, true]) await run(state, allowed);
    }
    await run('loading', false, true);
    console.log('PASS: missing configuration exits safely');
})().catch(error => { console.error(error); process.exitCode = 1; });
