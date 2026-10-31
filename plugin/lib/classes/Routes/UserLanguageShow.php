<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Profile\UserLanguage;
use AppConnect\Runtime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/user-language – the language the signed-in
 * person uses Stud.IP in, so the apps can follow it. The core API does not
 * carry the profile language.
 */
final class UserLanguageShow extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        if (!Runtime::settings()->isEnabled()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        $user = $this->getUser($request);
        if (!$user) {
            return JsonErrors::write($response, 401, 'app-connect-sign-in-required', 'Sign-in required');
        }

        $installed = array_keys((array) ($GLOBALS['INSTALLED_LANGUAGES'] ?? []));
        $default = (string) (\Config::get()->getValue('DEFAULT_LANGUAGE') ?: 'de_DE');
        $data = UserLanguage::resolve(
            isset($user->preferred_language) ? (string) $user->preferred_language : null,
            array_map('strval', $installed),
            $default
        );

        $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
