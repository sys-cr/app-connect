// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

// The link of the web login: takes the ticket from the fragment and posts it
// with the CSRF token of the page to web/redeem.
(function () {
    'use strict';
    const form = document.querySelector('form.app-connect-web-login');
    const ticket = window.location.hash.slice(1);
    // The ticket leaves the address bar and the history before anything else.
    if (window.location.hash) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    if (!form || !/^[A-Za-z0-9_-]{43}$/.test(ticket)) {
        document.querySelector('.app-connect-web-failed').hidden = false;
        return;
    }
    form.elements.ticket.value = ticket;
    form.hidden = false;
    form.submit();
}());
