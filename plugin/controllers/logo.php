<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

use AppConnect\Audit\EventLog;
use AppConnect\Logo\LogoImage;
use AppConnect\Logo\LogoRejected;
use AppConnect\Logo\LogoStatusText;
use AppConnect\Runtime;

/**
 * The institution logo the plugin serves to the apps, for root: fetch the
 * configured address again, upload a picture, remove the upload. The box
 * with the state is on "Stud.App Verbindung"; every action returns there.
 *
 * Trails passes the undeclared properties to the view. The core's
 * Trails\Controller does not allow them, so from PHP 8.2 on the attribute
 * is needed here; declaring them instead would hide them from the view.
 */
#[\AllowDynamicProperties]
class LogoController extends PluginController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        $this->response->add_header('X-Frame-Options', 'DENY');
        $this->response->add_header('Content-Security-Policy', "frame-ancestors 'none'");
        $GLOBALS['perm']->check('root');
        if (!Runtime::settings()->isConnected()) {
            throw new AccessDeniedException($this->_('Die Verbindung mit der App ist an dieser Hochschule nicht eingeschaltet.'));
        }
    }

    /** Fetches the configured logo again. */
    public function reload_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        LogoStatusText::post(Runtime::logo()->refresh(Runtime::configuredLogoUrl()));
        $this->redirect('connect/index');
    }

    /**
     * Stores an uploaded picture as the logo. It passes the same
     * checks as a fetched one and takes precedence over the address.
     */
    public function upload_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $file = $_FILES['logo'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        try {
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                || ($error === UPLOAD_ERR_OK && (int) $file['size'] > LogoImage::MAX_BYTES)) {
                throw new LogoRejected(LogoRejected::TOO_LARGE);
            }
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                PageLayout::postError(htmlReady($this->_('Wählen Sie eine Bilddatei aus: PNG, JPEG oder WebP, höchstens 512 KB.')));
            } else {
                Runtime::logo()->upload((string) file_get_contents((string) $file['tmp_name']));
                EventLog::record(EventLog::SETTINGS_CHANGE, 'logo upload');
                PageLayout::postSuccess(htmlReady($this->_('Das Logo wurde hochgeladen. Die Apps erhalten es von diesem Server.')));
            }
        } catch (LogoRejected $rejected) {
            PageLayout::postError(htmlReady(sprintf(
                $this->_('Das Logo wurde nicht übernommen: %s'),
                LogoStatusText::reason($rejected->reason)
            )));
        }
        $this->redirect('connect/index');
    }

    /** Deletes the uploaded logo; the address from the configuration applies again. */
    public function remove_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $logo = Runtime::logo()->removeUpload(Runtime::configuredLogoUrl());
        EventLog::record(EventLog::SETTINGS_CHANGE, 'logo upload removed');
        PageLayout::postSuccess(htmlReady($this->_('Das hochgeladene Logo wurde entfernt.')));
        LogoStatusText::post($logo);
        $this->redirect('connect/index');
    }
}
