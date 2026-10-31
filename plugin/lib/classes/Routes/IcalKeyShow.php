<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Audit\EventLog;
use AppConnect\Calendar\IcalSubscription;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/me/ical-key – the iCal key of the
 * signed-in person, so the app can subscribe the system calendar in one
 * step (contract/ical-key.md).
 *
 * Stud.IP's own JSON:API has no route for the key: it is created on the
 * publish page of the calendar and otherwise lives in the person's
 * configuration, which means the way to the subscription leads through the
 * browser and a copied string. The route exists for that gap only; the key
 * itself and its generator belong to the core (`IcalExport`).
 *
 * The key is a standing read access to the person's calendar for whoever
 * holds it, so: only the token holder's own key is ever returned, the
 * answer is never cached, and a missing key is created here rather than
 * on a page the person has to find – they asked for the subscription.
 */
final class IcalKeyShow extends \JsonApi\NonJsonApiController
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

        $key = \IcalExport::getKeyByUser($userId);
        if (!IcalSubscription::isValidKey($key)) {
            $key = \IcalExport::setKey($userId);
            EventLog::recordForUser(EventLog::ICAL_KEY_CREATE, $userId);
        }
        if (!IcalSubscription::isValidKey($key)) {
            return JsonErrors::write($response, 500, 'app-connect-ical-key-failed', 'The calendar key could not be prepared');
        }

        $payload = IcalSubscription::payload(Runtime::baseUrl(), (string) $key);
        $response->getBody()->write(json_encode(['data' => $payload], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}
