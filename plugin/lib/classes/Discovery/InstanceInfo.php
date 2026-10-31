<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Discovery;

/**
 * Facts about the running instance that do not come from the plugin settings.
 */
final class InstanceInfo
{
    /**
     * @param string $baseUrl       e.g. https://studip.example.org (no trailing slash)
     * @param string $defaultName   fallback name when the operator set none
     * @param string $studipVersion e.g. 6.2.3
     * @param string $pluginVersion from plugin.manifest
     * @param string $locale        e.g. de_DE
     * @param bool   $coursewareModule whether the core module Courseware is active
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $defaultName,
        public readonly string $studipVersion,
        public readonly string $pluginVersion,
        public readonly string $locale,
        public readonly bool $coursewareModule = false,
    ) {
    }
}
