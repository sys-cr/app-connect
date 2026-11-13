<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

use AppConnect\Attribution;
use AppConnect\Audit\EventLog;
use AppConnect\Connect\ConnectLink;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Help;
use AppConnect\Logo\LogoImage;
use AppConnect\Logo\LogoRejected;
use AppConnect\Logo\LogoStatusText;
use AppConnect\Pairing\PairingService;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;

/**
 * Profile → Settings → "Stud.App Verbindung", above "Drittanwendungen":
 * create a pairing code shown as QR code, watch the connection happen, list and
 * remove connected devices. Acts only for the signed-in person; a `username`
 * parameter is ignored. For root the page also shows the state of the
 * institution logo the plugin serves to the apps.
 */
class ConnectController extends PluginController
{
    private Settings $settings;
    private DeviceService $devices;
    private PairingService $pairing;
    private string $userId;

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        $this->response->add_header('X-Frame-Options', 'DENY');
        $this->response->add_header('Content-Security-Policy', "frame-ancestors 'none'");
        $GLOBALS['perm']->check('autor');
        $this->settings = Runtime::settings();
        if (!$this->settings->isEnabled() || !$this->settings->isProfileEntryEnabled() || $this->settings->clientId() === null) {
            throw new AccessDeniedException($this->_('Die Verbindung mit der App ist an dieser Hochschule nicht eingeschaltet.'));
        }
        $this->userId = (string) $GLOBALS['user']->id;
        $this->devices = new DeviceService(new TokenLedger(), (string) $this->settings->clientId());
        $this->pairing = new PairingService();
        if (Navigation::hasItem(AppConnect::NAVIGATION_PATH)) {
            Navigation::activateItem(AppConnect::NAVIGATION_PATH);
        }
        PageLayout::setTitle($this->_('Stud.App Verbindung'));
        Help::addToHelpbar(Help::CONNECT, $this->settings->isPairingEnabled());
        Attribution::addToHelpbar();
    }

    public function index_action()
    {
        $this->pairing_enabled = $this->settings->isPairingEnabled();
        $this->device_list = $this->devices->activeDevices($this->userId);
        $this->unassigned_count = count($this->devices->unassignedTokens($this->userId));
        $this->status_url = $this->url_for('connect/status');
        $this->index_url = $this->url_for('connect/index');
        $this->new_code = $this->new_code ?? null;
        $supportEmail = $this->settings->arrayValue(Settings::INSTANCE)['supportEmail'] ?? null;
        if ($GLOBALS['perm']->have_perm('root') && !is_string($supportEmail)) {
            PageLayout::postInfo($this->_('Ohne Support-Adresse (APP_CONNECT_SUPPORT_EMAIL) gehen Meldungen von Inhalten aus der App an support@studapp2.de, den Support des Projekts Stud.App. Tragen Sie unter Admin → System → Konfiguration, Sektion „Stud.App“, die Support-Adresse Ihrer Hochschule ein, damit Meldungen dort ankommen.'));
        }
        $this->logo = null;
        if ($GLOBALS['perm']->have_perm('root')) {
            $url = Runtime::configuredLogoUrl();
            $status = Runtime::logo()->status($url);
            $this->logo = [
                'configured' => $url,
                'status' => $status,
                'problem' => $status?->problem !== null ? LogoStatusText::reason($status->problem) : null,
                'preview' => $status?->image !== null
                    ? Runtime::baseUrl() . '/jsonapi.php/v1/' . DiscoveryBuilder::LOGO_ROUTE . '?v=' . $status->image->version()
                    : null,
            ];
        }
    }

    /** Fetches the configured logo again (root only). */
    public function reload_logo_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $GLOBALS['perm']->check('root');
        LogoStatusText::post(Runtime::logo()->refresh(Runtime::configuredLogoUrl()));
        $this->redirect('connect/index');
    }

    /**
     * Stores an uploaded picture as the logo (root only). It passes the same
     * checks as a fetched one and takes precedence over the address.
     */
    public function upload_logo_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $GLOBALS['perm']->check('root');
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

    /** Deletes the uploaded logo; the address from the configuration applies again (root only). */
    public function remove_logo_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $GLOBALS['perm']->check('root');
        $logo = Runtime::logo()->removeUpload(Runtime::configuredLogoUrl());
        EventLog::record(EventLog::SETTINGS_CHANGE, 'logo upload removed');
        PageLayout::postSuccess(htmlReady($this->_('Das hochgeladene Logo wurde entfernt.')));
        LogoStatusText::post($logo);
        $this->redirect('connect/index');
    }

    public function create_code_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        if (!$this->settings->isPairingEnabled()) {
            $this->redirect('connect/index');
            return;
        }
        $created = $this->pairing->create($this->userId);
        if ($created === null) {
            PageLayout::postError($this->_('Sie haben in der letzten Stunde zu viele Codes erzeugt. Bitte versuchen Sie es später noch einmal.'));
            $this->redirect('connect/index');
            return;
        }
        // The code exists only in this response; the database keeps its hash.
        $this->new_code = [
            'landing_url' => ConnectLink::landingUrl(Runtime::baseUrl(), $created['code']),
            'expires_at' => $created['expires_at'],
        ];
        $this->response->add_header('Cache-Control', 'no-store');
        $this->index_action();
        $this->render_action('index');
    }

    public function discard_code_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $this->pairing->discard($this->userId);
        $this->redirect('connect/index');
    }

    public function status_action()
    {
        $status = $this->pairing->status($this->userId);
        $data = ['state' => $status['state']];
        if (isset($status['expires_at'])) {
            $data['expires_in'] = max(0, $status['expires_at'] - time());
        }
        if (isset($status['device'])) {
            $data['device'] = [
                'name' => (string) $status['device']->name,
                'platform' => $status['device']->platform === 'ios' ? 'iOS' : 'Android',
                'time' => date('H:i', (int) $status['device']->paired_at),
            ];
        }
        $this->response->add_header('Cache-Control', 'no-store');
        $this->render_json($data);
    }

    public function remove_action($id = null)
    {
        CSRFProtection::verifyUnsafeRequest();
        if ($this->devices->remove($this->userId, (int) $id)) {
            PageLayout::postSuccess($this->_('Das Gerät wurde entfernt. Es kann nicht mehr auf Ihr Konto zugreifen.'));
        }
        $this->redirect('connect/index');
    }

    public function end_unassigned_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $count = $this->devices->endUnassigned($this->userId);
        if ($count > 0) {
            PageLayout::postSuccess($this->_('Die weiteren Anmeldungen wurden beendet.'));
        }
        $this->redirect('connect/index');
    }
}
