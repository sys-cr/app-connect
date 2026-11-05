<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

use AppConnect\Attribution;
use AppConnect\Connect\ConnectLink;
use AppConnect\Help;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;

/**
 * Public landing page "App holen", the target of the QR code on the profile
 * page. It renders no QR code and never consumes the pairing code: a script
 * reads the code from the URL fragment and builds "Open in the app".
 */
class GetController extends PluginController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        $this->response->add_header('Cache-Control', 'no-store');
        $this->response->add_header('Referrer-Policy', 'no-referrer');
        $this->response->add_header('X-Frame-Options', 'DENY');
        $this->response->add_header('Content-Security-Policy', "frame-ancestors 'none'");
    }

    public function index_action()
    {
        $settings = Runtime::settings();
        $this->enabled = $settings->isEnabled() && $settings->clientId() !== null;
        PageLayout::setTitle($this->_('App holen'));
        Help::addToHelpbar(Help::GET);
        Attribution::addToHelpbar();
        // The core shows the help bar from `autor` on; visitors who are not
        // signed in get the notice at the end of the page instead.
        $this->attribution = is_object($GLOBALS['perm']) && $GLOBALS['perm']->have_perm('autor')
            ? null
            : Attribution::html((string) Icon::create('link-extern', Icon::ROLE_INFO)
                ->asImg(16, ['style' => 'vertical-align: text-bottom;', 'alt' => '']), false);

        $base = parse_url(Runtime::baseUrl());
        // The host comes from ABSOLUTE_URI_STUDIP. Where a site has not set it,
        // Stud.IP derives it from the request; the page is sent `no-store`.
        $host = (string) ($base['host'] ?? '') . (isset($base['port']) ? ':' . $base['port'] : '');
        $this->connect_link = ConnectLink::connectLink($host, null);
        $this->instance_name = $settings->arrayValue(Settings::INSTANCE)['name'] ?? ($GLOBALS['UNI_NAME_CLEAN'] ?? 'Stud.IP');

        $links = $settings->arrayValue(Settings::STORE_LINKS);
        $this->store_links = array_filter([
            'appStore' => $links['appStore'] ?? null,
            'googlePlay' => $links['googlePlay'] ?? null,
        ], static fn ($url): bool => is_string($url) && preg_match('~^https://[^\s/?#@]+(/\S*)?$~', $url) === 1);
        $privacy = $settings->stringValue(Settings::PRIVACY_POLICY_URL);
        $this->privacy_url = $privacy !== null && str_starts_with($privacy, 'https://') ? $privacy : null;
    }
}
