// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

// "Stud.App Verbindung": counts down the pairing code and asks every three
// seconds whether the app has connected. Texts and addresses come from the
// data attributes of the status element.
(function () {
    'use strict';
    const status = document.querySelector('.app-connect-status');
    if (!status) {
        return;
    }
    const deadline = Date.now() + Number(status.dataset.expiresIn) * 1000;
    const texts = {
        waiting: status.dataset.textWaiting,
        expired: status.dataset.textExpired,
        connected: status.dataset.textConnected
    };
    function format(text, values) {
        return values.reduce((result, value) => result.replace('%s', value), text);
    }
    function remaining() {
        const seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }
    let timer = null;
    let poller = null;
    function done(text, reload) {
        clearInterval(timer);
        clearInterval(poller);
        document.querySelectorAll('.app-connect-pairing code.qr, .app-connect-pairing canvas').forEach((node) => node.remove());
        status.textContent = text;
        if (reload) {
            setTimeout(() => window.location.assign(status.dataset.indexUrl), 4000);
        }
    }
    // The time counts every second in its own element; a timer is not read
    // out by screen readers, unlike the live status around it.
    const clock = document.createElement('span');
    clock.setAttribute('role', 'timer');
    const [before, after = ''] = texts.waiting.split('%s');
    function tick() {
        if (Date.now() >= deadline) {
            done(texts.expired, false);
            return;
        }
        clock.textContent = remaining();
        if (!clock.isConnected) {
            status.replaceChildren(before, clock, after);
        }
    }
    function poll() {
        fetch(status.dataset.statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                if (data && data.state === 'connected') {
                    done(format(texts.connected, [data.device.name, data.device.platform, data.device.time]), true);
                } else if (data && data.state === 'expired') {
                    done(texts.expired, false);
                }
            })
            .catch(() => {});
    }
    timer = setInterval(tick, 1000);
    poller = setInterval(poll, 3000);
    tick();
}());
