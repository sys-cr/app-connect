<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AppConnect\Devices\DeviceRetention;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\OAuth2\CoreRefreshGuard;
use AppConnect\Privacy\UserDataExport;
use AppConnect\Routes\CourseDatePlacesShow;
use AppConnect\Routes\DevicesCreate;
use AppConnect\Routes\DiscoveryShow;
use AppConnect\Routes\IcalKeyDelete;
use AppConnect\Routes\IcalKeyShow;
use AppConnect\Routes\LogoShow;
use AppConnect\Routes\TokenCreate;
use AppConnect\Routes\TokenRevocationCreate;
use AppConnect\Routes\UserEventsShow;
use AppConnect\Routes\UserLanguageShow;
use AppConnect\Routes\WebLoginCreate;
use AppConnect\Runtime;
use AppConnect\Settings\OperatorConfigListener;
use JsonApi\Contracts\JsonApiPlugin;
use Slim\Routing\RouteCollectorProxy;

/**
 * Connects the mobile apps to this instance: the public discovery and the
 * institution logo it names, the token and revocation endpoints of the
 * shared app client, device registration,
 * the one-time web login from the app, the page "Stud.App Verbindung" under
 * Profile → Settings and the operator settings in the configuration section
 * "Stud.App" (Admin → System → Konfiguration).
 */
class AppConnect extends StudIPPlugin implements SystemPlugin, JsonApiPlugin, PrivacyPlugin
{
    /** Settings entry of the page "Stud.App Verbindung". */
    public const NAVIGATION_PATH = '/profile/settings/app_connect';

    public function __construct()
    {
        parent::__construct();

        // The app client refreshes only at the plugin's endpoint, so removing
        // a device always reaches its current tokens.
        CoreRefreshGuard::apply(static fn (): ?string => Runtime::settings()->clientId());

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
            (new OperatorConfigListener((int) $this->getPluginId()))->changed(is_array($change) ? $change : []);
        });

        $perm = $GLOBALS['perm'] ?? null;
        if (!is_object($perm)) {
            return;
        }
        // Own entry in the settings navigation, right above "Drittanwendungen";
        // connected apps appear in the core list there by themselves.
        if ($perm->have_perm('autor') && Navigation::hasItem('/profile/settings') && $this->profileEntryIsOn()) {
            $entry = new Navigation($this->_('Stud.App Verbindung'), PluginEngine::getURL($this, [], 'connect'));
            if (Navigation::hasItem('/profile/settings/oauth2')) {
                Navigation::insertItem(self::NAVIGATION_PATH, $entry, 'oauth2');
            } else {
                Navigation::addItem(self::NAVIGATION_PATH, $entry);
            }
        }
    }

    /**
     * Disabling the plugin in the plugin administration revokes the app
     * client: without the plugin there is no device list left to end a
     * connection, so no app may keep its access. The switch in the
     * configuration stays as it is.
     */
    public static function onDisable($plugin_id)
    {
        (new ClientProvisioner(Runtime::settings()))->revoke();
    }

    /**
     * Enabling the plugin again lifts the revocation while the app
     * connection is switched on in the configuration.
     */
    public static function onEnable($plugin_id)
    {
        $settings = Runtime::settings();
        if ($settings->isConnected()) {
            (new ClientProvisioner($settings))->provision(Runtime::baseUrl() . '/');
        }
    }

    /**
     * Loads one of the plugin's scripts (assets/js) on the current page,
     * deferred until the page is parsed. The pages carry no inline script.
     */
    public function addPageScript(string $file): void
    {
        $this->addScript('assets/js/' . $file, ['defer' => '']);
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
        $group->get('/app-connect/logo', LogoShow::class);
        $group->post('/app-connect/token', TokenCreate::class);
        $group->post('/app-connect/revoke', TokenRevocationCreate::class);
    }

    public function registerSchemas(): array
    {
        return [];
    }

    /** Devices, pairing codes and web-login tickets of the person, for the core's data export. */
    public function exportUserData(StoredUserData $storage)
    {
        UserDataExport::export($storage);
    }

    private function profileEntryIsOn(): bool
    {
        $settings = Runtime::settings();
        return $settings->isConnected() && $settings->isProfileEntryEnabled();
    }
}
