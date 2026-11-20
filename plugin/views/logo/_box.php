<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * The logo box on "Stud.App Verbindung" for root; the forms go to LogoController.
 *
 * @var array{configured: ?string, status: ?\AppConnect\Logo\StoredLogo, messages: list<array{0: string, 1: string}>, preview: ?string} $logo
 * @var PluginController $controller
 */
?>
<section class="contentbox">
    <header><h2 style="<?= \AppConnect\ContentBox::HEADING_STYLE ?>"><?= $_('Logo für die App') ?></h2></header>
    <section>
        <? if ($logo['status'] !== null && $logo['status']->isUpload()) : ?>
            <p>
                <img src="<?= htmlReady($logo['preview']) ?>" alt="<?= htmlReady($_('Logo der Hochschule')) ?>" height="64">
            </p>
            <?= $this->render_partial('logo/_messages', ['messages' => $logo['messages']]) ?>
            <? if ($logo['configured'] !== null) : ?>
                <p>
                    <?= $_('Das hochgeladene Logo hat Vorrang. Diese Adresse aus der Konfiguration verwenden die Apps erst, wenn Sie es entfernen:') ?>
                    <?= htmlReady($logo['configured']) ?>
                </p>
            <? endif ?>
            <form method="post" action="<?= $controller->link_for('logo/remove') ?>">
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
            <?= $this->render_partial('logo/_messages', ['messages' => $logo['messages']]) ?>
            <form method="post" action="<?= $controller->link_for('logo/reload') ?>">
                <?= CSRFProtection::tokenTag() ?>
                <?= Studip\Button::create($_('Logo neu laden'), 'reload_logo') ?>
            </form>
        <? endif ?>
        <form class="default" method="post" enctype="multipart/form-data" action="<?= $controller->link_for('logo/upload') ?>">
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
