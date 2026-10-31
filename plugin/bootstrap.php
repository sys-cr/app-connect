<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

// Classes live under lib/classes in the AppConnect namespace (PSR-4). The
// autoloader is registered here so the plugin does not depend on how the
// Stud.IP autoloader maps namespaces.
spl_autoload_register(static function (string $class): void {
    $prefix = 'AppConnect\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/lib/classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
