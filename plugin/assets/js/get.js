// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

// "App holen": the pairing code travels in the fragment (#p=…), which the
// browser never sends to the server. Only a well-formed code is added to the
// app link.
(function () {
    'use strict';
    const match = /(?:^|&)p=([0-9A-Za-z]{26})(?:&|$)/.exec(window.location.hash.slice(1));
    const link = document.querySelector('[data-connect-link]');
    if (!match || !link || !/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/.test(match[1])) {
        return;
    }
    link.href = link.dataset.connectLink + '&p=' + match[1].toUpperCase();
    document.querySelector('.app-connect-code-note').hidden = false;
}());
