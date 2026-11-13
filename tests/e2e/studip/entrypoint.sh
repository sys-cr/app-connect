#!/bin/bash
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Bodo Steffen
# Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
#
# Installs a fresh Stud.IP with the plugin for the end-to-end tests, then
# starts the web server: configuration, core database and migrations, the
# plugin, the OAuth2 keys, the test accounts and the app connection.
set -euo pipefail

cd /var/www/studip
PLUGIN_DIR=public/plugins_packages/studapp/AppConnect
: "${E2E_BASE_URL:?}"

# The installation answers at / and below /studip/ (Apache alias); the base
# address follows the path of the request. The command line gets the root.
cat > config/config_local.inc.php <<PHP
<?php
define('Studip\\\\ENV', 'development');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
\$CONTENT_LANGUAGES['en_GB'] = ['picture' => 'lang_en.gif', 'name' => 'English'];
\$MAIL_TRANSPORT = 'debug';
\$E2E_BELOW_PATH = str_starts_with(\$_SERVER['REQUEST_URI'] ?? '', '/studip/');
\$ABSOLUTE_URI_STUDIP = '${E2E_BASE_URL%/}/' . (\$E2E_BELOW_PATH ? 'studip/' : '');
\$CANONICAL_RELATIVE_PATH_STUDIP = \$E2E_BELOW_PATH ? '/studip/' : '/';
PHP
[ -f config/config.inc.php ] || cp config/config.inc.php.dist config/config.inc.php

cat > /etc/apache2/conf-enabled/app-connect-e2e.conf <<'CONF'
Alias /studip/ /var/www/studip/public/
CONF

sql() {
    mysql --skip-ssl --default-character-set=utf8mb4 -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" "$@"
}
until sql -e 'SELECT 1' >/dev/null 2>&1; do sleep 1; done

if [ "$(sql -N -e 'SHOW TABLES' | wc -l)" -eq 0 ]; then
    echo "Installing the Stud.IP database"
    sql < db/studip-base-installation.sql
    sql < db/studip-root-user.sql
fi
php cli/studip migrate

name=$(sed -n 's/^pluginname=//p' "$PLUGIN_DIR/plugin.manifest")
php cli/studip plugin:register "$PLUGIN_DIR"
php cli/studip plugin:activate "$name"
php cli/studip plugin:migrate "$name"

[ -f config/oauth2/private.key ] || php cli/studip oauth2:keys

# The web server writes caches and reads the keys; the helper runs as it too.
chown -R www-data: data config/oauth2
su www-data -s /bin/sh -c 'php /opt/app-connect-e2e/cli.php setup'

exec "$@"
