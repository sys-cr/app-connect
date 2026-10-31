<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use AppConnect\Devices\DeviceService;
use DateInterval;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The core refresh grant, plus: the device bound to the old refresh token is
 * moved to the new pair in the same transaction, so the binding always points
 * at the current tokens of the lineage.
 *
 * The core page "Drittanwendungen" revokes only the access token. A refresh
 * token whose access token was revoked there is therefore refused here, the
 * refresh token is revoked and the device counts as removed.
 */
final class LineageRefreshTokenGrant extends RefreshTokenGrant
{
    private ?string $oldRefreshTokenId = null;
    private ?string $newAccessTokenId = null;
    private ?string $newRefreshTokenId = null;
    private ?string $revokedInCore = null;

    public function __construct(RefreshTokenRepositoryInterface $refreshTokenRepository, private readonly DeviceService $devices)
    {
        parent::__construct($refreshTokenRepository);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $response = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
            if ($this->oldRefreshTokenId !== null && $this->newAccessTokenId !== null) {
                $this->devices->rebind($this->oldRefreshTokenId, $this->newAccessTokenId, $this->newRefreshTokenId);
            }
            $db->commit();
            return $response;
        } catch (Throwable $exception) {
            $db->rollBack();
            if ($this->revokedInCore !== null) {
                $this->devices->endRevokedLineage($this->revokedInCore);
            }
            throw $exception;
        }
    }

    protected function validateOldRefreshToken(ServerRequestInterface $request, $clientId)
    {
        $data = parent::validateOldRefreshToken($request, $clientId);
        // A normal refresh revokes both tokens, so a revoked access token with
        // a valid refresh token means a revocation in the core.
        if ($this->devices->isAccessTokenRevoked((string) $data['access_token_id'])) {
            $this->revokedInCore = (string) $data['refresh_token_id'];
            throw OAuthServerException::invalidRefreshToken('Token has been revoked');
        }
        $this->oldRefreshTokenId = (string) $data['refresh_token_id'];
        return $data;
    }

    protected function issueAccessToken(
        DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        $userIdentifier,
        array $scopes = []
    ) {
        $token = parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
        $this->newAccessTokenId = (string) $token->getIdentifier();
        return $token;
    }

    protected function issueRefreshToken(AccessTokenEntityInterface $accessToken)
    {
        $token = parent::issueRefreshToken($accessToken);
        $this->newRefreshTokenId = $token !== null ? (string) $token->getIdentifier() : null;
        return $token;
    }
}
