<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Audit\EventLog;
use AppConnect\Calendar\IcalKey;
use AppConnect\Calendar\IcalKeyLimit;
use AppConnect\Calendar\IcalSubscription;
use AppConnect\RateLimit\DatabaseCounterStore;
use AppConnect\Runtime;
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
 * browser and a copied string. The route exists for that gap; the key lives
 * where the core keeps it, so the core's export serves it unchanged.
 *
 * The key is a standing read access to the person's calendar for whoever
 * holds it, so: only the token holder's own key is ever returned, the
 * answer is never cached, and a missing key is created here rather than
 * on a page the person has to find – they asked for the subscription. A
 * key created here comes from {@see IcalKey}, not from the core's weaker
 * generator, and only {@see IcalKeyLimit::MAX_CREATIONS} an hour. A key the
 * person already has stays, since replacing it would end their
 * subscriptions; a reset gives them a new one.
 */
final class IcalKeyShow extends Route
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = $this->settings;
        if (!$settings->isConnected()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        if (!$settings->isFeatureOn('icalKey')) {
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
            $limit = new IcalKeyLimit(new DatabaseCounterStore());
            if (!$limit->allows($userId)) {
                return JsonErrors::write($response, 429, 'app-connect-too-many-requests', 'Too many new calendar keys, try again later')
                    ->withHeader('Retry-After', (string) $limit->retryAfter());
            }
            $key = IcalKey::unused(static fn (string $candidate): bool => \IcalExport::getUserIdByKey($candidate) !== false);
            if ($key !== null) {
                \UserConfig::get($userId)->store('ICAL_EXPORT_KEY', $key);
                $limit->record($userId);
                EventLog::recordForUser(EventLog::ICAL_KEY_CREATE, $userId);
            }
        }
        if (!IcalSubscription::isValidKey($key)) {
            return JsonErrors::write($response, 500, 'app-connect-ical-key-failed', 'The calendar key could not be prepared');
        }

        $payload = IcalSubscription::payload(Runtime::baseUrl(), (string) $key);
        return JsonResponse::write($response, ['data' => $payload])
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}
