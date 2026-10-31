<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use AppConnect\Connect\PairingCode;
use AppConnect\Devices\DeviceInput;
use AppConnect\Devices\DeviceRecord;
use AppConnect\Devices\DeviceService;
use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Pairing\PairingService;
use AppConnect\RateLimit\RateLimiter;
use AppConnect\Settings\Settings;
use DateInterval;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Redeems a pairing code for tokens of the shared app client
 * (`grant_type=urn:studip:app-connect:pairing`). Only for pairing; every other
 * sign-in goes through the system browser.
 */
final class PairingGrant extends AbstractGrant
{
    public const IDENTIFIER = 'urn:studip:app-connect:pairing';

    /** Accounts that may connect the app. */
    private const PERMISSIONS = ['autor', 'tutor', 'dozent', 'admin', 'root'];

    /** @var \Closure(string, DeviceRecord): void|null */
    private ?\Closure $onConnected;

    /**
     * @param \Closure(string, DeviceRecord): void|null $onConnected runs after the commit (the mail)
     */
    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly Settings $settings,
        private readonly PairingService $pairing,
        private readonly DeviceService $devices,
        private readonly RateLimiter $limiter,
        private readonly string $clientAddress,
        ?\Closure $onConnected = null
    ) {
        $this->setRefreshTokenRepository($refreshTokenRepository);
        $this->setRefreshTokenTTL(new DateInterval('P1M'));
        $this->onConnected = $onConnected;
    }

    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        if (!$this->settings->isPairingEnabled()) {
            throw OAuthServerException::unsupportedGrantType();
        }
        $clientId = (string) $this->getRequestParameter('client_id', $request, '');
        if ($clientId === '' || $clientId !== $this->settings->clientId()) {
            throw OAuthServerException::invalidClient($request);
        }
        // The core client repository only validates its own grant types, so
        // the client is looked up directly; only active clients are returned.
        $client = $this->getClientEntityOrFail($clientId, $request);

        if ($this->limiter->isBlocked($this->clientAddress)) {
            throw new OAuthServerException('Too many failed attempts, try again later.', 12, 'slow_down', 429);
        }
        $device = DeviceInput::fromValues(
            $this->getRequestParameter('device_id', $request),
            $this->getRequestParameter('device_name', $request),
            $this->getRequestParameter('platform', $request)
        );
        if ($device === null) {
            throw OAuthServerException::invalidRequest('device_id', 'device_id, device_name and platform are required');
        }
        $code = PairingCode::normalize((string) $this->getRequestParameter('code', $request, ''));

        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $userId = $code !== null ? $this->pairing->redeem($code, $device->deviceId) : null;
            if ($userId === null || !$this->mayConnect($userId)) {
                // A redeemed code of an account that may no longer connect stays used.
                $db->commit();
                $this->limiter->recordFailure($this->clientAddress);
                throw OAuthServerException::invalidGrant();
            }
            $scopes = $this->scopeRepository->finalizeScopes(
                $this->validateScopes(implode(self::SCOPE_DELIMITER_STRING, DiscoveryBuilder::SCOPES)),
                $this->getIdentifier(),
                $client,
                $userId
            );
            $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $userId, $scopes);
            $refreshToken = $this->issueRefreshToken($accessToken);
            $row = $this->devices->connect(
                $userId,
                $device,
                DeviceService::VIA_PAIRING,
                (string) $accessToken->getIdentifier(),
                $refreshToken !== null ? (string) $refreshToken->getIdentifier() : null
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $responseType->setAccessToken($accessToken);
        if ($refreshToken !== null) {
            $responseType->setRefreshToken($refreshToken);
        }
        if ($this->onConnected !== null) {
            ($this->onConnected)($userId, $row);
        }
        return $responseType;
    }

    private function mayConnect(string $userId): bool
    {
        $user = \User::find($userId);
        return $user !== null
            && (int) $user->locked === 0
            && in_array($user->perms, self::PERMISSIONS, true);
    }
}
