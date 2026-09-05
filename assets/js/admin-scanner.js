(function () {
    'use strict';

    var button = document.getElementById('mbcc-start-scan');
    var progress = document.getElementById('mbcc-scan-progress');

    if (!button || !progress || typeof window.MBCC_SCANNER !== 'object') {
        return;
    }

    function request(action) {
        var body = new URLSearchParams({ action: action, nonce: window.MBCC_SCANNER.nonce });
        return fetch(window.MBCC_SCANNER.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.data && payload.data.message ? payload.data.message : window.MBCC_SCANNER.failed);
            }
            return payload.data;
        });
    }

    function format(template, first, second) {
        return template.replace('%1$d', first).replace('%2$d', second);
    }

    function scanNext() {
        return request('mbcc_scan_batch').then(function (data) {
            progress.textContent = format(window.MBCC_SCANNER.scanning, data.scanned, data.total);
            if (data.remaining > 0) {
                return scanNext();
            }
            progress.textContent = window.MBCC_SCANNER.finished;
            window.location.reload();
        });
    }

    button.addEventListener('click', function () {
        button.disabled = true;
        progress.textContent = window.MBCC_SCANNER.starting;
        request('mbcc_start_scan').then(scanNext).catch(function (error) {
            progress.textContent = error.message || window.MBCC_SCANNER.failed;
            button.disabled = false;
        });
    });
}());
