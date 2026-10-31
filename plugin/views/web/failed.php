<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * @var string $login_url
 */
?>
<?= MessageBox::error(_('Der Link ist abgelaufen oder ungültig. Bitte melde dich an.')) ?>
<p><?= Studip\LinkButton::create(_('Anmelden'), $login_url) ?></p>
