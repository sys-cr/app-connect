<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * @var ConnectController $controller
 * @var bool $pairing_enabled
 * @var list<\AppConnect\Devices\DeviceRecord> $device_list
 * @var int $unassigned_count
 * @var string $status_url
 * @var string $index_url
 * @var array{landing_url: string, expires_in: int}|null $new_code
 * @var array{configured: ?string, status: ?\AppConnect\Logo\StoredLogo, messages: list<array{0: string, 1: string}>, preview: ?string}|null $logo
 */
?>
<? if ($pairing_enabled) : ?>
<section class="contentbox app-connect-pairing">
    <header><h2 style="<?= \AppConnect\ContentBox::HEADING_STYLE ?>"><?= $_('App mit Ihrem Konto verbinden') ?></h2></header>
    <section>
        <? if ($new_code) : ?>
            <p><?= $_('Scannen Sie diesen QR-Code mit der App. Er gilt fünf Minuten und nur einmal.') ?></p>
            <? /* QR code of the core; .tfa-app-code gives it the core's sizing. */ ?>
            <div class="tfa-app-code">
                <code class="qr hide-text"><?= htmlReady($new_code['landing_url']) ?></code>
            </div>
            <p class="app-connect-status" data-status-url="<?= htmlReady($status_url) ?>"
               data-index-url="<?= htmlReady($index_url) ?>"
               data-expires-in="<?= (int) $new_code['expires_in'] ?>"
               data-text-waiting="<?= htmlReady($_('Wartet auf die App … noch %s')) ?>"
               data-text-expired="<?= htmlReady($_('Der Code ist abgelaufen. Erzeugen Sie bei Bedarf einen neuen.')) ?>"
               data-text-connected="<?= htmlReady($_('Verbunden: %s · %s · %s Uhr. Waren Sie das nicht? Entfernen Sie das Gerät unten.')) ?>"
               aria-live="polite">
                <?= $_('Wartet auf die App …') ?>
            </p>
            <p><?= $_('Zeigen Sie den QR-Code niemandem und fotografieren Sie ihn nicht ab: Wer ihn zuerst scannt, ist mit Ihrem Konto verbunden.') ?></p>
            <form method="post" action="<?= $controller->link_for('connect/discard_code') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create($_('Code verwerfen'), 'discard') ?>
            </form>
        <? else : ?>
            <p><?= $_('Erzeugen Sie einen QR-Code und scannen Sie ihn mit der App. Die App ist danach ohne weitere Anmeldung mit Ihrem Konto verbunden.') ?></p>
            <form method="post" action="<?= $controller->link_for('connect/create_code') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::createAccept($_('QR-Code erzeugen'), 'create') ?>
            </form>
        <? endif ?>
    </section>
</section>
<? endif ?>

<section class="contentbox">
    <header><h2 style="<?= \AppConnect\ContentBox::HEADING_STYLE ?>"><?= $_('Mit der App verbundene Geräte') ?></h2></header>
    <section>
        <? if (!$device_list) : ?>
            <p><?= $_('Es ist kein Gerät verbunden.') ?></p>
        <? else : ?>
            <form method="post">
                <?= CSRFProtection::tokenTag() ?>
                <table class="default">
                    <thead>
                        <tr>
                            <th><?= $_('Gerät') ?></th>
                            <th><?= $_('Verbunden') ?></th>
                            <th><?= $_('Zuletzt aktiv') ?></th>
                            <th class="actions"><?= $_('Aktionen') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <? foreach ($device_list as $device) : ?>
                        <tr>
                            <td>
                                <?= htmlReady($device->name) ?>
                                (<?= $device->platform === 'ios' ? 'iOS' : 'Android' ?>)
                            </td>
                            <td>
                                <?= htmlReady(\AppConnect\Format::dateTime((int) $device->paired_at)) ?>,
                                <?= $device->paired_via === 'pairing' ? $_('per QR-Code') : $_('per Browser-Anmeldung') ?>
                            </td>
                            <td><?= htmlReady(\AppConnect\Format::date((int) $device->bound_at)) ?></td>
                            <td class="actions">
                                <?= ActionMenu::get()->setContext($device->name)->addButton(
                                    'remove',
                                    $_('Gerät entfernen'),
                                    Icon::create('trash'),
                                    [
                                        'formaction' => $controller->link_for('connect/remove/' . $device->id),
                                        'data-confirm' => $_('Die App auf diesem Gerät verliert den Zugang zu Ihrem Konto. Ein Kalender-Abo und eine im Browser geöffnete Stud.IP-Sitzung bleiben bestehen. Fortfahren?'),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <? endforeach ?>
                    </tbody>
                </table>
            </form>
        <? endif ?>
        <? if ($unassigned_count > 0) : ?>
            <p>
                <?= sprintf($_('Weitere Anmeldungen der App ohne Gerätezuordnung: %u'), $unassigned_count) ?>
            </p>
            <form method="post" action="<?= $controller->link_for('connect/end_unassigned') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create($_('Alle beenden'), 'end_unassigned') ?>
            </form>
        <? endif ?>
    </section>
</section>

<? if ($logo) : ?>
<section class="contentbox">
    <header><h2 style="<?= \AppConnect\ContentBox::HEADING_STYLE ?>"><?= $_('Logo für die App') ?></h2></header>
    <section>
        <? if ($logo['status'] !== null && $logo['status']->isUpload()) : ?>
            <p>
                <img src="<?= htmlReady($logo['preview']) ?>" alt="<?= htmlReady($_('Logo der Hochschule')) ?>" height="64">
            </p>
            <?= $this->render_partial('connect/_logo_messages', ['messages' => $logo['messages']]) ?>
            <? if ($logo['configured'] !== null) : ?>
                <p>
                    <?= $_('Das hochgeladene Logo hat Vorrang. Diese Adresse aus der Konfiguration verwenden die Apps erst, wenn Sie es entfernen:') ?>
                    <?= htmlReady($logo['configured']) ?>
                </p>
            <? endif ?>
            <form method="post" action="<?= $controller->link_for('connect/remove_logo') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create($_('Hochgeladenes Logo entfernen'), 'remove_logo', [
                    'data-confirm' => $logo['configured'] !== null
                        ? $_('Die Apps erhalten dann das Logo von der Adresse aus der Konfiguration. Fortfahren?')
                        : $_('Die Apps zeigen dann ein allgemeines Symbol. Fortfahren?'),
                ]) ?>
            </form>
        <? elseif ($logo['configured'] === null) : ?>
            <?= MessageBox::info(htmlReady($_('Es ist kein Logo hinterlegt. Die Apps zeigen dann ein allgemeines Symbol. Laden Sie unten eine Bilddatei hoch oder tragen Sie eine Adresse unter Admin → System → Konfiguration, Sektion „Stud.App“, bei APP_CONNECT_LOGO_URL ein.'))) ?>
        <? else : ?>
            <p>
                <?= $_('Das Plugin ruft das Logo von dieser Adresse ab und liefert es den Apps von diesem Server aus:') ?>
                <?= htmlReady($logo['configured']) ?>
            </p>
            <? if ($logo['preview'] !== null) : ?>
                <p>
                    <img src="<?= htmlReady($logo['preview']) ?>" alt="<?= htmlReady($_('Logo der Hochschule')) ?>" height="64">
                </p>
            <? endif ?>
            <?= $this->render_partial('connect/_logo_messages', ['messages' => $logo['messages']]) ?>
            <form method="post" action="<?= $controller->link_for('connect/reload_logo') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create($_('Logo neu laden'), 'reload_logo') ?>
            </form>
        <? endif ?>
        <form class="default" method="post" enctype="multipart/form-data" action="<?= $controller->link_for('connect/upload_logo') ?>">
            <?= CSRFProtection::tokenTag() ?>
            <label>
                <?= $logo['status'] !== null && $logo['status']->isUpload() ? $_('Anderes Logo hochladen') : $_('Logo hochladen') ?>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
            </label>
            <p><?= $_('PNG, JPEG oder WebP, höchstens 512 KB, 32 bis 2048 Pixel je Seite. Ein hochgeladenes Logo hat Vorrang vor der Adresse in der Konfiguration.') ?></p>
            <?= Studip\Button::createAccept($_('Hochladen'), 'upload_logo') ?>
        </form>
    </section>
</section>
<? endif ?>
