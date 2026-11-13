# Weg in den Stud.IP-Kern

**Deutsch** · [English](#english)

app-connect enthält nur, was dem Stud.IP-Kern fehlt, um die Apps in der aktuellsten Version
benutzen zu können. Was der Kern über seine JSON:API oder seine Weboberfläche schon leistet,
nutzen die Apps direkt. Das Plugin ist der erste Schritt: Ab Stud.IP 6.4 sollen seine
Funktionen in den Kern wandern. Für Stud.IP 6.0 bis 6.3 bleibt das Plugin, bis diese
Versionen auslaufen.

Für den Übergang gelten drei Zusagen:

- **Keine erneute Anmeldung.** Der OAuth2-Client und alle Tokens bleiben gültig.
- **Keine erneute Geräteregistrierung.** Geräte, Kopplung und Gerätebindung gehen in den Kern
  über.
- **Gleiche Routen.** Die Pfade unter `jsonapi.php/v1/app-connect/` bleiben erhalten. Die
  Discovery nennt den Token-Endpunkt; die Apps folgen ihr.

Über die Aufnahme in den Kern entscheiden die Stud.IP-Entwicklung und der Stud.IP e.V. Dieses
Dokument ist der Vorschlag dafür.

## Funktionen und ihr Ziel im Kern

| Funktion im Plugin | Ziel im Kern | Was im Kern entfällt |
|---|---|---|
| Discovery `GET app-connect/discovery` (`Discovery\*`) | Route der Kern-JSON:API unter demselben Pfad, Werte aus `Config` | Feld-Allowlist für Plugin-Einstellungen, eigene Versionsermittlung |
| Logo `GET app-connect/logo` (`Logo\*`, Tabelle `app_connect_logo`) | Logo als Datei im Kern, z. B. im Konfigurationseditor hochgeladen und beim Hochladen geprüft, und dieselbe Route | Abruf einer fremden Adresse mit eigenem SSRF-Schutz, Tabelle für die Kopie |
| Betreiber-Einstellungen, Sektion „Stud.App“ (`Settings\OperatorConfig`) | dieselben Einträge `APP_CONNECT_*` in einer produktneutralen Sektion; Prüfung der Werte im Konfigurationseditor | Warnung nach dem Speichern über `ConfigValueDidChange` |
| Interne Werte `app_connect_settings` (Client-ID, Basis-Adresse) | Konfigurationseinträge des Kerns | Tabelle `app_connect_settings` |
| Ein OAuth2-Client für alle Apps (`OAuth2\ClientProvisioner`) | vom Kern verwalteter Client mit Kennzeichen „vom System verwaltet“, nicht bearbeitbar in der OAuth2-Verwaltung | Bereitstellen und Widerrufen über `ConfigValueDidChange`, `onEnable`/`onDisable` |
| Eigener Token-Endpunkt `POST app-connect/token` (`OAuth2\TokenServer`) | Token-Endpunkt des Kerns `dispatch.php/api/oauth2/token`; `app-connect/token` bleibt als Verweis darauf | doppelte Server-Konfiguration, abgeschriebene Token-Laufzeiten |
| Kopplungs-Grant `urn:studip:app-connect:pairing` (`OAuth2\PairingGrant`) | Grant im OAuth2-Container des Kerns, zugelassen in `ClientRepository::validateClient`; als Alternative der Device Authorization Grant nach RFC 8628 | Sonderprüfung des Clients im Grant |
| Gerätebindung an die Token-Kette (`OAuth2\LineageRefreshTokenGrant`, `Devices\TokenLedger`) | Spalte für die Token-Familie (bzw. das Gerät) an `oauth2_access_tokens` und `oauth2_refresh_tokens`; atomare Rotation im Refresh-Grant des Kerns | Nachfolgersuche über `chdate`, rohes SQL auf Kern-Tabellen, eigener Refresh-Grant |
| Widerruf unter „Drittanwendungen“ beendet die Kette (nur am Plugin-Endpunkt) | Der Widerruf in `api/oauth2/applications` widerruft Access- und Refresh-Token; die Regel gilt damit an jedem Endpunkt | Prüfung „Access-Token widerrufen, Refresh-Token gültig“ |
| Widerruf beim Abmelden in der App `POST app-connect/revoke` (`OAuth2\TokenRevocation`, `OAuth2\TokenInspector`, `OAuth2\RefreshTokenCipher`) | Widerrufs-Endpunkt nach RFC 7009 im OAuth2-Server des Kerns, in der Discovery als `revocationEndpoint` | eigene Prüfung der JWT-Signatur, Entschlüsseln mit dem Schlüssel des Kerns |
| Geräte `app_connect_devices`, Seite „Stud.App Verbindung“ (`controllers/connect`) | Kernmodell für Geräte, Liste und Kopplungscode auf der Seite „Drittanwendungen“ unter Profil → Einstellungen | eigener Navigationseintrag |
| Kopplungscodes `app_connect_pairing_codes` (`Pairing\*`, `Connect\*`) | Kernmodell, Erzeugen auf derselben Seite | – |
| Seite „App holen“ `plugins.php/appconnect/get` | öffentlicher Kern-Controller (`dispatch.php/...`); die alte Adresse leitet weiter, weil gedruckte QR-Codes darauf zeigen | Rolle „Nobody“ für das Plugin |
| Web-Anmeldung aus der App `POST app-connect/web-login`, `plugins.php/appconnect/web/*` | Einmal-Anmeldung in `Studip\Authentication`; das `Token`-Modell des Kerns wird gehärtet (Zufall aus `random_bytes`, nur der Hash wird gespeichert, atomares Einlösen) oder es kommt eine eigene Tabelle | Nachbau der Anmeldefolge in `WebLogin\WebSignIn` |
| Kalender der Person `GET app-connect/user-events` | `users/{id}/events` des Kerns um Veranstaltungstermine und Ausfälle erweitert, wie es der iCal-Export des Kerns schon tut; Kursfarbe als `meta` oder über `course-memberships` | eigene Route, eigene Dokumentform |
| Orte von Terminen `GET app-connect/course-dates/places` | Relation `rooms` am Schema `course-events`, Raum-Schema mit Koordinaten (geerbt über Gebäude und Standort) und Raumfinder-Link | Sammelroute mit `filter[ids]` |
| Profilsprache `GET app-connect/user-language` | entfällt: Seit 6.3 liefert `users/me` das Attribut `preferred-language`; der Rückfall auf die Vorgabesprache wird ein weiteres Attribut | ganze Route |
| Kalender-Schlüssel `GET`/`DELETE app-connect/me/ical-key` | Route `users/{id}/ical-key`; `IcalExport::makeKey()` erzeugt Schlüssel aus `random_int` mit 32 Zeichen; `ICAL_EXPORT_KEY` als registrierter Konfigurationseintrag | `Calendar\IcalKey`, Längenprüfung |
| Zugriff nur für den App-Client (`Routes\AppToken`) | eigener OAuth2-Scope (z. B. `app`) | Vergleich mit der Client-ID |
| Grenzen für Fehlversuche (`RateLimit\*`) | allgemeiner Dienst auf `Studip\Cache` | – |
| Ereignisprotokoll `APPCONNECT_*` (`Audit\EventLog`) | Kern-Aktionen im `log_actions` | Registrierung als Plugin-Aktion |
| Mail „Gerät verbunden“ (`Mail\ConnectedMail`) | Kern-Mail in der Sprache der empfangenden Person | zwei hart codierte Sprachfassungen |
| Aufräum-Cronjob (`AppConnectCleanupJob`) | Kern-Cronjob in `lib/cronjobs/`, zusätzlich: abgelaufene OAuth2-Tokens löschen | eigener Autoloader für den Cron-Runner |
| Datenauskunft der Person | `PrivacyObject` am Gerätemodell | `PrivacyPlugin` am Plugin |

## Bewusste Abweichungen im Plugin

Einige Dinge löst das Plugin anders, als der Kern es täte. Jede dieser Abweichungen hat einen
Grund, der nur für ein Plugin gilt, und entfällt im Kern.

| Kern | Plugin | Grund |
|---|---|---|
| Token-Endpunkt `dispatch.php/api/oauth2/token` | eigener Endpunkt mit demselben Server | Plugins können dem OAuth2-Container des Kerns keine Grants hinzufügen; Kopplung und Gerätebindung brauchen eigene Grants. |
| Widerruf über `AccessToken::revoke()` | Widerruf mit SQL in `Devices\TokenLedger` | Das `chdate` eines Tokens muss beim Widerruf erhalten bleiben, weil das Entfernen eines Geräts damit auch Nachfolger findet, die am Kern-Endpunkt ausgestellt wurden. |
| Einmal-Tokens über das Modell `Token` | eigene Tabelle `app_connect_web_login_tickets` | `Token` speichert den Wert im Klartext, erzeugt ihn aus `uniqid()` und löst nicht atomar ein. |
| Kalender-Schlüssel aus `IcalExport::makeKey()` | eigener Generator | `makeKey()` nutzt `rand()` und 8 Zeichen; der Schlüssel ist die ganze Berechtigung des Abos und läuft nie ab. |
| Anmeldung nur über `login.php` | `WebLogin\WebSignIn` führt dieselben Schritte aus (Person setzen, Sitzungs-ID erneuern, Prüfungen wie beim Login) | Der Kern hat keine Schnittstelle für eine Anmeldung ohne Passwort. |
| Rechte über OAuth2-Scopes | Vergleich mit der Client-ID | Der Kern kennt nur den Scope `api`; Plugins können keine Scopes anmelden. |
| Routen mit `JsonApiController` und Schema, Fehler über `JsonApi\Errors` | `NonJsonApiController`, Fehler mit festem `code` | Die Apps brauchen stabile Fehlercodes (siehe `contract/`); Plugins können Schemas des Kerns nicht erweitern. |
| Autoloader `StudipAutoloader` | eigener Autoloader in `bootstrap.php` | Migrationen und Cron-Runner laden die Klassen ohne Plugin-Instanz. |
| Überschrift eines Inhaltskastens als h1, gestaltet über `section.contentbox header h1` | h2 mit denselben Werten als Inline-Style (`ContentBox`) | Die Seite behält den Seitentitel des Kerns als einziges h1; Plugins können das Stylesheet des Kerns nicht ändern. Im Kern gilt die Regel auch für h2. |
| Plugins sind für „Nobody“ nicht freigegeben | das Plugin gibt sich für „Nobody“ frei | Discovery, Token-Endpunkt und „App holen“ werden ohne Sitzung aufgerufen. |
| keine eigenen Sicherheits-Header | `X-Frame-Options` und `frame-ancestors` auf Kopplung, „App holen“ und Web-Anmeldung | Schutz vor Clickjacking auf Seiten, die Zugang gewähren. |
| PHPUnit/Codeception im Kern-Testsystem | eigenständige PHPUnit-Suiten für die Logik | Ein Plugin hat kein Kern-Testsystem; beim Übergang wandern die Tests nach `tests/unit` und `tests/jsonapi`. |

## Datenmodell

Die Tabellen sind von Anfang an so geschnitten, wie sie im Kern aussehen sollen.

| Plugin | Kern |
|---|---|
| `app_connect_devices` | wird übernommen; Name bleibt oder bekommt das Präfix der OAuth2-Tabellen (`oauth2_devices`) – das entscheidet die Kernentwicklung |
| `app_connect_pairing_codes` | wird übernommen (bzw. `oauth2_pairing_codes`) |
| `app_connect_web_login_tickets` | wird übernommen oder geht in ein gehärtetes `Token` auf |
| `app_connect_settings` | entfällt; Werte gehen in die Konfiguration |
| `app_connect_logo` | entfällt; das Logo wird eine Datei des Kerns |
| `config`, Einträge `APP_CONNECT_*` | bleiben mit Namen und Werten; nur die Sektion wird umbenannt |
| `log_actions` `APPCONNECT_*` | werden Kern-Aktionen (`type = 'core'`); vorhandene `log_events` bleiben |
| Cronjob `AppConnectCleanupJob` | wird durch den Kern-Cronjob ersetzt |

## Ablauf

### 1. Vorbereitung im Plugin

Das Plugin bereitet den Übergang vor; was noch fehlt, kommt mit einer letzten 1.x-Version:

- **Feste Migrationen.** Jede Migration enthält die Werte, die sie anlegt, statt sie aus
  aktuellen Klassen zu lesen (`MigrationsTest`). Die Kernmigration weiß damit genau, was
  vorhanden ist.
- **Kern erkennen.** Bringt der Kern die Funktionen mit, dann
  - registriert das Plugin keine Routen und keinen Navigationseintrag,
  - widerruft in `onDisable` nichts,
  - und lässt in seinen `down()`-Migrationen Tabellen, Konfiguration und Client unberührt.

  Erkannt wird das an einer Klasse oder einem Konfigurationseintrag des Kerns, nicht an der
  Versionsnummer.
- **Versionsgrenze.** `studipMaxVersion` im Manifest nennt die neueste geprüfte Version,
  zurzeit 6.3. Sobald feststeht, mit welcher Version der Kern die Funktionen mitbringt, bleibt
  die Grenze darunter. Kern und Plugin dürfen dieselben Routen nie gleichzeitig registrieren,
  sonst lehnt der Router der JSON:API alle Anfragen ab.
- **Übersetzung.** Die Texte stehen mit deutschen msgids in der Textdomäne `app-connect`
  (`plugin/locale`). Beim Übergang wandern msgids und englische Übersetzungen unverändert
  in den Kernkatalog.

### 2. Kernbeitrag

- Merge Request gegen `main` im Code-Stil des Kerns (`.editorconfig`, 120 Zeichen),
  Namensraum `Studip\…` statt `AppConnect\`. Die Namensräume kollidieren damit nicht, solange
  die Plugin-Dateien noch auf dem Server liegen.
- Routen in `lib/classes/JsonApi/RouteMap.php` unter denselben Pfaden; Seiten unter
  `app/controllers/`; Navigation in `lib/navigation/ProfileNavigation.php`.
- Tests aus `plugin/tests` nach `tests/unit` und `tests/jsonapi`; dazu ein Migrationstest:
  Eine Instanz mit Plugin und verbundenen Geräten wird aktualisiert, danach erneuert eine
  App ihr Token, ohne sich neu anzumelden, und das Entfernen eines Geräts beendet seine Kette.
- Einzelne Teile können vorab und unabhängig in den Kern, weil sie den Kern auch ohne
  app-connect verbessern:
  - `IcalExport::makeKey()` mit `random_int`,
  - das gehärtete `Token`-Modell,
  - der Widerruf von Access- und Refresh-Token unter „Drittanwendungen“,
  - „Drittanwendungen“ listet eine Anwendung, solange sie ein gültiges Refresh-Token hat;
    heute zeigt `AccessToken::findValidTokens()` sie nur, solange ein Access-Token gilt, bei
    der App also bis etwa eine Stunde nach der letzten Nutzung,
  - das Löschen abgelaufener OAuth2-Tokens.

### 3. Kernmigration

Die Migration des Kerns (`db/migrations/6.4.N_integrate_app_connect.php`, nach dem Muster
früherer Plugin-Übernahmen) führt diese Schritte aus:

1. **Tabellen übernehmen:** `CREATE TABLE IF NOT EXISTS` mit dem Schema des Plugins, bei
   Umbenennung `RENAME TABLE`; Spalte für die Token-Familie an den OAuth2-Tabellen ergänzen
   und aus `app_connect_devices` füllen.
2. **Client übernehmen:** Die Client-ID aus `app_connect_settings` wird zum
   Konfigurationseintrag des Kerns und der Client als „vom System verwaltet“ markiert. Fehlt
   die Zeile, legt der Kern den Client beim Einschalten an. Danach entfällt
   `app_connect_settings`.
3. **Konfiguration übernehmen:** `APP_CONNECT_*` bleiben, Sektion umbenennen, fehlende
   Einträge per `INSERT IGNORE` anlegen.
4. **Ereignisprotokoll:** `UPDATE log_actions SET type = 'core', class = NULL WHERE name LIKE
   'APPCONNECT\_%'`.
5. **Cronjob:** den Plugin-Cronjob abmelden, den Kern-Cronjob anmelden.
6. **Plugin stilllegen, ohne seine `down()`-Migrationen auszuführen:**
   - das Plugin deaktivieren,
   - seine Rollenzuordnungen löschen,
   - seine Einträge in `schema_version` entfernen.

   Eine spätere Deinstallation des Plugins löscht dann weder Tabellen noch Konfiguration und
   widerruft den Client nicht.

### 4. Nach dem Update

- Die Discovery liefert dieselben Felder und Funktionen; `oauth2.tokenEndpoint` darf auf den
  Kern-Endpunkt zeigen. `app-connect/token` bleibt als Verweis auf den Kern-Endpunkt, solange
  App-Versionen im Umlauf sind, die den Endpunkt zwischenspeichern.
- „App holen“ unter der alten Plugin-Adresse leitet auf die Kernseite weiter.
- Die Seite „Stud.App Verbindung“ geht in „Drittanwendungen“ auf.

### 5. Pflege für ältere Versionen

Das Plugin wird für Stud.IP 6.0 bis 6.3 weiter gepflegt, bis diese Versionen auslaufen.
Fehlerbehebungen gehen in Plugin und Kern. Neue Funktionen kommen in den Kern und nur dann
auch ins Plugin, wenn sie ohne Kernänderung möglich sind.

## Lizenz

Der Kern steht unter GPL-2.0-or-later. Das Plugin steht unter GPL-3.0-or-later mit
Zusatzbedingungen nach § 7. Beiträge zum Plugin stehen laut [`CONTRIBUTING.md`](../../CONTRIBUTING.md)
zusätzlich unter GPL-2.0-or-later ohne Zusatzbedingungen; der eigene Code des Herausgebers
wird beim Einreichen ebenso bereitgestellt. Damit kann der Code ohne Umlizenzierung durch Dritte
in den Kern; die Zusatzbedingungen (Hinweis in der Hilfelasche) gelten dort nicht.

---

## English

app-connect contains only what the Stud.IP core lacks for the apps to work in their latest
version. Whatever the core already provides through its JSON:API or its web interface, the
apps use directly. The plugin is the first step: from Stud.IP 6.4 its functions are meant to
move into the core. For Stud.IP 6.0 to 6.3 the plugin stays until those versions reach end of
life.

The transition keeps three promises:

- **No new sign-in.** The OAuth2 client and all tokens stay valid.
- **No new device registration.** Devices, pairing and the device binding move into the core.
- **Same routes.** The paths under `jsonapi.php/v1/app-connect/` are kept. The discovery names
  the token endpoint; the apps follow it.

Whether the functions become part of the core is decided by the Stud.IP developers and
Stud.IP e.V. This document is the proposal for that.

### Functions and their target in the core

| Function in the plugin | Target in the core | What the core no longer needs |
|---|---|---|
| Discovery `GET app-connect/discovery` (`Discovery\*`) | route of the core JSON:API under the same path, values from `Config` | field allowlist for plugin settings, own version lookup |
| Logo `GET app-connect/logo` (`Logo\*`, table `app_connect_logo`) | the logo as a file in the core, for example uploaded in the configuration editor and checked on upload, and the same route | fetching a foreign address with its own SSRF protection, the table for the copy |
| Operator settings, section "Stud.App" (`Settings\OperatorConfig`) | the same `APP_CONNECT_*` entries in a product-neutral section; values validated in the configuration editor | warning after saving via `ConfigValueDidChange` |
| Internal values `app_connect_settings` (client id, base URL) | core configuration entries | table `app_connect_settings` |
| One OAuth2 client for all apps (`OAuth2\ClientProvisioner`) | client managed by the core, flagged "managed by the system", not editable in the OAuth2 administration | provisioning and revoking via `ConfigValueDidChange`, `onEnable`/`onDisable` |
| Own token endpoint `POST app-connect/token` (`OAuth2\TokenServer`) | the core token endpoint `dispatch.php/api/oauth2/token`; `app-connect/token` remains as a pointer to it | duplicated server setup, copied token lifetimes |
| Pairing grant `urn:studip:app-connect:pairing` (`OAuth2\PairingGrant`) | grant in the core OAuth2 container, allowed in `ClientRepository::validateClient`; alternatively the device authorization grant of RFC 8628 | special client check inside the grant |
| Device bound to the token lineage (`OAuth2\LineageRefreshTokenGrant`, `Devices\TokenLedger`) | column for the token family (or device) on `oauth2_access_tokens` and `oauth2_refresh_tokens`; atomic rotation in the core refresh grant | successor search via `chdate`, raw SQL on core tables, own refresh grant |
| Revoking on "Drittanwendungen" ends the lineage (only at the plugin endpoint) | revoking in `api/oauth2/applications` revokes access and refresh token, so the rule holds at every endpoint | check "access token revoked, refresh token valid" |
| Revocation when signing out in the app `POST app-connect/revoke` (`OAuth2\TokenRevocation`, `OAuth2\TokenInspector`, `OAuth2\RefreshTokenCipher`) | revocation endpoint per RFC 7009 in the core OAuth2 server, announced as `revocationEndpoint` | own JWT signature check, decryption with the core's key |
| Devices `app_connect_devices`, page "Stud.App Verbindung" (`controllers/connect`) | core device model; list and pairing code on the page "Drittanwendungen" under Profile → Settings | own navigation entry |
| Pairing codes `app_connect_pairing_codes` (`Pairing\*`, `Connect\*`) | core model, created on the same page | – |
| Page "Get the app" `plugins.php/appconnect/get` | public core controller (`dispatch.php/...`); the old address redirects, because printed QR codes point to it | role "Nobody" for the plugin |
| Web sign-in from the app `POST app-connect/web-login`, `plugins.php/appconnect/web/*` | one-time sign-in in `Studip\Authentication`; the core `Token` model is hardened (randomness from `random_bytes`, only the hash stored, atomic redemption) or gets a table of its own | rebuilt sign-in sequence in `WebLogin\WebSignIn` |
| The person's calendar `GET app-connect/user-events` | core `users/{id}/events` extended by course dates and cancellations, as the core iCal export already does; course colour as `meta` or through `course-memberships` | own route, own document shape |
| Places of dates `GET app-connect/course-dates/places` | relation `rooms` on the `course-events` schema, room schema with coordinates (inherited from building and location) and room finder link | collection route with `filter[ids]` |
| Profile language `GET app-connect/user-language` | dropped: since 6.3 `users/me` has the attribute `preferred-language`; the fallback to the default language becomes one more attribute | the whole route |
| Calendar key `GET`/`DELETE app-connect/me/ical-key` | route `users/{id}/ical-key`; `IcalExport::makeKey()` creates keys from `random_int` with 32 characters; `ICAL_EXPORT_KEY` as a registered configuration entry | `Calendar\IcalKey`, length checks |
| Access for the app client only (`Routes\AppToken`) | own OAuth2 scope (e.g. `app`) | comparison with the client id |
| Limits for failed attempts (`RateLimit\*`) | general service on top of `Studip\Cache` | – |
| Event log `APPCONNECT_*` (`Audit\EventLog`) | core actions in `log_actions` | registration as plugin actions |
| Mail "device connected" (`Mail\ConnectedMail`) | core mail in the recipient's language | two hard-coded language versions |
| Cleanup cronjob (`AppConnectCleanupJob`) | core cronjob in `lib/cronjobs/`, additionally deleting expired OAuth2 tokens | own autoloader for the cron runner |
| The person's data export | `PrivacyObject` on the device model | `PrivacyPlugin` on the plugin |

### Deliberate deviations in the plugin

Some things the plugin does differently from how the core would. Each deviation has a reason
that only applies to a plugin and disappears in the core.

| Core | Plugin | Reason |
|---|---|---|
| Token endpoint `dispatch.php/api/oauth2/token` | own endpoint with the same server | Plugins cannot add grants to the core OAuth2 container; pairing and device binding need grants of their own. |
| Revoking via `AccessToken::revoke()` | revoking with SQL in `Devices\TokenLedger` | A token's `chdate` must survive revocation, because removing a device uses it to find successors issued at the core endpoint as well. |
| One-time tokens via the `Token` model | own table `app_connect_web_login_tickets` | `Token` stores the value in clear text, derives it from `uniqid()` and does not redeem atomically. |
| Calendar keys from `IcalExport::makeKey()` | own generator | `makeKey()` uses `rand()` and 8 characters; the key is the whole authorisation of the feed and never expires. |
| Sign-in only through `login.php` | `WebLogin\WebSignIn` performs the same steps (set the person, renew the session id, the checks of the login) | The core has no interface for a sign-in without a password. |
| Permissions through OAuth2 scopes | comparison with the client id | The core knows only the scope `api`; plugins cannot register scopes. |
| Routes with `JsonApiController` and schema, errors via `JsonApi\Errors` | `NonJsonApiController`, errors with a fixed `code` | The apps need stable error codes (see `contract/`); plugins cannot extend core schemas. |
| Autoloader `StudipAutoloader` | own autoloader in `bootstrap.php` | Migrations and the cron runner load the classes without a plugin instance. |
| Content box heading as h1, styled by `section.contentbox header h1` | h2 with the same values as an inline style (`ContentBox`) | The page keeps the core's page title as its only h1; plugins cannot change the core's stylesheet. In the core the rule also covers h2. |
| Plugins are not enabled for "Nobody" | the plugin enables itself for "Nobody" | Discovery, token endpoint and "Get the app" are called without a session. |
| No security headers of its own | `X-Frame-Options` and `frame-ancestors` on pairing, "Get the app" and web sign-in | Protection against clickjacking on pages that grant access. |
| PHPUnit/Codeception in the core test system | standalone PHPUnit suites for the logic | A plugin has no core test system; on the move the tests go to `tests/unit` and `tests/jsonapi`. |

### Data model

The tables are cut from the start the way they should look in the core.

| Plugin | Core |
|---|---|
| `app_connect_devices` | taken over; keeps its name or gets the prefix of the OAuth2 tables (`oauth2_devices`) – the core developers decide |
| `app_connect_pairing_codes` | taken over (or `oauth2_pairing_codes`) |
| `app_connect_web_login_tickets` | taken over or merged into a hardened `Token` |
| `app_connect_settings` | dropped; values move into the configuration |
| `app_connect_logo` | dropped; the logo becomes a core file |
| `config`, entries `APP_CONNECT_*` | keep names and values; only the section is renamed |
| `log_actions` `APPCONNECT_*` | become core actions (`type = 'core'`); existing `log_events` stay |
| Cronjob `AppConnectCleanupJob` | replaced by the core cronjob |

### Steps

#### 1. Preparation in the plugin

The plugin prepares the move; what is still missing comes with a last 1.x version:

- **Fixed migrations.** Each migration contains the values it creates instead of reading
  them from current classes (`MigrationsTest`), so the core migration knows exactly what
  exists.
- **Detect the core.** When the core brings the functions, the plugin
  - registers no routes and no navigation entry,
  - revokes nothing in `onDisable`,
  - and leaves tables, configuration and client untouched in its `down()` migrations.

  Detection uses a core class or configuration entry, not the version number.
- **Version limit.** `studipMaxVersion` in the manifest names the newest tested version,
  currently 6.3. Once it is known which version brings the functions, the limit stays below
  it. Core and plugin must never register the same routes at the same time, or the JSON:API
  router refuses every request.
- **Translation.** The texts have German msgids in the text domain `app-connect`
  (`plugin/locale`). On the move, msgids and English translations go unchanged into the
  core catalogue.

#### 2. Core contribution

- Merge request against `main` in the core code style (`.editorconfig`, 120 columns),
  namespace `Studip\…` instead of `AppConnect\`. The namespaces therefore do not collide while
  the plugin files are still on the server.
- Routes in `lib/classes/JsonApi/RouteMap.php` under the same paths; pages under
  `app/controllers/`; navigation in `lib/navigation/ProfileNavigation.php`.
- Tests from `plugin/tests` move to `tests/unit` and `tests/jsonapi`, plus a migration test:
  an instance with the plugin and connected devices is updated, then an app renews its token
  without signing in again, and removing a device ends its lineage.
- Some parts can go into the core early and on their own, because they improve the core even
  without app-connect:
  - `IcalExport::makeKey()` with `random_int`,
  - the hardened `Token` model,
  - revoking access and refresh token on "Drittanwendungen",
  - "Drittanwendungen" lists an application as long as it holds a valid refresh token;
    today `AccessToken::findValidTokens()` shows it only while an access token is valid,
    for the app until about an hour after it was last used,
  - deleting expired OAuth2 tokens.

#### 3. Core migration

The core migration (`db/migrations/6.4.N_integrate_app_connect.php`, following earlier
plugin integrations) does the following:

1. **Take over the tables:** `CREATE TABLE IF NOT EXISTS` with the plugin's schema, or
   `RENAME TABLE` when renamed; add the token-family column to the OAuth2 tables and fill it
   from `app_connect_devices`.
2. **Take over the client:** the client id from `app_connect_settings` becomes a core
   configuration entry and the client is flagged "managed by the system". Without that row
   the core creates the client when the connection is switched on. Then
   `app_connect_settings` is dropped.
3. **Take over the configuration:** `APP_CONNECT_*` stay, the section is renamed, missing
   entries are added with `INSERT IGNORE`.
4. **Event log:** `UPDATE log_actions SET type = 'core', class = NULL WHERE name LIKE
   'APPCONNECT\_%'`.
5. **Cronjob:** unregister the plugin cronjob, register the core cronjob.
6. **Retire the plugin without running its `down()` migrations:**
   - disable the plugin,
   - delete its role assignments,
   - remove its rows from `schema_version`.

   Uninstalling the plugin later then drops no tables or configuration and does not revoke
   the client.

#### 4. After the update

- The discovery returns the same fields and features; `oauth2.tokenEndpoint` may point to the
  core endpoint. `app-connect/token` stays as a pointer to the core endpoint as long as app
  versions that cache the endpoint are in use.
- "Get the app" under the old plugin address redirects to the core page.
- The page "Stud.App Verbindung" becomes part of "Drittanwendungen".

#### 5. Maintenance for older versions

The plugin is maintained for Stud.IP 6.0 to 6.3 until those versions reach end of life. Fixes
go into plugin and core. New functions go into the core, and into the plugin only when they
need no core change.

### License

The core is licensed under GPL-2.0-or-later. The plugin is licensed under GPL-3.0-or-later
with additional terms under section 7. According to [`CONTRIBUTING.md`](../../CONTRIBUTING.md),
contributions to the plugin are additionally licensed under GPL-2.0-or-later without the
additional terms; the publisher's own code is provided the same way when it is submitted. The
code can therefore go into the core without relicensing by third parties; the additional terms
(notice in the help panel) do not apply there.
