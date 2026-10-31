<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AppConnect\Audit\EventLog;
use AppConnect\Devices\DeviceRetention;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\Routes\CourseDatePlacesShow;
use AppConnect\Routes\DevicesCreate;
use AppConnect\Routes\DiscoveryShow;
use AppConnect\Routes\IcalKeyDelete;
use AppConnect\Routes\IcalKeyShow;
use AppConnect\Routes\TokenCreate;
use AppConnect\Routes\UserEventsShow;
use AppConnect\Routes\UserLanguageShow;
use AppConnect\Routes\WebLoginCreate;
use AppConnect\Runtime;
use AppConnect\Settings\OperatorConfig;
use JsonApi\Contracts\JsonApiPlugin;
use Slim\Routing\RouteCollectorProxy;

/**
 * Connects the mobile apps to this instance: the public discovery, the token
 * endpoint of the shared app client, device registration, the one-time web
 * login from the app, the page
 * "Stud.App Verbindung" under Profile → Settings and the operator settings in
 * the configuration section "Stud.App" (Admin → System → Konfiguration).
 */
class AppConnect extends StudIPPlugin implements SystemPlugin, JsonApiPlugin
{
    /** Settings entry of the page "Stud.App Verbindung". */
    public const NAVIGATION_PATH = '/profile/settings/app_connect';

    public function __construct()
    {
        parent::__construct();

        // A deleted account leaves nothing behind in the plugin tables.
        NotificationCenter::on('UserDidDelete', static function ($event, $user): void {
            if (is_object($user) && isset($user->id)) {
                (new DeviceRetention())->forgetUser((string) $user->id);
            }
        });

        // The operator settings are configuration entries of the section
        // "Stud.App" (Admin → System → Konfiguration); switching the app
        // connection on or off there provides or revokes the app client.
        NotificationCenter::on('ConfigValueDidChange', function ($event, $config, $change): void {
            $this->configChanged(is_array($change) ? $change : []);
        });

        $perm = $GLOBALS['perm'] ?? null;
        if (!is_object($perm)) {
            return;
        }
        // Own entry in the settings navigation, right above "Drittanwendungen";
        // connected apps appear in the core list there by themselves.
        if ($perm->have_perm('autor') && Navigation::hasItem('/profile/settings') && $this->profileEntryIsOn()) {
            $entry = new Navigation(_('Stud.App Verbindung'), PluginEngine::getURL($this, [], 'connect'));
            if (Navigation::hasItem('/profile/settings/oauth2')) {
                Navigation::insertItem(self::NAVIGATION_PATH, $entry, 'oauth2');
            } else {
                Navigation::addItem(self::NAVIGATION_PATH, $entry);
            }
        }
    }

    public function registerAuthenticatedRoutes(RouteCollectorProxy $group)
    {
        $group->post('/app-connect/devices', DevicesCreate::class);
        $group->get('/app-connect/course-dates/places', CourseDatePlacesShow::class);
        $group->get('/app-connect/user-language', UserLanguageShow::class);
        $group->get('/app-connect/user-events', UserEventsShow::class);
        $group->post('/app-connect/web-login', WebLoginCreate::class);
        $group->get('/app-connect/me/ical-key', IcalKeyShow::class);
        $group->delete('/app-connect/me/ical-key', IcalKeyDelete::class);
    }

    public function registerUnauthenticatedRoutes(RouteCollectorProxy $group)
    {
        $group->get('/app-connect/discovery', DiscoveryShow::class);
        $group->post('/app-connect/token', TokenCreate::class);
    }

    public function registerSchemas(): array
    {
        return [];
    }

    private function profileEntryIsOn(): bool
    {
        $settings = Runtime::settings();
        return $settings->isEnabled() && $settings->isProfileEntryEnabled() && $settings->clientId() !== null;
    }

    /** @param array{field?: string, new_value?: mixed} $change */
    private function configChanged(array $change): void
    {
        $field = (string) ($change['field'] ?? '');
        if (!array_key_exists($field, OperatorConfig::definitions())) {
            return;
        }
        if ($field === OperatorConfig::ENABLED) {
            $provisioner = new ClientProvisioner(Runtime::settings());
            if (in_array((string) ($change['new_value'] ?? ''), ['1', 'true'], true)) {
                $provisioner->provision(Runtime::baseUrl() . '/');
                $this->allowRequestsWithoutSession();
            } else {
                $provisioner->revoke();
            }
            return;
        }
        EventLog::record(EventLog::SETTINGS_CHANGE, $field);
        $value = trim((string) ($change['new_value'] ?? ''));
        if ($value === '') {
            return;
        }
        $problem = match (true) {
            in_array($field, OperatorConfig::URL_FIELDS, true) && !OperatorConfig::isHttpsUrl($value)
                => _('Die Adresse muss mit https:// beginnen.'),
            $field === OperatorConfig::SUPPORT_EMAIL && !OperatorConfig::isEmail($value)
                => _('Das ist keine gültige E-Mail-Adresse.'),
            $field === OperatorConfig::ACCENT_COLOR && !OperatorConfig::isHexColor($value)
                => _('Die Farbe muss die Form #RRGGBB haben.'),
            $field === OperatorConfig::MENSA_OPENMENSA_IDS && OperatorConfig::canteenIds($value) === null
                => _('Erwartet werden OpenMensa-IDs (Zahlen), durch Komma getrennt.'),
            default => null,
        };
        if ($problem !== null) {
            PageLayout::postWarning(sprintf(_('%s wird nicht verwendet: %s'), htmlReady($field), $problem));
        }
    }

    /**
     * The discovery and the token endpoint are called without a session, so
     * the plugin must be loaded for the "Nobody" role as well.
     */
    private function allowRequestsWithoutSession(): void
    {
        $roleIds = [];
        foreach (RolePersistence::getAllRoles() as $role) {
            if ($role->getRolename() === 'Nobody') {
                $roleIds[] = $role->getRoleid();
            }
        }
        if ($roleIds !== []) {
            RolePersistence::assignPluginRoles($this->getPluginId(), $roleIds);
        }
    }
}
