<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * @var bool $enabled
 * @var string $connect_link link without code; the script adds `p` from the fragment
 * @var string $instance_name
 * @var array<string, string> $store_links
 * @var string|null $privacy_url
 */
?>
<? if (!$enabled) : ?>
    <?= MessageBox::info(_('Die Verbindung mit der App ist an dieser Hochschule nicht eingeschaltet.')) ?>
<? else : ?>
<section class="contentbox app-connect-get">
    <header><h1><?= sprintf(_('Die App für %s'), htmlReady($instance_name)) ?></h1></header>
    <section>
        <p><?= _('Ist die App schon installiert, öffne sie hier. Sonst installiere sie und scanne den QR-Code danach in der App noch einmal – oder komm auf diese Seite zurück und tippe auf „In der App öffnen“.') ?></p>
        <p>
            <?= Studip\LinkButton::createAccept(_('In der App öffnen'), $connect_link, [
                'data-connect-link' => $connect_link,
            ]) ?>
        </p>
        <? if ($store_links) : ?>
            <ul class="app-connect-stores">
                <? if (isset($store_links['appStore'])) : ?>
                    <li><a href="<?= htmlReady($store_links['appStore']) ?>" rel="noreferrer"><?= _('Im App Store laden') ?></a></li>
                <? endif ?>
                <? if (isset($store_links['googlePlay'])) : ?>
                    <li><a href="<?= htmlReady($store_links['googlePlay']) ?>" rel="noreferrer"><?= _('Bei Google Play laden') ?></a></li>
                <? endif ?>
            </ul>
        <? endif ?>
        <p class="app-connect-code-note" hidden><?= _('Der Kopplungscode gilt fünf Minuten. Ist er abgelaufen, erzeuge im Profil einen neuen.') ?></p>
        <? if ($privacy_url) : ?>
            <p><a href="<?= htmlReady($privacy_url) ?>" rel="noreferrer"><?= _('Datenschutzerklärung') ?></a></p>
        <? endif ?>
    </section>
</section>
<script>
(function () {
    // The pairing code travels in the fragment (#p=…), which the browser never
    // sends to the server. Only a well-formed code is added to the app link.
    const match = /(?:^|&)p=([0-9A-Za-z]{26})(?:&|$)/.exec(window.location.hash.slice(1));
    const link = document.querySelector('[data-connect-link]');
    if (!match || !link || !/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/.test(match[1])) {
        return;
    }
    link.href = link.dataset.connectLink + '&p=' + match[1].toUpperCase();
    document.querySelector('.app-connect-code-note').hidden = false;
}());
</script>
<? endif ?>
