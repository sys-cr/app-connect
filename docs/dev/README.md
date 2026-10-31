# Entwicklerhandbuch

**Deutsch** · [English](#english)

## Aufbau des Plugins

| Pfad | Inhalt |
|---|---|
| `plugin/plugin.manifest` | Name `app-connect`, Klasse `AppConnect`, Herkunft `studapp` |
| `plugin/AppConnect.php` | Plugin-Klasse (`SystemPlugin`, `JsonApiPlugin`): Routen und Navigationseinträge (Admin, Profil-Einstellungen) |
| `plugin/lib/classes/` | Namensraum `AppConnect\`, siehe Komponenten unten |
| `plugin/controllers/`, `plugin/views/` | Profilseite (`connect`), öffentliche Landing-Page (`get`), Anmeldung im Web aus der App (`web`: `enter`, `redeem`, `confirm`); die Betreiber-Einstellungen sind Konfigurationseinträge der Sektion „Stud.App“ (`Settings\OperatorConfig`, Migration `004`) |
| `plugin/migrations/` | Datenbank-Migrationen (`NNN_name.php`); `003` registriert den Aufräum-Cronjob, `010` und `011` setzen die Aufbewahrung der Ereignisprotokoll-Einträge zu Web-Anmeldung und Kalender-Schlüssel |
| `plugin/cronjobs/` | `AppConnectCleanupJob` (tägliches Aufräumen); lädt `bootstrap.php` selbst, weil der Cron-Runner das Plugin nicht lädt |
| `plugin/tests/` | PHPUnit: `Unit` (Logik ohne Stud.IP) und `Contract` (Schema, gemeinsame Test-Vektoren) |
| `plugin/LICENSE`, `plugin/SUPPLEMENTAL-TERMS.txt` | Kopien von `LICENSES/GPL-3.0-or-later.txt` und `SUPPLEMENTAL-TERMS.txt`, damit das Plugin-Verzeichnis beide mitbringt; `AttributionTest` hält sie gleich |

Komponenten in `plugin/lib/classes/`:

| Namensraum | Aufgabe |
|---|---|
| `Settings` | Betreiber-Einstellungen (Konfigurationssektion „Stud.App“) und die eigene Einstellungstabelle; Prüfung von Adressen, Farben, IDs |
| `Discovery` | die öffentliche Discovery aus einer Feld-Allowlist, `ETag`/`304` |
| `OAuth2` | der eine öffentliche App-Client (`ClientProvisioner`), der Token-Endpunkt (`TokenServer`), der Kopplungs-Grant und der Refresh-Grant, der die Gerätebindung mitführt |
| `Pairing`, `Connect` | Kopplungscodes (nur Hash, fünf Minuten, die erste Einlösung gewinnt) und der Verbindungslink des QR-Codes |
| `Devices` | Gerätezeilen, gebunden an eine Token-Kette; Entfernen und Widerruf in den Token-Tabellen des Kerns (`TokenLedger`), Aufbewahrung |
| `WebLogin` | einmalige Tickets für die Anmeldung im Web, Prüfung von `return_to`, die Anmeldung wie im Kern-Login |
| `Calendar` | der Kalender der Person in den Ressourcen der Kern-Route `users/{id}/events` mit Kursfarben (`UserEvents`); Abo-Adresse des Kalender-Schlüssels (`IcalSubscription`) |
| `Places`, `Profile` | Orte von Terminen mit Koordinaten, die Profilsprache |
| `RateLimit`, `Audit`, `Mail` | Grenzen für Fehlversuche, Aktionen des Ereignisprotokolls, die Verbindungsmail |
| `Routes` | schlanke JSON:API-Controller der Routen unten; `AppToken` lässt nur das Token des App-Clients zu |
| `Attribution` | Autor, Quellcode-Adresse und Lizenz; setzt den Hinweis in die Hilfelasche von `connect` und `get` |
| `Runtime` | Verbindung zum laufenden Stud.IP (Einstellungen, Basis-Adresse, Instanzdaten) |

Klassen ohne Abhängigkeit von Stud.IP (`Discovery\DiscoveryBuilder`,
`Discovery\DiscoveryResponse`, `Connect\*`, `Settings\Settings`, `Calendar\UserEvents`,
`Calendar\IcalSubscription`, `WebLogin\WebLoginService`, `RateLimit\RateLimiter`) sind mit
PHPUnit abgedeckt; die schlanke Stud.IP-Schicht (`Runtime`, `Routes\*`,
`OAuth2\ClientProvisioner`, `Devices\TokenLedger`, die Controller) wird gegen ein laufendes
Stud.IP getestet.

## Tests

```bash
make lint     # php -l
make test     # PHPUnit, beide Suiten (installiert beim ersten Lauf die Dev-Abhängigkeiten)
```

Der Plugin-Code muss auf den PHP-Versionen aller unterstützten Stud.IP-Versionen laufen
(PHP 8.1 und neuer); `php -l` daher auch mit der ältesten ausführen. Werkzeuge: PHP 8.1+,
Composer (Dev-Abhängigkeiten aus `plugin/composer.lock`), git 2.40+ (`make dist` nutzt
`git archive --mtime`).

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

`make dist` packt das eingecheckte `plugin/` ohne `tests/` nach
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
| `POST /jsonapi.php/v1/app-connect/token` | keine (OAuth2-Client) | Token-Endpunkt des App-Clients: `authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing` |
| `POST /jsonapi.php/v1/app-connect/devices` | OAuth2-Token des App-Clients | Registrierung nach einer Browser-Anmeldung; `201`, `401`, `403`, `409`, `422` |
| `GET /jsonapi.php/v1/app-connect/course-dates/places` | angemeldet | gebuchte Räume, Koordinaten und Raumfinder-Links von bis zu 50 Terminen ([`contract/course-date-places.md`](../../contract/course-date-places.md)); Discovery-Funktion `eventPlaces` |
| `GET /jsonapi.php/v1/app-connect/user-language` | angemeldet | Profilsprache der Person, sonst die Vorgabe der Instanz ([`contract/user-language.md`](../../contract/user-language.md)); Discovery-Funktion `userLanguage` |
| `GET /jsonapi.php/v1/app-connect/user-events` | angemeldet | der Kalender der Person für zwei Wochen: eigene Termine, Veranstaltungstermine, Ausfälle, mit der Kursfarbe `color` ([`contract/user-events.md`](../../contract/user-events.md)); Discovery-Funktion `userEvents`; `200`, `400`, `401`, `404` |
| `POST /jsonapi.php/v1/app-connect/web-login` | OAuth2-Token des App-Clients oder HTTP Basic (Passwort prüft das Plugin) | einmaliger Link (60 s), der die Person im Web anmeldet und `return_to` öffnet ([`contract/web-login.md`](../../contract/web-login.md)); Discovery-Funktion `webLogin`; `201`, `400`, `401`, `403`, `404`, `429` |
| `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` | OAuth2-Token des App-Clients | der eigene Kalender-Schlüssel der Person, beim ersten Abruf angelegt, und das Zurücksetzen ([`contract/ical-key.md`](../../contract/ical-key.md)); Discovery-Funktion `icalKey`; `200`, `204`, `401`, `403`, `404` |
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
Tokens; eine Erneuerung am Endpunkt des Kerns unterbricht die Kette, deshalb folgt das
Entfernen auch den Nachfolgern, die beim Rotieren jedes Tokens ausgestellt wurden
(höchstens 50 Schritte). Der Zeitabgleich nutzt das `chdate` des Access-Tokens, weil
Refresh-Tokens kein Änderungsdatum haben; beim Widerruf bleibt das `chdate` eines Tokens,
das der Kern schon widerrufen hat, erhalten, und die Nachfolger werden ermittelt, bevor das
gebundene Token widerrufen wird.

Die Kernseite „Drittanwendungen“ widerruft nur das Access-Token. Eine normale Erneuerung
widerruft beide; ein widerrufenes Access-Token mit gültigem Refresh-Token bedeutet also
einen Widerruf im Kern: Der Refresh-Grant des Plugins lehnt das Refresh-Token dann ab,
widerruft es und markiert das Gerät als entfernt. Der Token-Endpunkt des Kerns kennt diese
Regel nicht; die Apps nutzen den Endpunkt des Plugins aus der Discovery.

---

## English

### Layout of the plugin

| Path | Content |
|---|---|
| `plugin/plugin.manifest` | name `app-connect`, class `AppConnect`, origin `studapp` |
| `plugin/AppConnect.php` | plugin class (`SystemPlugin`, `JsonApiPlugin`): routes and the navigation entries (admin, profile settings) |
| `plugin/lib/classes/` | namespace `AppConnect\`, see the components below |
| `plugin/controllers/`, `plugin/views/` | profile page (`connect`), public landing page (`get`), web login from the app (`web`: `enter`, `redeem`, `confirm`); the operator settings are configuration entries of the section "Stud.App" (`Settings\OperatorConfig`, migration `004`) |
| `plugin/migrations/` | database migrations (`NNN_name.php`); `003` registers the cleanup cronjob, `010` and `011` set the retention of the event log entries about the web login and the calendar key |
| `plugin/cronjobs/` | `AppConnectCleanupJob` (daily retention cleanup); loads `bootstrap.php` itself because the cron runner does not load the plugin |
| `plugin/tests/` | PHPUnit: `Unit` (logic without Stud.IP) and `Contract` (schema, shared test vectors) |
| `plugin/LICENSE`, `plugin/SUPPLEMENTAL-TERMS.txt` | copies of `LICENSES/GPL-3.0-or-later.txt` and `SUPPLEMENTAL-TERMS.txt`, so the plugin directory ships both; `AttributionTest` keeps them identical |

Components in `plugin/lib/classes/`:

| Namespace | Responsibility |
|---|---|
| `Settings` | operator settings (configuration section "Stud.App") and the plugin's own settings table; validation of addresses, colours, ids |
| `Discovery` | the public discovery payload from a field allowlist, `ETag`/`304` |
| `OAuth2` | the one public app client (`ClientProvisioner`), the token endpoint (`TokenServer`), the pairing grant and the refresh grant that keeps the device binding |
| `Pairing`, `Connect` | pairing codes (hash only, five minutes, first redemption wins) and the connect link of the QR code |
| `Devices` | device rows bound to a token lineage, removal and revocation in the core token tables (`TokenLedger`), retention |
| `WebLogin` | one-time tickets for the web login, `return_to` checks, the sign-in like the core login |
| `Calendar` | the person's calendar in the shapes of the core route `users/{id}/events`, with course colours (`UserEvents`); the subscription address of the calendar key (`IcalSubscription`) |
| `Places`, `Profile` | places of course dates with coordinates, the profile language |
| `RateLimit`, `Audit`, `Mail` | failure limits, event log actions, the connection mail |
| `Routes` | thin JSON:API controllers of the routes below; `AppToken` admits only the app client's token |
| `Attribution` | author, source URL and license; adds the notice to the help bar of `connect` and `get` |
| `Runtime` | wiring to the running Stud.IP (settings, base URL, instance data) |

Classes without a Stud.IP dependency (`Discovery\DiscoveryBuilder`,
`Discovery\DiscoveryResponse`, `Connect\*`, `Settings\Settings`, `Calendar\UserEvents`,
`Calendar\IcalSubscription`, `WebLogin\WebLoginService`, `RateLimit\RateLimiter`) are
covered by PHPUnit; the thin Stud.IP layer (`Runtime`, `Routes\*`,
`OAuth2\ClientProvisioner`, `Devices\TokenLedger`, the controllers) is tested against a
running Stud.IP.

### Tests

```bash
make lint     # php -l
make test     # PHPUnit, both suites (installs the dev dependencies on first run)
```

The plugin code must run on the PHP versions of every supported Stud.IP release (PHP 8.1
and later); run `php -l` with the oldest one as well. Tools: PHP 8.1+, Composer (dev
dependencies from `plugin/composer.lock`), git 2.40+ (`make dist` uses
`git archive --mtime`).

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

`make dist` packs the committed `plugin/` without `tests/` into
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
| `POST /jsonapi.php/v1/app-connect/token` | none (OAuth2 client) | token endpoint of the app client: `authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing` |
| `POST /jsonapi.php/v1/app-connect/devices` | OAuth2 token of the app client | registration after a browser sign-in; `201`, `401`, `403`, `409`, `422` |
| `GET /jsonapi.php/v1/app-connect/course-dates/places` | signed in | booked rooms, coordinates and room finder links of up to 50 course dates ([`contract/course-date-places.md`](../../contract/course-date-places.md)); discovery feature `eventPlaces` |
| `GET /jsonapi.php/v1/app-connect/user-language` | signed in | profile language of the person, else the site's default ([`contract/user-language.md`](../../contract/user-language.md)); discovery feature `userLanguage` |
| `GET /jsonapi.php/v1/app-connect/user-events` | signed in | the person's calendar for two weeks: personal entries, course dates, cancellations, with the course colour `color` ([`contract/user-events.md`](../../contract/user-events.md)); discovery feature `userEvents`; `200`, `400`, `401`, `404` |
| `POST /jsonapi.php/v1/app-connect/web-login` | OAuth2 token of the app client, or HTTP Basic (password checked by the plugin) | one-time link (60 s) that signs the person in on the web and opens `return_to` ([`contract/web-login.md`](../../contract/web-login.md)); discovery feature `webLogin`; `201`, `400`, `401`, `403`, `404`, `429` |
| `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` | OAuth2 token of the app client | the person's own calendar key, created on first ask, and the reset ([`contract/ical-key.md`](../../contract/ical-key.md)); discovery feature `icalKey`; `200`, `204`, `401`, `403`, `404` |
| `plugins.php/appconnect/web/enter`, `…/web/redeem`, `…/web/confirm` | none | the link of `web-login`: the script posts the ticket from the fragment to `redeem`, which claims it; the same person signed in is redirected, anyone else confirms "Als … anmelden?" and `confirm` signs in |
| `plugins.php/appconnect/connect` | session, `autor` | "Stud.App Verbindung" under Profile → Settings, above Drittanwendungen: pairing code, device list |
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
token (`LineageRefreshTokenGrant`). Removing a device revokes the bound tokens; a refresh
done at the core endpoint breaks the chain, so removal also follows the successors issued
when each token was rotated (at most 50 steps). The time match uses the access token's
`chdate`, because refresh tokens have no change date; revoking keeps the `chdate` of a
token the core revoked already, and successors are looked up before the bound token is
revoked.

The core page "Drittanwendungen" revokes only the access token. A normal refresh revokes
both, so a revoked access token with a valid refresh token means a revocation in the core:
the plugin's refresh grant then refuses the refresh token, revokes it and marks the device
as removed. The core token endpoint does not know this rule; the apps use the plugin's
endpoint from the discovery.
