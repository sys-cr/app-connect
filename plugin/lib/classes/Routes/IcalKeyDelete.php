<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Audit\EventLog;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /jsonapi.php/v1/app-connect/me/ical-key – drops the iCal key of
 * the signed-in person (contract/ical-key.md).
 *
 * The key is a standing read access to the calendar, so there has to be a
 * way back: once it has been passed on, or a device was lost, dropping it
 * stops every subscription built on it. The next `GET` makes a new one.
 *
 * Idempotent: a person without a key gets the same `204`, so the app does
 * not have to know whether one existed.
 */
final class IcalKeyDelete extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled() || $settings->clientId() === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        if (($settings->arrayValue(Settings::FEATURES)['icalKey'] ?? true) === false) {
            return JsonErrors::write($response, 404, 'app-connect-ical-key-disabled', 'The calendar key is switched off');
        }
        $user = $this->getUser($request);
        if (!$user) {
            return JsonErrors::write($response, 401, 'app-connect-sign-in-required', 'Sign-in required');
        }
        $userId = (string) $user->id;
        $refusal = AppToken::refusal($request, $response, (string) $settings->clientId(), $userId);
        if ($refusal !== null) {
            return $refusal;
        }

        if (\IcalExport::getKeyByUser($userId)) {
            \IcalExport::deleteKey($userId);
            EventLog::recordForUser(EventLog::ICAL_KEY_RESET, $userId);
        }

        return $response->withStatus(204)->withHeader('Cache-Control', 'no-store');
    }
}
