<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * @var User $person
 * @var User|null $current_person
 * @var string $confirm_url
 */
$name = sprintf('%s (%s)', $person->getFullName(), $person->username);
?>
<? if ($current_person !== null): ?>
    <?= MessageBox::warning(sprintf(
        $_('In diesem Browser ist %s angemeldet. Wenn Sie fortfahren, wird diese Person abgemeldet.'),
        htmlReady(sprintf('%s (%s)', $current_person->getFullName(), $current_person->username))
    )) ?>
<? endif ?>

<form class="default" method="post" action="<?= htmlReady($confirm_url) ?>">
    <?= CSRFProtection::tokenTag() ?>
    <p><?= sprintf($_('Als %s anmelden?'), '<strong>' . htmlReady($name) . '</strong>') ?></p>
    <p><?= $_('Melden Sie sich nur an, wenn Sie diesen Link gerade selbst aus der App geöffnet haben. Ist das nicht Ihr Konto, brechen Sie ab.') ?></p>
    <footer>
        <?= Studip\Button::createAccept($_('Anmelden'), 'confirm') ?>
        <?= Studip\Button::createCancel($_('Abbrechen'), 'cancel') ?>
    </footer>
</form>
