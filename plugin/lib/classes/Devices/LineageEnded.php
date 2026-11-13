<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

use RuntimeException;

/**
 * A refresh found its lineage ended while it held the locks: the refresh
 * token was revoked or the device removed. The refresh must fail and its
 * transaction roll back, so no new pair goes out.
 */
final class LineageEnded extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The token lineage has ended');
    }
}
