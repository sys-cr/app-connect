<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Runtime;

/** What the logo box on "Stud.App Verbindung" shows root (views/logo/_box.php). */
final class LogoBox
{
    /**
     * @return array{configured: ?string, status: ?StoredLogo, messages: list<array{0: string, 1: string}>, preview: ?string}
     */
    public static function data(): array
    {
        $url = Runtime::configuredLogoUrl();
        $status = Runtime::logo()->status($url);
        return [
            'configured' => $url,
            'status' => $status,
            'messages' => LogoStatusText::describe($status),
            'preview' => $status?->image !== null
                ? Runtime::baseUrl() . '/jsonapi.php/v1/' . DiscoveryBuilder::LOGO_ROUTE . '?v=' . $status->image->version()
                : null,
        ];
    }

    private function __construct()
    {
    }
}
