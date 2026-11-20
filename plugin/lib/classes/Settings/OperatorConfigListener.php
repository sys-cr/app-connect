<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

use AppConnect\Audit\EventLog;
use AppConnect\Connect\ConnectLink;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Help;
use AppConnect\Logo\LogoStatusText;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\Runtime;
use AppConnect\Translation;

/**
 * Reacts to changes in the configuration section "Stud.App" (Admin → System
 * → Konfiguration): switching the app connection on provides the app client,
 * switching it off ends every connection; a new logo address is fetched at
 * once, and values the plugin cannot use get a warning.
 */
final class OperatorConfigListener
{
    /** @param string $pluginId the plugin, to open it for requests without a session */
    public function __construct(private readonly int $pluginId)
    {
    }

    /** @param array{field?: string, new_value?: mixed} $change */
    public function changed(array $change): void
    {
        $field = (string) ($change['field'] ?? '');
        if (!array_key_exists($field, OperatorConfig::definitions())) {
            return;
        }
        if ($field === OperatorConfig::ENABLED) {
            in_array((string) ($change['new_value'] ?? ''), ['1', 'true'], true) ? $this->switchedOn() : $this->switchedOff();
            return;
        }
        EventLog::record(EventLog::SETTINGS_CHANGE, $field);
        $value = trim((string) ($change['new_value'] ?? ''));
        if ($field === OperatorConfig::LOGO_URL) {
            $this->logoChanged($value);
        }
        if ($field === OperatorConfig::SUPPORT_EMAIL && !OperatorConfig::isEmail($value)) {
            // The apps then send content reports to the project's own address.
            \PageLayout::postInfo(htmlReady(Help::missingSupportAddress()));
        }
        $problem = $value === '' ? null : $this->fieldProblem($field, $value);
        if ($problem !== null) {
            \PageLayout::postWarning(sprintf(Translation::text('%s wird nicht verwendet: %s'), htmlReady($field), $problem));
        }
    }

    /** Provides the app client, unless the base path cannot go into the connect link. */
    private function switchedOn(): void
    {
        if (ConnectLink::basePathOf(Runtime::baseUrl()) === null) {
            // Switching back off calls the hook again, which revokes.
            \Config::get()->store(OperatorConfig::ENABLED, false);
            \PageLayout::postError(Translation::text('Die App-Verbindung bleibt aus: Der Pfad von Stud.IP in ABSOLUTE_URI_STUDIP lässt sich nicht in den Verbindungslink übernehmen. Erlaubt sind bis zu acht Abschnitte aus Buchstaben, Ziffern und den Zeichen - . _ ~, etwa https://example.org/studip/.'));
            return;
        }
        (new ClientProvisioner(Runtime::settings()))->provision(Runtime::baseUrl() . '/');
        $this->allowRequestsWithoutSession();
    }

    /**
     * Revokes the app client and ends every connection for good: switching
     * on again must not bring old tokens back. Disabling the plugin only
     * pauses (onDisable).
     */
    private function switchedOff(): void
    {
        (new ClientProvisioner(Runtime::settings()))->revoke();
        $clientId = Runtime::settings()->clientId();
        if ($clientId === null) {
            return;
        }
        $count = (new DeviceService(new TokenLedger(), $clientId))->endAll();
        EventLog::record(EventLog::DISABLE, 'connections ended: ' . $count);
        if ($count > 0) {
            \PageLayout::postInfo(htmlReady(Translation::text('Alle Verbindungen der App sind beendet. Nach dem Einschalten muss jede Person die App neu verbinden.')));
        }
    }

    /** Why a non-empty value of $field is not used, or null when it is fine. */
    private function fieldProblem(string $field, string $value): ?string
    {
        return match (true) {
            in_array($field, OperatorConfig::URL_FIELDS, true) && !OperatorConfig::isHttpsUrl($value)
                => Translation::text('Die Adresse muss mit https:// beginnen.'),
            $field === OperatorConfig::SUPPORT_EMAIL && !OperatorConfig::isEmail($value)
                => Translation::text('Das ist keine gültige E-Mail-Adresse.'),
            $field === OperatorConfig::ACCENT_COLOR && !OperatorConfig::isHexColor($value)
                => Translation::text('Die Farbe muss die Form #RRGGBB haben.'),
            $field === OperatorConfig::MENSA_OPENMENSA_IDS && OperatorConfig::canteenIds($value) === null
                => Translation::text('Erwartet werden OpenMensa-IDs (Zahlen), durch Komma getrennt.'),
            default => null,
        };
    }

    /**
     * A new logo address is fetched at once, so the operator learns right
     * after saving whether the apps get the picture; an empty or non-https
     * address deletes the copy.
     */
    private function logoChanged(string $url): void
    {
        LogoStatusText::post(Runtime::logo($url)->refresh($url));
    }

    /**
     * The discovery, the logo, the token and the revocation endpoint are called
     * without a session, so the plugin must be loaded for the "Nobody" role
     * as well.
     */
    private function allowRequestsWithoutSession(): void
    {
        $roleIds = [];
        foreach (\RolePersistence::getAllRoles() as $role) {
            if ($role->getRolename() === 'Nobody') {
                $roleIds[] = $role->getRoleid();
            }
        }
        if ($roleIds !== []) {
            \RolePersistence::assignPluginRoles($this->pluginId, $roleIds);
        }
    }
}
