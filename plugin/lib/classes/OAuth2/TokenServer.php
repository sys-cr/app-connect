<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use AppConnect\Devices\DeviceRecord;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Mail\ConnectedMail;
use AppConnect\Pairing\PairingService;
use AppConnect\RateLimit\RateLimiter;
use AppConnect\RateLimit\StudipCacheCounterStore;
use AppConnect\Settings\Settings;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * The authorization server behind the plugin's token endpoint: the core
 * server with its keys and repositories, the authorization code grant
 * unchanged, the refresh grant replaced by the lineage-aware one and the
 * pairing grant added.
 */
final class TokenServer
{
    public static function create(Settings $settings, string $clientAddress): AuthorizationServer
    {
        $container = new \Studip\OAuth2\Container();
        $server = $container->get(AuthorizationServer::class);
        $refreshTokens = $container->get(RefreshTokenRepositoryInterface::class);
        $devices = new DeviceService(new TokenLedger(), (string) $settings->clientId());

        $refresh = new LineageRefreshTokenGrant($refreshTokens, $devices);
        $refresh->setRefreshTokenTTL(new DateInterval('P1M'));
        $server->enableGrantType($refresh, new DateInterval('PT1H'));

        $server->enableGrantType(
            new PairingGrant(
                $refreshTokens,
                $settings,
                new PairingService(),
                $devices,
                new RateLimiter(new StudipCacheCounterStore()),
                $clientAddress,
                static function (string $userId, DeviceRecord $device): void {
                    ConnectedMail::send($userId, $device);
                }
            ),
            new DateInterval('PT1H')
        );
        return $server;
    }
}
