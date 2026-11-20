<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

/*
 * Test helper inside the Stud.IP container of the end-to-end tests. It
 * bootstraps Stud.IP and loads the plugin, so a change of a configuration
 * entry runs the plugin's hook exactly as in Admin -> System -> Konfiguration.
 * Prints JSON. Never point it at a production installation.
 *
 *   setup                     test accounts, pairing on, app connection on
 *   state                     whether the app connection is on, the client id
 *   config FIELD VALUE        stores a configuration entry (true/false for switches)
 *   enable-at URL             switches on with ABSOLUTE_URI_STUDIP = URL
 *   code USERNAME             a pairing code for the account, stored as its hash
 *   lock USERNAME 1|0         locks or unlocks the account
 *   devices USERNAME          the device rows of the account
 *   reset-limits              clears the counters of the rate limits
 *   export USERNAME           the plugin's part of the data export of the account
 *   mails TO                  the subjects of the mails to TO in the debug mail log
 */

declare(strict_types=1);

use AppConnect\Connect\PairingCode;
use AppConnect\Runtime;
use AppConnect\Settings\OperatorConfig;

require '/var/www/studip/cli/studip_cli_env.inc.php';
require_once '/var/www/studip/composer/autoload.php';
require_once '/var/www/studip/public/plugins_packages/studapp/AppConnect/bootstrap.php';

/** username => [perms, first name, last name] */
const E2E_USERS = [
    'e2e_autor' => ['autor', 'Anna', 'Autorin'],
    'e2e_locked' => ['autor', 'Lars', 'Gesperrt'],
    'e2e_web' => ['autor', 'Wiebke', 'Weblogin'],
    'e2e_password' => ['autor', 'Paul', 'Passwort'],
];

/** The hash of the password "testing", as for root@studip in db/studip-root-user.sql. */
const E2E_PASSWORD_HASH = '$2a$08$E2oDjsPcmCrnJT3GiSvHGugLhyUc1mlpcB3FstDzgMwgnIdzi8iSW';

function out(mixed $value): void
{
    echo json_encode($value, JSON_UNESCAPED_SLASHES), "\n";
}

function user(string $username): User
{
    return User::findByUsername($username) ?? throw new RuntimeException("No account $username");
}

/** The plugin hooks into ConfigValueDidChange in its constructor. */
function loadPlugin(): void
{
    $plugin = PluginEngine::getPlugin('AppConnect');
    if ($plugin === null) {
        throw new RuntimeException('The plugin is not active');
    }
}

function store(string $field, mixed $value): void
{
    loadPlugin();
    Config::get()->store($field, $value);
}

function state(): array
{
    $settings = Runtime::settings();
    return ['enabled' => $settings->isEnabled(), 'clientId' => $settings->clientId()];
}

function switchValue(string $value): bool|string
{
    return match ($value) {
        'true' => true,
        'false' => false,
        default => $value,
    };
}

$args = array_slice($argv, 1);
switch ($args[0] ?? '') {
    case 'setup':
        foreach (E2E_USERS as $username => [$perms, $first, $last]) {
            $account = User::findByUsername($username) ?? User::build([
                'username' => $username,
                'perms' => $perms,
                'Vorname' => $first,
                'Nachname' => $last,
                'Email' => $username . '@example.org',
                'auth_plugin' => 'standard',
                'visible' => 'yes',
            ]);
            $account->password = E2E_PASSWORD_HASH;
            $account->store();
            UserConfig::get($account->id)->store('TERMS_ACCEPTED', 1);
        }
        store(OperatorConfig::PAIRING_ENABLED, true);
        if (!Runtime::settings()->isEnabled()) {
            store(OperatorConfig::ENABLED, true);
        }
        out(state());
        break;
    case 'state':
        out(state());
        break;
    case 'config':
        store((string) $args[1], switchValue((string) ($args[2] ?? '')));
        out(state());
        break;
    case 'enable-at':
        $GLOBALS['ABSOLUTE_URI_STUDIP'] = (string) $args[1];
        store(OperatorConfig::ENABLED, true);
        out(state());
        break;
    case 'code':
        $code = PairingCode::generate();
        DBManager::get()->execute(
            'INSERT INTO `app_connect_pairing_codes` (`code_hash`, `user_id`, `expires_at`, `mkdate`) VALUES (?, ?, ?, ?)',
            [PairingCode::hash($code), user((string) $args[1])->id, time() + 300, time()]
        );
        out(['code' => $code]);
        break;
    case 'lock':
        $account = user((string) $args[1]);
        $account->locked = (int) ($args[2] ?? 1);
        $account->store();
        out(['locked' => (bool) $account->locked]);
        break;
    case 'devices':
        out(DBManager::get()->fetchAll(
            'SELECT `id`, `device_id`, `paired_via`, `access_token_id`, `refresh_token_id`, `revoked_at`
               FROM `app_connect_devices` WHERE `user_id` = ? ORDER BY `id`',
            [user((string) $args[1])->id]
        ));
        break;
    case 'export':
        $storage = new StoredUserData(user((string) $args[1])->id);
        AppConnect\Privacy\UserDataExport::export($storage);
        out(array_map(
            static fn (array $table): array => ['key' => $table['key'], 'rows' => $table['value']],
            $storage->getTabularData()
        ));
        break;
    case 'mails':
        // The debug transport writes each mail as a block that starts with "-- <time>".
        $log = rtrim((string) $GLOBALS['TMP_PATH'], '/') . '/' . ($GLOBALS['DEBUG_MAIL_LOG_FILE_NAME'] ?? 'studip-mail-debug.log');
        $subjects = [];
        foreach (preg_split('/^-- /m', is_readable($log) ? (string) file_get_contents($log) : '') ?: [] as $block) {
            // 6.0 writes "To:  <address>" and an encoded subject, 6.3 both plain.
            if (preg_match('/^To:\s*<?' . preg_quote((string) $args[1], '/') . '>?\s*$/m', $block) === 1
                && preg_match('/^Subject: (.*)$/m', $block, $match) === 1) {
                $subject = trim($match[1]);
                $subjects[] = str_contains($subject, '=?')
                    ? (string) iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8')
                    : $subject;
            }
        }
        out($subjects);
        break;
    case 'reset-limits':
        DBManager::get()->exec('DELETE FROM `app_connect_counters`');
        out(['ok' => true]);
        break;
    default:
        fwrite(STDERR, "Unknown command\n");
        exit(2);
}
