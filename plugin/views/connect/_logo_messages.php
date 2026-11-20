<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * The messages of the logo box ({@see \AppConnect\Logo\LogoStatusText::describe()}).
 *
 * @var list<array{0: 'success'|'info'|'warning'|'error', 1: string}> $messages
 */
?>
<? foreach ($messages as [$kind, $text]) : ?>
    <?= match ($kind) {
        'success' => MessageBox::success(htmlReady($text)),
        'info' => MessageBox::info(htmlReady($text)),
        'warning' => MessageBox::warning(htmlReady($text)),
        default => MessageBox::error(htmlReady($text)),
    } ?>
<? endforeach ?>
