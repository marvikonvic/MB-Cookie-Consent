(function () {
    'use strict';

    var config = window.MBCC_CONFIG;
    if (!config) {
        return;
    }

    var banner = document.getElementById('mbcc-banner');
    var modal = document.getElementById('mbcc-modal');
    var dialog = modal ? modal.querySelector('.mbcc-modal__dialog') : null;
    var lastFocus = null;

    function readCookie() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + config.cookieName.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
        if (!match) {
            return null;
        }
        try {
            var encoded = decodeURIComponent(match[1]).replace(/-/g, '+').replace(/_/g, '/');
            var consent = JSON.parse(window.atob(encoded));
            if (typeof consent.necessary !== 'boolean') {
                consent.necessary = true;
            }
            return consent;
        } catch (error) {
            return null;
        }
    }

    function validConsent(consent) {
        return consent && consent.version === String(config.version);
    }

    function encodeConsent(consent) {
        return window.btoa(JSON.stringify(consent)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function writeConsent(consent) {
        var maxAge = Math.max(1, Number(config.durationDays) || 180) * 86400;
        var cookie = config.cookieName + '=' + encodeURIComponent(encodeConsent(consent)) + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
        if (window.location.protocol === 'https:') {
            cookie += ';Secure';
        }
        document.cookie = cookie;
    }

    function consentObject(necessary, preferences, analytics, marketing) {
		return {
			version: String(config.version),
			necessary: Boolean(necessary),
            preferences: Boolean(preferences),
            analytics: Boolean(analytics),
            marketing: Boolean(marketing),
            updated: new Date().toISOString()
        };
    }

    function updateGoogleConsent(consent) {
        if (typeof window.gtag !== 'function') {
            return;
        }
        window.gtag('consent', 'update', {
            ad_storage: consent.marketing ? 'granted' : 'denied',
            analytics_storage: consent.analytics ? 'granted' : 'denied',
            ad_user_data: consent.marketing ? 'granted' : 'denied',
            ad_personalization: consent.marketing ? 'granted' : 'denied',
            functionality_storage: consent.preferences ? 'granted' : 'denied',
            personalization_storage: consent.preferences ? 'granted' : 'denied',
			security_storage: consent.necessary ? 'granted' : 'denied'
        });
    }

    function nameMatches(name, pattern) {
        return pattern.slice(-1) === '*' ? name.indexOf(pattern.slice(0, -1)) === 0 : name === pattern;
    }

    function expireCookie(name) {
        var hostname = window.location.hostname;
        var domains = ['', hostname, '.' + hostname];
        var parts = hostname.split('.');
        if (parts.length > 2) {
            domains.push('.' + parts.slice(-2).join('.'));
        }
        domains.forEach(function (domain) {
            var value = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
            if (domain) {
                value += ';domain=' + domain;
            }
            document.cookie = value;
        });
    }

    function removeRejectedCookies(consent) {
        var existing = document.cookie.split(';').map(function (item) {
            return item.split('=')[0].trim();
        });
        (config.cookiePatterns || []).forEach(function (rule) {
            if (consent[rule.category]) {
                return;
            }
            existing.forEach(function (name) {
                if (nameMatches(name, rule.name)) {
                    expireCookie(name);
                }
            });
        });
    }

    function activateIframe(iframe) {
        var source = iframe.getAttribute('data-mbcc-src');
        if (source) {
            iframe.setAttribute('src', source);
            iframe.removeAttribute('data-mbcc-src');
        }
        iframe.removeAttribute('data-mbcc-blocked');
        var placeholder = iframe.previousElementSibling;
        if (placeholder && placeholder.classList.contains('mbcc-placeholder')) {
            placeholder.remove();
        }
    }

    function activateScript(oldScript) {
        return new Promise(function (resolve) {
            var script = document.createElement('script');
            Array.prototype.slice.call(oldScript.attributes).forEach(function (attribute) {
                if (['type', 'data-mbcc-src', 'data-mbcc-type', 'data-mbcc-blocked', 'data-mbcc-category'].indexOf(attribute.name) === -1 && attribute.name !== 'async' && attribute.name !== 'defer') {
                    script.setAttribute(attribute.name, attribute.value);
                }
            });
            var originalType = oldScript.getAttribute('data-mbcc-type');
            if (originalType) {
                script.setAttribute('type', originalType);
            }
            var source = oldScript.getAttribute('data-mbcc-src');
            if (source) {
                script.src = source;
                script.async = false;
                script.onload = resolve;
                script.onerror = resolve;
            } else {
                script.text = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(script, oldScript);
            if (!source) {
                resolve();
            }
        });
    }

    function activateAllowed(consent) {
        var chain = Promise.resolve();
        document.querySelectorAll('script[data-mbcc-blocked="1"]').forEach(function (script) {
            var category = script.getAttribute('data-mbcc-category');
            if (consent[category]) {
                chain = chain.then(function () { return activateScript(script); });
            }
        });
        document.querySelectorAll('iframe[data-mbcc-blocked="1"]').forEach(function (iframe) {
            if (consent[iframe.getAttribute('data-mbcc-category')]) {
                activateIframe(iframe);
            }
        });
        return chain;
    }

    function addIframePlaceholders(consent) {
        var template = document.getElementById('mbcc-placeholder-template');
        if (!template) {
            return;
        }
        document.querySelectorAll('iframe[data-mbcc-blocked="1"]').forEach(function (iframe) {
            var category = iframe.getAttribute('data-mbcc-category');
            if (consent && consent[category]) {
                return;
            }
            if (!iframe.previousElementSibling || !iframe.previousElementSibling.classList.contains('mbcc-placeholder')) {
                iframe.parentNode.insertBefore(template.content.cloneNode(true), iframe);
            }
        });
    }

    function setInputs(consent) {
		['necessary', 'preferences', 'analytics', 'marketing'].forEach(function (category) {
            var input = document.querySelector('[data-mbcc-category-input="' + category + '"]');
            if (input) {
                input.checked = category === 'necessary' && !validConsent(consent) ? true : Boolean(consent && consent[category]);
            }
        });
    }

    function openModal() {
        if (!modal) {
            return;
        }
        lastFocus = document.activeElement;
        setInputs(readCookie());
        modal.hidden = false;
        document.body.classList.add('mbcc-modal-open');
        if (dialog) {
            dialog.focus();
        }
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('mbcc-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function save(consent) {
        writeConsent(consent);
        updateGoogleConsent(consent);
        removeRejectedCookies(consent);
        document.documentElement.classList.add('mbcc-consent-valid');
        if (banner) {
            banner.hidden = true;
        }
        closeModal();
        document.dispatchEvent(new CustomEvent('mbcc:consent-changed', { detail: consent }));
        window.location.reload();
    }

    document.addEventListener('click', function (event) {
        var open = event.target.closest('[data-mbcc-open]');
        if (open) {
            event.preventDefault();
            openModal();
            return;
        }
        if (event.target.closest('[data-mbcc-close]')) {
            closeModal();
            return;
        }
        if (event.target.closest('[data-mbcc-accept-all]')) {
			save(consentObject(true, true, true, true));
            return;
        }
        if (event.target.closest('[data-mbcc-reject]')) {
			save(consentObject(true, false, false, false));
            return;
        }
        if (event.target.closest('[data-mbcc-save]')) {
			var necessaryInput = document.getElementById('mbcc-necessary');
			save(consentObject(
				necessaryInput ? necessaryInput.checked : true,
				document.getElementById('mbcc-preferences').checked,
                document.getElementById('mbcc-analytics').checked,
                document.getElementById('mbcc-marketing').checked
            ));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!modal || modal.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closeModal();
        }
        if (event.key === 'Tab' && dialog) {
            var focusable = dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])');
            if (!focusable.length) {
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    var consent = readCookie();
    if (validConsent(consent)) {
        if (banner) {
            banner.hidden = true;
        }
        activateAllowed(consent).then(function () { addIframePlaceholders(consent); });
    } else {
        document.documentElement.classList.remove('mbcc-consent-valid');
        if (banner) {
            banner.hidden = false;
        }
		var defaultConsent = consentObject(true, false, false, false);
		activateAllowed(defaultConsent).then(function () { addIframePlaceholders(defaultConsent); });
    }
}());
