# Entwicklerhandbuch

**Deutsch** · [English](#english)

## Aufbau des Plugins

| Pfad | Inhalt |
|---|---|
| `plugin/plugin.manifest` | Name `app-connect`, Klasse `AppConnect`, Herkunft `studapp` |
| `plugin/AppConnect.php` | Plugin-Klasse (`SystemPlugin`, `JsonApiPlugin`): Routen und Navigationseinträge (Admin, Profil-Einstellungen) |
| `plugin/lib/classes/` | Namensraum `AppConnect\`, siehe Komponenten unten |
| `plugin/controllers/`, `plugin/views/` | Profilseite (`connect`), öffentliche Landing-Page (`get`), Anmeldung im Web aus der App (`web`: `enter`, `redeem`, `confirm`); die Betreiber-Einstellungen sind Konfigurationseinträge der Sektion „Stud.App“ (`Settings\OperatorConfig`, Migration `004`) |
| `plugin/migrations/` | Datenbank-Migrationen (`NNN_name.php`); `003` registriert den Aufräum-Cronjob, `010` und `011` setzen die Aufbewahrung der Ereignisprotokoll-Einträge zu Web-Anmeldung und Kalender-Schlüssel, `015` legt die Tabelle der Logo-Kopie an, `016` den Schalter der Web-Anmeldung für admin und root, `017` die Tabelle der Zähler |
| `plugin/cronjobs/` | `AppConnectCleanupJob` (tägliches Aufräumen, abgelaufene Zähler, Logo neu abrufen); lädt `bootstrap.php` selbst, weil der Cron-Runner das Plugin nicht lädt |
| `plugin/tests/` | PHPUnit: `Unit` (Logik ohne Stud.IP), `Contract` (Schema, gemeinsame Test-Vektoren) `Integration` (gegen MariaDB) und `E2E` (gegen Stud.IP in Docker); `Fixtures/logo` mit kleinen Bildern, `Support/FakeHttpFetcher` beantwortet Abrufe ohne Netz |
| `plugin/LICENSE`, `plugin/SUPPLEMENTAL-TERMS.txt` | Kopien von `LICENSES/GPL-3.0-or-later.txt` und `SUPPLEMENTAL-TERMS.txt`, damit das Plugin-Verzeichnis beide mitbringt; `AttributionTest` hält sie gleich |

Komponenten in `plugin/lib/classes/`:

| Namensraum | Aufgabe |
|---|---|
| `Settings` | Betreiber-Einstellungen (Konfigurationssektion „Stud.App“) und die eigene Einstellungstabelle; Prüfung von Adressen, Farben, IDs |
| `Discovery` | die öffentliche Discovery aus einer Feld-Allowlist, `ETag`/`304` |
| `Logo` | das Logo der Hochschule auf dem eigenen Server: Abruf mit SSRF-Schutz (`LogoFetch`, `AddressGuard`, `CurlHttpFetcher` mit festgelegter Adresse), Prüfung an den Bytes (`LogoImage`: PNG, JPEG, WebP, 512 KB, 32 bis 2048 Pixel), Kopie in `app_connect_logo` (`DatabaseLogoStore`), letzte gute Kopie bei Fehlern und hochgeladenes Logo mit Vorrang (`LogoService`), Antwort der Route (`LogoResponse`) |
| `OAuth2` | der eine öffentliche App-Client (`ClientProvisioner`), der Token-Endpunkt (`TokenServer`), der Kopplungs-Grant und der Refresh-Grant, der die Gerätebindung mitführt; der Widerruf nach RFC 7009 (`TokenRevocation`, `TokenInspector` prüft Access-Tokens gegen den öffentlichen Schlüssel des Kerns, `RefreshTokenCipher` entschlüsselt Refresh-Tokens mit dem Schlüssel des Kerns) |
| `Pairing`, `Connect` | Kopplungscodes (nur Hash, fünf Minuten, die erste Einlösung gewinnt) und der Verbindungslink des QR-Codes |
| `Devices` | Gerätezeilen, gebunden an eine Token-Kette; Entfernen und Widerruf in den Token-Tabellen des Kerns (`TokenLedger`), Aufbewahrung |
| `WebLogin` | einmalige Tickets für die Anmeldung im Web, Prüfung von `return_to`, die Anmeldung wie im Kern-Login |
| `Calendar` | der Kalender der Person in den Ressourcen der Kern-Route `users/{id}/events` mit Kursfarben (`UserEvents`); Abo-Adresse des Kalender-Schlüssels (`IcalSubscription`), neue Schlüssel und ihre Grenze (`IcalKey`, `IcalKeyLimit`) |
| `Places`, `Profile` | Orte von Terminen mit Koordinaten, die Profilsprache |
| `RateLimit`, `Audit`, `Mail` | Grenzen je Adresse und für die Instanz (`Limits`; Adressen als HMAC mit dem Schlüssel der Installation; atomare Zähler in `app_connect_counters`, `DatabaseCounterStore`), Aktionen des Ereignisprotokolls, die Verbindungsmail |
| `Routes` | schlanke JSON:API-Controller der Routen unten; `AppToken` lässt nur das Token des App-Clients zu |
| `Help` | kurze Hilfetexte für die Hilfelasche von `connect` und `get` |
| `ContentBox` | Stil der h2-Überschriften in den Inhaltskästen, mit den Werten des Kerns |
| `Attribution` | rechtliche Hinweise im Sinne der GPL (Nennung, Copyright, Lizenz mit Zusatzbedingungen, Weitergabe, keine Gewährleistung) mit Link zur Lizenz; setzt sie ans Ende der Hilfelasche von `connect` und `get` |
| `Runtime` | Verbindung zum laufenden Stud.IP (Einstellungen, Basis-Adresse, Instanzdaten) |

Klassen ohne Abhängigkeit von Stud.IP (`Discovery\DiscoveryBuilder`,
`Discovery\DiscoveryResponse`, `Connect\*`, `Settings\Settings`, `Calendar\UserEvents`,
`Calendar\IcalSubscription`, `Calendar\IcalKey`, `Calendar\IcalKeyLimit`,
`WebLogin\WebLoginService`, `RateLimit\RateLimiter`, `Logo\LogoImage`, `Logo\LogoFetch`,
`Logo\AddressGuard`, `Logo\LogoService`, `Logo\LogoResponse`) sind mit
PHPUnit abgedeckt; die schlanke Stud.IP-Schicht (`Runtime`, `Routes\*`,
`OAuth2\ClientProvisioner`, `Devices\TokenLedger`, `Logo\CurlHttpFetcher`,
`Logo\DatabaseLogoStore`, die Controller) wird gegen ein laufendes
Stud.IP getestet.

## Tests

```bash
make lint     # php -l
make test     # PHPUnit, beide Suiten (installiert beim ersten Lauf die Dev-Abhängigkeiten)
make test-integration   # gegen eine MariaDB 10.11 in Docker, die danach wieder verschwindet
make test-e2e STUDIP=6.0   # gegen Stud.IP 6.0 in Docker (6.0 bis 6.3, ohne STUDIP 6.3)
```

Die Integrationstests laufen gegen eine echte Datenbank. Die Tabellen des Plugins legen
seine eigenen Migrationen an, von den OAuth2-Tabellen des Kerns nur die Spalten, die das
Plugin liest. Gleichzeitige Anfragen erzeugen sie mit mehreren Prozessen (`pcntl`), jeder
mit eigener Verbindung. Ohne `APP_CONNECT_TEST_DB` (PDO-DSN, dazu `…_USER` und
`…_PASSWORD`) überspringt `make test` sie. GitHub Actions führt `make lint` und `make test`
mit PHP 8.1 bis 8.3 aus und die Integrationstests mit einer MariaDB als Dienst.

Die End-to-End-Tests (`plugin/tests/E2E`) laufen gegen ein echtes Stud.IP mit dem Plugin.
`tests/e2e/run.sh` baut dafür ein Image aus den Quellen des Kerns mit dem Dockerfile, das
der Kern mitbringt (`docker/studip/Dockerfile`, Stände in `run.sh` festgelegt; beim ersten
Mal einige Minuten), startet es mit einer MariaDB im Arbeitsspeicher, installiert Stud.IP,
bindet `plugin/` als Plugin ein (registrieren, aktivieren, migrieren), legt die
OAuth2-Schlüssel und Testkonten an und schaltet die App-Verbindung über
`Config::get()->store()` ein, also über denselben Hook wie die Konfiguration im Browser.
Dieselbe Installation antwortet unter `http://localhost:846<Minor>/` und unter `…/studip/`
(Apache-Alias, `$ABSOLUTE_URI_STUDIP` mit `/studip/`). Die Tests sprechen HTTP mit der
Instanz; Testdaten legt `tests/e2e/studip/cli.php` im Container an. Abgedeckt sind:

- Discovery und Landing-Page an der Wurzel und unter `/studip` (Endpunkte und
  Verbindungslink tragen den Basispfad), Discovery gegen das Schema, `ETag`/`304`
- Kopplung: Code einlösen, mit dem Token eine Route der Kern-JSON:API aufrufen, denselben
  Code erneut einlösen (abgelehnt), unbekannter Code, fremder Client
- Erneuern am Endpunkt des Plugins (neue Tokens, altes Refresh-Token abgelehnt, Gerät folgt
  der Kette) und Ablehnung am Token-Endpunkt des Kerns (`invalid_grant`, `CoreRefreshGuard`)
- Widerruf über `app-connect/revoke` mit Refresh- und Access-Token: Paar und Gerät enden
- gesperrtes Konto: die nächste Erneuerung scheitert, das Gerät gilt als entfernt;
  Kopplung abgelehnt
- Ausschalten von `APP_CONNECT_ENABLED`: Discovery `404`, alle Tokens ungültig, auch nach
  dem Wiedereinschalten; ein Basispfad außerhalb der Regeln lässt die Verbindung aus
- Anmeldung im Web aus der App unter `/studip` bis zur Sitzung, damit im Profil ein
  QR-Code, dessen Adresse den Basispfad trägt und dessen Code ein Gerät verbindet; für root
  nur mit `APP_CONNECT_WEB_LOGIN_PRIVILEGED`

Nicht abgedeckt sind Browser-Anmeldung (`authorization_code` mit PKCE), Identity Provider
und Zwei-Faktor-Prüfung, Kalender-Schlüssel, Ablauf eines Kontos, Upgrade, Deaktivieren
und Reaktivieren des Plugins sowie die Apps selbst. Ein abgelehntes Refresh-Token
beantwortet die OAuth2-Bibliothek des Kerns mit `401 invalid_request`.

```bash
tests/e2e/run.sh up 6.3     # Instanz starten und stehen lassen
tests/e2e/run.sh test 6.3   # Suite gegen die laufende Instanz, PHPUnit-Argumente möglich
tests/e2e/run.sh down 6.3   # Instanz entfernen
```

`E2E_STUDIP_IMAGE` nimmt ein vorhandenes Image statt eines eigenen Builds, `E2E_PORT`
einen anderen Port. Ohne `APP_CONNECT_E2E_URL` und `APP_CONNECT_E2E_CONTAINER` überspringt
`make test` die Suite. GitHub Actions führt sie für Stud.IP 6.0 und 6.3 aus und legt die
gebauten Images im Cache ab. Im Plugin-ZIP sind die Tests nicht enthalten.

Der Plugin-Code muss auf den PHP-Versionen aller unterstützten Stud.IP-Versionen laufen
(PHP 8.1 und neuer); `php -l` daher auch mit der ältesten ausführen. Werkzeuge: PHP 8.1+,
Composer (Dev-Abhängigkeiten aus `plugin/composer.lock`), git 2.40+ (`make dist` nutzt
`git archive --mtime`).

## Übersetzung

Die Texte sind deutsch und stehen in der Textdomäne `app-connect` (`localedomain` im
Manifest). Plugin-Klasse und Controller übersetzen mit `$this->_()`, Views mit `$_()`, Code
ohne Plugin-Instanz (Cronjob, Mail, Datenauskunft) mit `Translation::text()`. Die
Oberfläche siezt. Die englischen Texte stehen in
`plugin/locale/en/LC_MESSAGES/app-connect.po`; `make locale` übersetzt sie mit `msgfmt` in
die `.mo`-Datei, die mit eingecheckt wird. `LocaleTest` schlägt fehl, wenn ein Text im Code
keine Übersetzung hat, eine Übersetzung nicht mehr gebraucht wird oder die `.mo`-Datei
veraltet ist.

## Migrationen

Eine Migration enthält die Werte, die sie anlegt – Konfigurationseinträge (`FIELDS`),
Aktionen des Ereignisprotokolls (`ACTIONS`) –, statt sie aus den Klassen des Plugins zu
lesen, wie die Migrationen des Kerns. Eine spätere Änderung an `OperatorConfig` oder
`EventLog` braucht deshalb eine neue Migration. `MigrationsTest` prüft, dass alle
Migrationen zusammen genau die Einträge und Aktionen anlegen, die der Code erwartet.

## Installation in ein lokales Stud.IP

Das Plugin-Verzeichnis muss `public/plugins_packages/studapp/AppConnect` heißen (Herkunft
und Klassenname aus dem Manifest). `plugin/` ohne `vendor/` und `tests/` dorthin kopieren,
dann im Stud.IP-Verzeichnis:

```bash
php cli/studip plugin:register public/plugins_packages/studapp/AppConnect
php cli/studip plugin:activate app-connect
```

Das Registrieren führt die Migrationen aus. Danach als `root` anmelden, Admin → System →
Konfiguration öffnen, Sektion „Stud.App“, und `APP_CONNECT_ENABLED` einschalten. Das Plugin
reagiert auf `ConfigValueDidChange`: Dabei entsteht der OAuth2-Client, und das Plugin wird
für Anfragen ohne Sitzung freigegeben (Rolle „Nobody“), die Discovery und Token-Endpunkt
brauchen.

`make dist` packt das eingecheckte `plugin/` ohne `tests/`, `composer.json`,
`composer.lock` und `phpunit.xml` nach
`dist/app-connect-<version>.zip` für den Plugin-Upload unter Admin → System → Plugins; das
Archiv enthält immer `LICENSE` und `SUPPLEMENTAL-TERMS.txt`, und derselbe Commit ergibt
immer dieselbe Datei.

Ergebnis prüfen:

```bash
curl -i http://localhost:8032/jsonapi.php/v1/app-connect/discovery
```

Eine lokale Instanz über einfaches `http` akzeptieren die Apps nur in DEBUG-Builds und nur
auf `localhost` oder `127.0.0.1`.

## Routen

| Route | Anmeldung | Zweck |
|---|---|---|
| `GET /jsonapi.php/v1/app-connect/discovery` | keine | öffentliche Instanzdaten, `ETag`/`304`; `404 app-connect-disabled`, solange ausgeschaltet |
| `GET /jsonapi.php/v1/app-connect/logo` | keine | die geprüfte Kopie des Logos (`Content-Type` aus den Bytes, `nosniff`, `Content-Security-Policy: default-src 'none'`, einen Tag cachebar, `ETag` = SHA-256, `304`); Discovery `branding.logoUrl` mit `?v=` (die ersten 16 Zeichen des SHA-256); `404 app-connect-no-logo` ohne gültige Kopie |
| `POST /jsonapi.php/v1/app-connect/token` | keine (OAuth2-Client) | Token-Endpunkt des App-Clients: `authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing` |
| `POST /jsonapi.php/v1/app-connect/revoke` | keine (OAuth2-Client) | Widerruf nach RFC 7009 beim Abmelden ([`contract/token-revocation.md`](../../contract/token-revocation.md)); Discovery `oauth2.revocationEndpoint`; `200` (auch für unbekannte Tokens), `400`, `401`, `404`, `503` |
| `POST /jsonapi.php/v1/app-connect/devices` | OAuth2-Token des App-Clients | Registrierung nach einer Browser-Anmeldung; `201`, `401`, `403`, `409`, `422` |
| `GET /jsonapi.php/v1/app-connect/course-dates/places` | angemeldet | gebuchte Räume, Koordinaten und Raumfinder-Links von bis zu 50 Terminen ([`contract/course-date-places.md`](../../contract/course-date-places.md)); Discovery-Funktion `eventPlaces` |
| `GET /jsonapi.php/v1/app-connect/user-language` | angemeldet | Profilsprache der Person, sonst die Vorgabe der Instanz ([`contract/user-language.md`](../../contract/user-language.md)); Discovery-Funktion `userLanguage` |
| `GET /jsonapi.php/v1/app-connect/user-events` | angemeldet | der Kalender der Person für zwei Wochen: eigene Termine, Veranstaltungstermine, Ausfälle, mit der Kursfarbe `color` ([`contract/user-events.md`](../../contract/user-events.md)); Discovery-Funktion `userEvents`; `200`, `400`, `401`, `404` |
| `POST /jsonapi.php/v1/app-connect/web-login` | OAuth2-Token des App-Clients oder HTTP Basic (Passwort prüft das Plugin) | einmaliger Link (60 s), der die Person im Web anmeldet und `return_to` öffnet ([`contract/web-login.md`](../../contract/web-login.md)); Discovery-Funktion `webLogin`; `201`, `400`, `401`, `403`, `404`, `429` |
| `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` | OAuth2-Token des App-Clients | der eigene Kalender-Schlüssel der Person, beim ersten Abruf angelegt, und das Zurücksetzen ([`contract/ical-key.md`](../../contract/ical-key.md)); Discovery-Funktion `icalKey`; `200`, `204`, `401`, `403`, `404`, `429` |
| `plugins.php/appconnect/web/enter`, `…/web/redeem`, `…/web/confirm` | keine | der Link von `web-login`: Das Skript schickt das Ticket aus dem Fragment an `redeem`, das es einlöst; ist dieselbe Person angemeldet, wird weitergeleitet, sonst bestätigt die Person „Als … anmelden?“, und `confirm` meldet an |
| `plugins.php/appconnect/connect` | Sitzung, `autor` | „Stud.App Verbindung“ unter Profil → Einstellungen, über Drittanwendungen: Kopplungscode, Geräteliste |
| `plugins.php/appconnect/get` | keine | Landing-Page des QR-Codes; Code im Fragment `#p=` |

### Kopplungs-Grant

```
POST /jsonapi.php/v1/app-connect/token
grant_type=urn:studip:app-connect:pairing&client_id=…&code=…
&device_id=<uuid>&device_name=…&platform=ios|android
```

Die Antwort ist die übliche Token-Antwort. Fehler: `invalid_grant` für einen unbekannten,
benutzten oder abgelaufenen Code, `429 slow_down` nach zu vielen Fehlversuchen.

### Token-Kette

Es gibt einen OAuth2-Client für alle Installationen. Ein Gerät ist an die Kette seiner
Tokens gebunden: Jede Erneuerung über den Endpunkt des Plugins verschiebt die Bindung auf
das neue Refresh-Token (`LineageRefreshTokenGrant`). Entfernen widerruft die gebundenen
Tokens. Eine Erneuerung am Token-Endpunkt des Kerns würde die Kette unterbrechen; das
Plugin lehnt sie für den App-Client deshalb ab (`CoreRefreshGuard`, als Systemplugin vor dem
Controller des Kerns geladen). Ist eine Kette trotzdem gebrochen (gebundenes Refresh-Token
widerrufen, Gerät noch aktiv), beendet das Entfernen zusätzlich alle gültigen Anmeldungen
der Person, die keinem aktiven Gerät gehören: lieber mehr als weniger.

Erneuern, Entfernen und Widerrufen sperren in derselben Reihenfolge: zuerst die Gerätezeile
(`SELECT … FOR UPDATE`), dann die Zeile des Refresh-Tokens. Der Refresh prüft nach dem
Sperren den Widerruf erneut; findet er die Gerätezeile beim Umbinden nicht mehr aktiv,
rollt er zurück und gibt keine Tokens aus (`LineageEnded`). So entsteht weder durch zwei
gleichzeitige Erneuerungen noch durch Erneuern während des Entfernens eine gültige
Token-Familie ohne Gerät.

Die Kernseite „Drittanwendungen“ widerruft nur das Access-Token. Eine normale Erneuerung
widerruft beide; ein widerrufenes Access-Token mit gültigem Refresh-Token bedeutet also
einen Widerruf im Kern: Der Refresh-Grant des Plugins lehnt das Refresh-Token dann ab,
widerruft es und markiert das Gerät als entfernt. Am Token-Endpunkt des Kerns erneuert der
App-Client nicht (siehe oben); die Apps nutzen den Endpunkt des Plugins aus der Discovery.

Der Widerrufs-Endpunkt (`app-connect/revoke`) beendet ein Paar wie das Entfernen auf
„Stud.App Verbindung“: Ist ein Gerät an das Paar gebunden, wird es mit allen Nachfolgern
entfernt; das genannte Paar wird in jedem Fall widerrufen.

---

## English

### Layout of the plugin

| Path | Content |
|---|---|
| `plugin/plugin.manifest` | name `app-connect`, class `AppConnect`, origin `studapp` |
| `plugin/AppConnect.php` | plugin class (`SystemPlugin`, `JsonApiPlugin`): routes and the navigation entries (admin, profile settings) |
| `plugin/lib/classes/` | namespace `AppConnect\`, see the components below |
| `plugin/controllers/`, `plugin/views/` | profile page (`connect`), public landing page (`get`), web login from the app (`web`: `enter`, `redeem`, `confirm`); the operator settings are configuration entries of the section "Stud.App" (`Settings\OperatorConfig`, migration `004`) |
| `plugin/migrations/` | database migrations (`NNN_name.php`); `003` registers the cleanup cronjob, `010` and `011` set the retention of the event log entries about the web login and the calendar key, `015` creates the table of the logo copy, `016` the switch of the web login for admin and root, `017` the table of the counters |
| `plugin/cronjobs/` | `AppConnectCleanupJob` (daily retention cleanup, expired counters, fetching the logo again); loads `bootstrap.php` itself because the cron runner does not load the plugin |
| `plugin/tests/` | PHPUnit: `Unit` (logic without Stud.IP), `Contract` (schema, shared test vectors) `Integration` (against MariaDB) and `E2E` (against Stud.IP in Docker); `Fixtures/logo` holds tiny pictures, `Support/FakeHttpFetcher` answers fetches without network |
| `plugin/LICENSE`, `plugin/SUPPLEMENTAL-TERMS.txt` | copies of `LICENSES/GPL-3.0-or-later.txt` and `SUPPLEMENTAL-TERMS.txt`, so the plugin directory ships both; `AttributionTest` keeps them identical |

Components in `plugin/lib/classes/`:

| Namespace | Responsibility |
|---|---|
| `Settings` | operator settings (configuration section "Stud.App") and the plugin's own settings table; validation of addresses, colours, ids |
| `Discovery` | the public discovery payload from a field allowlist, `ETag`/`304` |
| `Logo` | the institution logo on the instance's own server: fetch with SSRF protection (`LogoFetch`, `AddressGuard`, `CurlHttpFetcher` pinned to the checked address), checks on the bytes (`LogoImage`: PNG, JPEG, WebP, 512 KB, 32 to 2048 pixels), the copy in `app_connect_logo` (`DatabaseLogoStore`), the last good copy on failure and an uploaded logo that takes precedence (`LogoService`), the route's answer (`LogoResponse`) |
| `OAuth2` | the one public app client (`ClientProvisioner`), the token endpoint (`TokenServer`), the pairing grant and the refresh grant that keeps the device binding; revocation per RFC 7009 (`TokenRevocation`; `TokenInspector` checks access tokens against the core's public key, `RefreshTokenCipher` decrypts refresh tokens with the core's key) |
| `Pairing`, `Connect` | pairing codes (hash only, five minutes, first redemption wins) and the connect link of the QR code |
| `Devices` | device rows bound to a token lineage, removal and revocation in the core token tables (`TokenLedger`), retention |
| `WebLogin` | one-time tickets for the web login, `return_to` checks, the sign-in like the core login |
| `Calendar` | the person's calendar in the shapes of the core route `users/{id}/events`, with course colours (`UserEvents`); the subscription address of the calendar key (`IcalSubscription`), new keys and their limit (`IcalKey`, `IcalKeyLimit`) |
| `Places`, `Profile` | places of course dates with coordinates, the profile language |
| `RateLimit`, `Audit`, `Mail` | limits per address and for the instance (`Limits`; addresses as an HMAC with the installation's key; atomic counters in `app_connect_counters`, `DatabaseCounterStore`), event log actions, the connection mail |
| `Routes` | thin JSON:API controllers of the routes below; `AppToken` admits only the app client's token |
| `Help` | short help texts for the help bar of `connect` and `get` |
| `ContentBox` | style of the h2 headings in the content boxes, with the core's values |
| `Attribution` | the legal notices in the GPL's sense (attribution, copyright, license with additional terms, conveying, no warranty) with a link to the license; adds them to the end of the help bar of `connect` and `get` |
| `Runtime` | wiring to the running Stud.IP (settings, base URL, instance data) |

Classes without a Stud.IP dependency (`Discovery\DiscoveryBuilder`,
`Discovery\DiscoveryResponse`, `Connect\*`, `Settings\Settings`, `Calendar\UserEvents`,
`Calendar\IcalSubscription`, `Calendar\IcalKey`, `Calendar\IcalKeyLimit`,
`WebLogin\WebLoginService`, `RateLimit\RateLimiter`, `Logo\LogoImage`, `Logo\LogoFetch`,
`Logo\AddressGuard`, `Logo\LogoService`, `Logo\LogoResponse`) are
covered by PHPUnit; the thin Stud.IP layer (`Runtime`, `Routes\*`,
`OAuth2\ClientProvisioner`, `Devices\TokenLedger`, `Logo\CurlHttpFetcher`,
`Logo\DatabaseLogoStore`, the controllers) is tested against a
running Stud.IP.

### Tests

```bash
make lint     # php -l
make test     # PHPUnit, both suites (installs the dev dependencies on first run)
make test-integration   # against MariaDB 10.11 in Docker, removed again afterwards
make test-e2e STUDIP=6.0   # against Stud.IP 6.0 in Docker (6.0 to 6.3, 6.3 without STUDIP)
```

The integration tests run against a real database. The plugin's own migrations create its
tables; of the core's OAuth2 tables only the columns the plugin reads are created.
Concurrent requests come from several processes (`pcntl`), each with its own connection.
Without `APP_CONNECT_TEST_DB` (a PDO DSN, plus `…_USER` and `…_PASSWORD`) `make test`
skips them. GitHub Actions runs `make lint` and `make test` with PHP 8.1 to 8.3 and the
integration tests with MariaDB as a service.

The end-to-end tests (`plugin/tests/E2E`) run against a real Stud.IP with the plugin.
`tests/e2e/run.sh` builds an image from the core sources with the Dockerfile the core
ships (`docker/studip/Dockerfile`, versions pinned in `run.sh`; a few minutes the first
time), starts it with MariaDB in memory, installs Stud.IP, mounts `plugin/` as the plugin
(register, activate, migrate), creates the OAuth2 keys and test accounts and switches the
app connection on through `Config::get()->store()`, so through the same hook as the
configuration in the browser. The same installation answers at
`http://localhost:846<minor>/` and at `…/studip/` (Apache alias, `$ABSOLUTE_URI_STUDIP`
with `/studip/`). The tests talk HTTP to the instance; `tests/e2e/studip/cli.php` prepares
data inside the container. Covered:

- discovery and landing page at the root and below `/studip` (endpoints and connect link
  carry the base path), discovery against the schema, `ETag`/`304`
- pairing: redeem a code, call a route of the core JSON:API with the token, redeem the
  same code again (refused), unknown code, foreign client
- refresh at the plugin's endpoint (new tokens, old refresh token refused, the device
  follows the lineage) and the refusal at the core token endpoint (`invalid_grant`,
  `CoreRefreshGuard`)
- revocation through `app-connect/revoke` with refresh and access token: pair and device
  end
- locked account: the next refresh fails, the device counts as removed; pairing refused
- switching `APP_CONNECT_ENABLED` off: discovery `404`, every token invalid, also after
  switching on again; a base path outside the rules keeps the connection off
- the web login from the app below `/studip` up to the session, then on the profile page a
  QR code whose address carries the base path and whose code connects a device; for root
  only with `APP_CONNECT_WEB_LOGIN_PRIVILEGED`

Not covered: the browser sign-in (`authorization_code` with PKCE), identity providers and
the two-factor check, the calendar key, an expired account, upgrade, disabling and
re-enabling the plugin, and the apps themselves. The core's OAuth2 library answers a
refused refresh token with `401 invalid_request`.

```bash
tests/e2e/run.sh up 6.3     # start an instance and keep it running
tests/e2e/run.sh test 6.3   # the suite against the running instance, PHPUnit arguments allowed
tests/e2e/run.sh down 6.3   # remove the instance
```

`E2E_STUDIP_IMAGE` takes an existing image instead of building one, `E2E_PORT` another
port. Without `APP_CONNECT_E2E_URL` and `APP_CONNECT_E2E_CONTAINER`, `make test` skips the
suite. GitHub Actions runs it for Stud.IP 6.0 and 6.3 and caches the built images. The
plugin ZIP does not contain the tests.

The plugin code must run on the PHP versions of every supported Stud.IP release (PHP 8.1
and later); run `php -l` with the oldest one as well. Tools: PHP 8.1+, Composer (dev
dependencies from `plugin/composer.lock`), git 2.40+ (`make dist` uses
`git archive --mtime`).

### Translation

The texts are German and live in the text domain `app-connect` (`localedomain` in the
manifest). The plugin class and controllers translate with `$this->_()`, views with
`$_()`, code without a plugin instance (cronjob, mail, data export) with
`Translation::text()`. The interface addresses people formally ("Sie"). The English texts
are in `plugin/locale/en/LC_MESSAGES/app-connect.po`; `make locale` compiles them with
`msgfmt` into the committed `.mo` file. `LocaleTest` fails when a text in the code has no
translation, a translation is no longer used or the `.mo` file is out of date.

### Migrations

A migration contains the values it creates – configuration entries (`FIELDS`), event log
actions (`ACTIONS`) – instead of reading them from the plugin's classes, like the core's
migrations. A later change to `OperatorConfig` or `EventLog` therefore needs a new
migration. `MigrationsTest` checks that all migrations together create exactly the entries
and actions the code expects.

### Installing into a local Stud.IP

The plugin directory must be `public/plugins_packages/studapp/AppConnect` (origin and
class name from the manifest). Copy `plugin/` there without `vendor/` and `tests/`, then,
inside the Stud.IP checkout:

```bash
php cli/studip plugin:register public/plugins_packages/studapp/AppConnect
php cli/studip plugin:activate app-connect
```

Registering runs the migrations. Then sign in as `root`, open Admin → System →
Konfiguration, section "Stud.App", and set `APP_CONNECT_ENABLED` to on. The plugin reacts
to `ConfigValueDidChange`: this creates the OAuth2 client and allows the plugin for
requests without a session (role "Nobody"), which the discovery and the token endpoint
need.

`make dist` packs the committed `plugin/` without `tests/`, `composer.json`,
`composer.lock` and `phpunit.xml` into
`dist/app-connect-<version>.zip` for the plugin upload under Admin → System → Plugins; the
archive always contains `LICENSE` and `SUPPLEMENTAL-TERMS.txt`, and the same commit always
yields the same file.

Check the result:

```bash
curl -i http://localhost:8032/jsonapi.php/v1/app-connect/discovery
```

A local instance over plain `http` is accepted by the apps only in DEBUG builds and only on
`localhost` or `127.0.0.1`.

### Routes

| Route | Authentication | Purpose |
|---|---|---|
| `GET /jsonapi.php/v1/app-connect/discovery` | none | public instance data, `ETag`/`304`; `404 app-connect-disabled` while switched off |
| `GET /jsonapi.php/v1/app-connect/logo` | none | the checked copy of the logo (`Content-Type` from the bytes, `nosniff`, `Content-Security-Policy: default-src 'none'`, cacheable for a day, `ETag` = SHA-256, `304`); discovery `branding.logoUrl` with `?v=` (the first 16 characters of the SHA-256); `404 app-connect-no-logo` without a valid copy |
| `POST /jsonapi.php/v1/app-connect/token` | none (OAuth2 client) | token endpoint of the app client: `authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing` |
| `POST /jsonapi.php/v1/app-connect/revoke` | none (OAuth2 client) | revocation per RFC 7009 on sign-out ([`contract/token-revocation.md`](../../contract/token-revocation.md)); discovery `oauth2.revocationEndpoint`; `200` (unknown tokens as well), `400`, `401`, `404`, `503` |
| `POST /jsonapi.php/v1/app-connect/devices` | OAuth2 token of the app client | registration after a browser sign-in; `201`, `401`, `403`, `409`, `422` |
| `GET /jsonapi.php/v1/app-connect/course-dates/places` | signed in | booked rooms, coordinates and room finder links of up to 50 course dates ([`contract/course-date-places.md`](../../contract/course-date-places.md)); discovery feature `eventPlaces` |
| `GET /jsonapi.php/v1/app-connect/user-language` | signed in | profile language of the person, else the site's default ([`contract/user-language.md`](../../contract/user-language.md)); discovery feature `userLanguage` |
| `GET /jsonapi.php/v1/app-connect/user-events` | signed in | the person's calendar for two weeks: personal entries, course dates, cancellations, with the course colour `color` ([`contract/user-events.md`](../../contract/user-events.md)); discovery feature `userEvents`; `200`, `400`, `401`, `404` |
| `POST /jsonapi.php/v1/app-connect/web-login` | OAuth2 token of the app client, or HTTP Basic (password checked by the plugin) | one-time link (60 s) that signs the person in on the web and opens `return_to` ([`contract/web-login.md`](../../contract/web-login.md)); discovery feature `webLogin`; `201`, `400`, `401`, `403`, `404`, `429` |
| `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` | OAuth2 token of the app client | the person's own calendar key, created on first ask, and the reset ([`contract/ical-key.md`](../../contract/ical-key.md)); discovery feature `icalKey`; `200`, `204`, `401`, `403`, `404`, `429` |
| `plugins.php/appconnect/web/enter`, `…/web/redeem`, `…/web/confirm` | none | the link of `web-login`: the script posts the ticket from the fragment to `redeem`, which claims it; the same person signed in is redirected, anyone else confirms "Als … anmelden?" and `confirm` signs in |
| `plugins.php/appconnect/connect` | session, `autor` | "Stud.App connection" under Profile → Settings, above Drittanwendungen: pairing code, device list |
| `plugins.php/appconnect/get` | none | landing page of the QR code; code in the fragment `#p=` |

#### Pairing grant

```
POST /jsonapi.php/v1/app-connect/token
grant_type=urn:studip:app-connect:pairing&client_id=…&code=…
&device_id=<uuid>&device_name=…&platform=ios|android
```

The response is the usual token response. Errors: `invalid_grant` for an unknown, used or
expired code, `429 slow_down` after too many failures.

#### Token lineage

There is one OAuth2 client for all installations. A device is bound to the lineage of its
tokens: every refresh through the plugin's endpoint moves the binding to the new refresh
token (`LineageRefreshTokenGrant`). Removing a device revokes the bound tokens. A refresh
at the core's token endpoint would break the chain, so the plugin refuses it for the app
client (`CoreRefreshGuard`, loaded as a system plugin before the core's controller). If a
chain is broken all the same (bound refresh token revoked, device still active), removal
also ends every valid sign-in of the person that belongs to no active device: more rather
than fewer.

Refresh, removal and revocation lock in the same order: the device row first (`SELECT …
FOR UPDATE`), then the refresh token row. The refresh checks the revocation again after
locking; if the device row is no longer active when it rebinds, it rolls back and hands out
no tokens (`LineageEnded`). Neither two simultaneous refreshes nor a refresh during removal
can leave a valid token family without a device.

The core page "Drittanwendungen" revokes only the access token. A normal refresh revokes
both, so a revoked access token with a valid refresh token means a revocation in the core:
the plugin's refresh grant then refuses the refresh token, revokes it and marks the device
as removed. The app client does not refresh at the core token endpoint (see above); the
apps use the plugin's endpoint from the discovery.

The revocation endpoint (`app-connect/revoke`) ends a pair as removing it on "Stud.App
connection" does: a device bound to the pair is removed with all successors; the named pair
is revoked either way.
