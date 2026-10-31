<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * @var string $redeem_url
 * @var string $login_url
 */
?>
<noscript>
    <?= MessageBox::info(_('Dieser Link funktioniert nur mit JavaScript. Bitte melde dich an.')) ?>
    <p><?= Studip\LinkButton::create(_('Anmelden'), $login_url) ?></p>
</noscript>

<div class="app-connect-web-failed" hidden>
    <?= MessageBox::error(_('Der Link ist abgelaufen oder ungültig. Bitte melde dich an.')) ?>
    <p><?= Studip\LinkButton::create(_('Anmelden'), $login_url) ?></p>
</div>

<form class="app-connect-web-login" method="post" action="<?= htmlReady($redeem_url) ?>" hidden>
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="ticket" value="">
    <p><?= _('Du wirst mit deiner Anmeldung aus der App angemeldet.') ?></p>
    <?= Studip\Button::createAccept(_('Weiter'), 'redeem') ?>
</form>

<script>
(function () {
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
</script>
