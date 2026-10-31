<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use AppConnect\Audit\EventLog;
use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Settings\Settings;
use Studip\OAuth2\Models\Client;

/**
 * Manages the one public OAuth2 client all app installations of the instance
 * share. The client is found by its stored id only, never by name.
 */
final class ClientProvisioner
{
    public const CLIENT_NAME = 'Stud.App';
    public const CLIENT_OWNER = 'Stud.App';
    public const CLIENT_DESCRIPTION = 'Mobile apps. Managed by the app-connect plugin – please do not edit it here.';

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Switches the app connection on: provides the client and sets the switch.
     * Returns the client id.
     */
    public function enable(string $homepage): string
    {
        $clientId = $this->provision($homepage);
        $this->settings->set(Settings::ENABLED, true);
        return $clientId;
    }

    /** Switches the app connection off: revokes the client and clears the switch. */
    public function disable(): void
    {
        $this->revoke();
        $this->settings->set(Settings::ENABLED, false);
    }

    /**
     * Reuses the stored client (lifting a revocation, bringing name and owner
     * up to date) or creates a new public PKCE client. Leaves the switch
     * alone: it runs when root switched it on in the configuration.
     */
    public function provision(string $homepage): string
    {
        // Switching on happens in root's request, so its address is trusted;
        // later requests may carry any Host header.
        if (self::isBaseUrl($homepage)) {
            $this->settings->set(Settings::BASE_URL, rtrim($homepage, '/'));
        }
        $client = $this->storedClient();
        if ($client !== null) {
            $client->revoked = 0;
            $client->name = self::CLIENT_NAME;
            $client->owner = self::CLIENT_OWNER;
            if ($client->isDirty()) {
                $client->store();
            }
            EventLog::record(EventLog::CLIENT_REUSE, 'client ' . $client->id);
        } else {
            $client = Client::createClient(
                self::CLIENT_NAME,
                DiscoveryBuilder::REDIRECT_URI,
                false,
                self::CLIENT_OWNER,
                $homepage,
                self::CLIENT_DESCRIPTION,
                null
            );
            $this->settings->set(Settings::OAUTH_CLIENT_ID, (string) $client->id);
            EventLog::record(EventLog::CLIENT_CREATE, 'client ' . $client->id);
        }
        EventLog::record(EventLog::ENABLE);
        return (string) $client->id;
    }

    /**
     * Revokes the client, not deleting it, so existing app tokens stop working
     * and a later switch-on can reuse it.
     */
    public function revoke(): void
    {
        $client = $this->storedClient();
        if ($client !== null && (int) $client->revoked === 0) {
            $client->revoked = 1;
            $client->store();
        }
        EventLog::record(EventLog::DISABLE);
    }

    public static function isBaseUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('~^https?://[^\s/?#@]+(/[^\s?#]*)?$~', $value) === 1;
    }

    /** The client the settings point to, if it still exists. */
    public function storedClient(): ?Client
    {
        $id = $this->settings->clientId();
        return $id !== null ? Client::find($id) : null;
    }
}
