<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

use AppConnect\Attribution;
use AppConnect\Connect\ConnectLink;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Pairing\PairingService;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;

/**
 * Profile → Settings → "Stud.App Verbindung", above "Drittanwendungen":
 * create a pairing code shown as QR code, watch the connection happen, list and
 * remove connected devices. Acts only for the signed-in person; a `username`
 * parameter is ignored.
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
            throw new AccessDeniedException(_('Die Verbindung mit der App ist an dieser Hochschule nicht eingeschaltet.'));
        }
        $this->userId = (string) $GLOBALS['user']->id;
        $this->devices = new DeviceService(new TokenLedger(), (string) $this->settings->clientId());
        $this->pairing = new PairingService();
        if (Navigation::hasItem(AppConnect::NAVIGATION_PATH)) {
            Navigation::activateItem(AppConnect::NAVIGATION_PATH);
        }
        PageLayout::setTitle(_('Stud.App Verbindung'));
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
            PageLayout::postError(_('Du hast in der letzten Stunde zu viele Codes erzeugt. Bitte versuche es später noch einmal.'));
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
            PageLayout::postSuccess(_('Das Gerät wurde entfernt. Es kann nicht mehr auf dein Konto zugreifen.'));
        }
        $this->redirect('connect/index');
    }

    public function end_unassigned_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $count = $this->devices->endUnassigned($this->userId);
        if ($count > 0) {
            PageLayout::postSuccess(_('Die weiteren Anmeldungen wurden beendet.'));
        }
        $this->redirect('connect/index');
    }
}
