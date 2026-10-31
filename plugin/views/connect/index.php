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
 * @var array{landing_url: string, expires_at: int}|null $new_code
 */
?>
<? if ($pairing_enabled) : ?>
<section class="contentbox app-connect-pairing">
    <header><h1><?= _('App mit deinem Konto verbinden') ?></h1></header>
    <section>
        <? if ($new_code) : ?>
            <p><?= _('Scanne diesen QR-Code mit der App. Er gilt fünf Minuten und nur einmal.') ?></p>
            <? /* QR code of the core; .tfa-app-code gives it the core's sizing. */ ?>
            <div class="tfa-app-code">
                <code class="qr hide-text"><?= htmlReady($new_code['landing_url']) ?></code>
            </div>
            <p class="app-connect-status" data-status-url="<?= htmlReady($status_url) ?>"
               data-index-url="<?= htmlReady($index_url) ?>"
               data-expires-in="<?= max(0, $new_code['expires_at'] - time()) ?>" aria-live="polite">
                <?= _('Wartet auf die App …') ?>
            </p>
            <p><?= _('Zeige den QR-Code niemandem und fotografiere ihn nicht ab: Wer ihn zuerst scannt, ist mit deinem Konto verbunden.') ?></p>
            <form method="post" action="<?= $controller->link_for('connect/discard_code') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create(_('Code verwerfen'), 'discard') ?>
            </form>
        <? else : ?>
            <p><?= _('Erzeuge einen QR-Code und scanne ihn mit der App. Die App ist danach ohne weitere Anmeldung mit deinem Konto verbunden.') ?></p>
            <form method="post" action="<?= $controller->link_for('connect/create_code') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::createAccept(_('QR-Code erzeugen'), 'create') ?>
            </form>
        <? endif ?>
    </section>
</section>
<? endif ?>

<section class="contentbox">
    <header><h1><?= _('Mit der App verbundene Geräte') ?></h1></header>
    <section>
        <? if (!$device_list) : ?>
            <p><?= _('Es ist kein Gerät verbunden.') ?></p>
        <? else : ?>
            <form method="post">
                <?= CSRFProtection::tokenTag() ?>
                <table class="default">
                    <thead>
                        <tr>
                            <th><?= _('Gerät') ?></th>
                            <th><?= _('Verbunden') ?></th>
                            <th><?= _('Zuletzt verwendet') ?></th>
                            <th class="actions"><?= _('Aktionen') ?></th>
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
                                <?= date('d.m.Y H:i', (int) $device->paired_at) ?>,
                                <?= $device->paired_via === 'pairing' ? _('per QR-Code') : _('per Browser-Anmeldung') ?>
                            </td>
                            <td><?= date('d.m.Y', (int) $device->bound_at) ?></td>
                            <td class="actions">
                                <?= ActionMenu::get()->setContext($device->name)->addButton(
                                    'remove',
                                    _('Gerät entfernen'),
                                    Icon::create('trash'),
                                    [
                                        'formaction' => $controller->link_for('connect/remove/' . $device->id),
                                        'data-confirm' => _('Das Gerät verliert den Zugang zu deinem Konto. Fortfahren?'),
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
                <?= sprintf(
                    ngettext('Weitere Anmeldung der App ohne Gerätezuordnung: %u', 'Weitere Anmeldungen der App ohne Gerätezuordnung: %u', $unassigned_count),
                    $unassigned_count
                ) ?>
            </p>
            <form method="post" action="<?= $controller->link_for('connect/end_unassigned') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create(_('Alle beenden'), 'end_unassigned') ?>
            </form>
        <? endif ?>
    </section>
</section>

<? if ($new_code) : ?>
<script>
(function () {
    const status = document.querySelector('.app-connect-status');
    if (!status) {
        return;
    }
    const deadline = Date.now() + Number(status.dataset.expiresIn) * 1000;
    const texts = {
        waiting: <?= json_encode(_('Wartet auf die App … noch %s')) ?>,
        expired: <?= json_encode(_('Der Code ist abgelaufen. Erzeuge bei Bedarf einen neuen.')) ?>,
        connected: <?= json_encode(_('Verbunden: %s · %s · %s Uhr. Das warst nicht du? Entferne das Gerät unten.')) ?>
    };
    function format(text, values) {
        return values.reduce((result, value) => result.replace('%s', value), text);
    }
    function remaining() {
        const seconds = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }
    function done(text, reload) {
        clearInterval(timer);
        document.querySelectorAll('.app-connect-pairing code.qr, .app-connect-pairing canvas').forEach((node) => node.remove());
        status.textContent = text;
        if (reload) {
            setTimeout(() => window.location.assign(status.dataset.indexUrl), 4000);
        }
    }
    const timer = setInterval(() => {
        if (Date.now() >= deadline) {
            done(texts.expired, false);
            return;
        }
        status.textContent = format(texts.waiting, [remaining()]);
        fetch(status.dataset.statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                if (data && data.state === 'connected') {
                    done(format(texts.connected, [data.device.name, data.device.platform, data.device.time]), true);
                } else if (data && data.state === 'expired') {
                    done(texts.expired, false);
                }
            })
            .catch(() => {});
    }, 3000);
}());
</script>
<? endif ?>
