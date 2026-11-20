<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Runtime;
use AppConnect\Settings\Settings;
use Psr\Container\ContainerInterface;

/**
 * A plugin route of the JSON:API. The core's container builds it with the
 * container; the settings come in through the constructor, from
 * {@see Runtime} unless a caller hands in others.
 */
abstract class Route extends \JsonApi\NonJsonApiController
{
    protected readonly Settings $settings;

    public function __construct(ContainerInterface $container, ?Settings $settings = null)
    {
        parent::__construct($container);
        $this->settings = $settings ?? Runtime::settings();
    }
}
