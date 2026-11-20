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
    <?= MessageBox::info($_('Dieser Link funktioniert nur mit JavaScript. Bitte melden Sie sich an.')) ?>
    <p><?= Studip\LinkButton::create($_('Anmelden'), $login_url) ?></p>
</noscript>

<div class="app-connect-web-failed" hidden>
    <?= MessageBox::error($_('Der Link ist abgelaufen oder ungültig. Bitte melden Sie sich an.')) ?>
    <p><?= Studip\LinkButton::create($_('Anmelden'), $login_url) ?></p>
</div>

<form class="app-connect-web-login" method="post" action="<?= htmlReady($redeem_url) ?>" hidden>
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="ticket" value="">
    <p><?= $_('Sie werden mit Ihrer Anmeldung aus der App angemeldet.') ?></p>
    <?= Studip\Button::createAccept($_('Weiter'), 'redeem') ?>
</form>
