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
 * @var array{configured: ?string, status: ?\AppConnect\Logo\StoredLogo, messages: list<array{0: string, 1: string}>, preview: ?string}|null $logo for root, see views/logo/_box.php
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
    <?= $this->render_partial('logo/_box', ['logo' => $logo, 'controller' => $controller]) ?>
<? endif ?>
