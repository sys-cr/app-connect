# Changelog

**Deutsch** · [English](#english)

Alle nennenswerten Änderungen an diesem Projekt stehen in dieser Datei. Das Format folgt
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen folgen
[Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Funktionen

- Das Plugin liefert das Logo der Hochschule selbst aus: `GET /jsonapi.php/v1/app-connect/logo`
  ohne Anmeldung, mit `ETag`/`304` und einen Tag cachebar. Die Apps laden das Logo nur vom
  Server der Instanz; ein Logo auf einem anderen Server erschien bisher nicht. Das Plugin
  ruft die Adresse aus `APP_CONNECT_LOGO_URL` nach dem Speichern und täglich im Cronjob ab
  und speichert eine geprüfte Kopie in der neuen Tabelle `app_connect_logo`: nur https,
  keine internen Netze, höchstens drei Weiterleitungen, PNG, JPEG oder WebP bis 512 KB mit
  32 bis 2048 Pixeln je Seite; SVG wird abgelehnt. Scheitert ein späterer Abruf, bleibt die
  letzte gute Kopie.
- `branding.logoUrl` in der Discovery zeigt auf diese Route, mit einer Version für den
  Cache, und fehlt ohne gültige Kopie.
- Für root zeigt „Stud.App Verbindung“ das gespeicherte Logo, den Zeitpunkt des Abrufs oder
  den Grund der Ablehnung und „Logo neu laden“.
- Statt einer Adresse kann root dort eine Bilddatei hochladen (PNG, JPEG oder WebP, gleiche
  Prüfungen). Sie hat Vorrang vor `APP_CONNECT_LOGO_URL`, bis sie entfernt wird.

### Änderungen

- Die Spalte „Zuletzt verwendet“ auf „Stud.App Verbindung“ heißt jetzt „Zuletzt aktiv“. Sie
  zeigt den Tag der letzten Token-Erneuerung; der Hilfetext erklärt das (#11).

### Änderungen

- „Gerät entfernen“ sagt jetzt, was endet und was nicht: Die App auf dem Gerät verliert den
  Zugang; ein Kalender-Abo und eine im Browser geöffnete Sitzung bleiben. Bestätigung,
  Meldung, Hilfetext und Nutzer-Doku nennen das (#7).

### Dokumentation

- Die Admin-Doku sagt, dass die Funktionsschalter steuern, was die App anbietet, und keine
  Berechtigungen sind; serverseitig gesperrt werden nur die Routen des Plugins (#10).

### Änderungen

- Liegt Stud.IP unter einem Unterpfad (etwa `/studip`), lässt sich die App-Verbindung nicht
  mehr einschalten, und das Plugin sagt warum. Die Apps erreichen solche Instanzen nicht;
  Admin-Doku und Vertrag nennen die Voraussetzung (#6).

### Änderungen

- Ausschalten von `APP_CONNECT_ENABLED` beendet alle Verbindungen endgültig: Alle Geräte
  gelten als entfernt, alle Tokens der App werden widerrufen. Bisher wurden alte
  Verbindungen nach dem Wiedereinschalten wieder gültig. Deaktivieren des Plugins pausiert
  weiterhin nur (#9).

### Änderungen

- Konten mit den Rollen admin und root nutzen die Anmeldung im Web aus der App nur noch,
  wenn der neue Schalter `APP_CONNECT_WEB_LOGIN_PRIVILEGED` an ist; ab Werk ist er aus
  (Migration 016) (#8).

### Entwicklung

- Integrationstests gegen MariaDB (`make test-integration`): Kopplungscode und Web-Ticket
  lassen sich auch bei zehn gleichzeitigen Anfragen nur einmal einlösen, dazu die Abfragen
  auf den OAuth2-Tabellen. GitHub Actions prüft jede Änderung mit PHP 8.1 bis 8.3 (#12).

### Sicherheit

- Die Zähler der Grenzen (Kopplung, Widerruf, Kalender-Schlüssel) liegen in der neuen
  Tabelle `app_connect_counters` (Migration 017) und zählen atomar. Im Cache konnten sich
  gleichzeitige Anfragen gegenseitig überschreiben, sodass die Grenzen nicht griffen
  (GHSA-h233-jcr9-9x32).
- Erneuern, Entfernen und Widerrufen sperren Gerätezeile und Refresh-Token in fester
  Reihenfolge. Zwei gleichzeitige Erneuerungen oder eine Erneuerung während des Entfernens
  konnten bisher eine gültige Token-Familie ohne Gerät hinterlassen (GHSA-mpgv-rh37-3q3h).
- Der Token-Endpunkt des Kerns lehnt die Erneuerung von Tokens des App-Clients ab; die Apps
  erneuern ohnehin über das Plugin. Eine dort erneuerte Kette konnte „Gerät entfernen“
  entgehen. Ist eine Kette trotzdem gebrochen, beendet das Entfernen alle gültigen
  Anmeldungen der Person ohne Gerät, statt Nachfolger über Zeitstempel zu suchen
  (GHSA-96vf-38vj-xw35).
- Kopplung und Erneuerung der Tokens prüfen das Konto wie die Passwortanmeldung des Kerns.
  Ein abgelaufenes, gesperrtes oder noch nicht aktiviertes Konto kann kein Gerät verbinden,
  und ein verbundenes Gerät verliert bei der nächsten Erneuerung den Zugang
  (GHSA-74w7-wfwq-mqj6).

## [1.1.1] – 2026-11-08

### Änderungen

- Das Manifest nennt Stud.IP 6.3 als höchste Version. Das Plugin läuft mit Stud.IP 6.0
  bis 6.3; der Kern vergleicht nur Haupt- und Nebenversion, sodass jedes 6.3.x passt.
- Der Widerruf lässt 300 statt 30 Anfragen je Adresse in 10 Minuten zu. Hinter einem
  gemeinsamen Netz der Hochschule (NAT, Proxy) teilen sich viele Personen eine Adresse;
  die Grenze für die ganze Instanz bleibt bei 300 je Minute.

## [1.1.0] – 2026-11-08

### Funktionen

- Widerruf nach RFC 7009: `POST /jsonapi.php/v1/app-connect/revoke`, in der Discovery als
  `oauth2.revocationEndpoint` angekündigt. Beim Abmelden in der App enden Tokens und Gerät
  auch auf dem Server.

### Änderungen

- Die Zähler der Grenzen speichern Adressen nur noch als HMAC mit einem zufälligen
  Schlüssel der Installation.
- Ohne `APP_CONNECT_SUPPORT_EMAIL` weisen Konfiguration und „Stud.App Verbindung“ darauf
  hin, dass Meldungen von Inhalten an support@studapp2.de gehen.

### Dokumentation

- Der Datenschutzbaustein nennt OpenMensa, Links, Web-Anmeldung, Ereignisprotokoll und den
  Meldeweg; die Grenze bei `REMOTE_ADDR` hinter Proxys ist beschrieben.

## [1.0.0] – 2026-11-02

Erste veröffentlichte Version.

### Funktionen

- **Discovery** – `GET /jsonapi.php/v1/app-connect/discovery` liefert die öffentlichen
  Instanzdaten aus einer festen Feld-Allowlist, mit `ETag`/`304` und
  `404 app-connect-disabled`, solange die App-Verbindung aus ist; dazu die Sprache der
  Instanz (`instance.locale`) und die eingeschalteten Funktionen.
- **Ein OAuth2-Client** – ein öffentlicher PKCE-Client „Stud.App“ für alle
  App-Installationen, beim Einschalten angelegt oder wiederverwendet, beim Ausschalten
  sowie beim Deaktivieren oder Deinstallieren des Plugins widerrufen. „Entwickelt durch“
  unter „Drittanwendungen“ verweist auf studapp2.de.
- **Token-Endpunkt** – `POST /jsonapi.php/v1/app-connect/token` für den App-Client
  (`authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing`); die
  Erneuerung führt die Gerätebindung mit.
- **Verbinden aus dem Profil** – Seite Profil → Einstellungen → „Stud.App Verbindung“
  direkt über „Drittanwendungen“: einmaliger Kopplungscode (26 Zeichen, fünf Minuten, nur
  als Hash gespeichert) als QR-Code, Rückmeldung der Verbindung, Liste aller Geräte mit
  Entfernen und Beenden von Anmeldungen ohne Gerät. Die erste Einlösung eines Codes
  gewinnt; Fehlversuche sind je Adresse und insgesamt begrenzt (`429 slow_down`).
- **Registrierung nach Browser-Anmeldung** – `POST /jsonapi.php/v1/app-connect/devices`
  bindet das aufrufende Token an ein Gerät (`201`, `409`, wenn schon gebunden).
- **Widerruf** – Entfernen eines Geräts widerruft seine ganze Token-Kette, auch Tokens,
  die am Endpunkt des Kerns erneuert wurden; ein Widerruf unter „Drittanwendungen“ beendet
  das Gerät ebenfalls.
- **Verbindungsmail** – bei jeder neuen Verbindung (in der Sprache der Person), ohne
  Code und ohne IP-Adresse; der Link führt auf die beim Einschalten gespeicherte Adresse.
- **Landing-Page** – `plugins.php/appconnect/get` mit Store-Links und „In der App
  öffnen“; der Code bleibt im URL-Fragment (`no-store`, `no-referrer`).
- **Kalender** – `GET /jsonapi.php/v1/app-connect/user-events` liefert für zwei Wochen
  eigene Termine, Termine der im Kalender angezeigten Veranstaltungen und Ausfälle, jeder
  Veranstaltungstermin mit der Farbgruppe der Person (`color`), in den Ressourcen der
  Kern-Route `users/{id}/events`, jedes Vorkommen einer Serie als eigene Ressource;
  Funktion `userEvents`, Schalter `APP_CONNECT_FEATURE_USER_EVENTS`.
- **Kalender-Schlüssel** – `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` gibt
  den eigenen iCal-Schlüssel und die `webcal://`-Adresse heraus bzw. setzt ihn zurück, nur
  für das Token der App; Funktion `icalKey`, Schalter `APP_CONNECT_FEATURE_ICAL_KEY`.
  Neue Schlüssel erzeugt das Plugin selbst (32 Zeichen aus `random_int`), höchstens fünf
  pro Person und Stunde (`429`); ein vorhandener Schlüssel bleibt.
- **Orte von Terminen** – `GET /jsonapi.php/v1/app-connect/course-dates/places` liefert die
  gebuchten Räume von bis zu 50 Terminen mit Koordinaten und Raumfinder-Link, sonst die
  Freitext-Raumangabe; Funktion `eventPlaces`.
- **Sprache der Person** – `GET /jsonapi.php/v1/app-connect/user-language` liefert die
  Profilsprache oder die Vorgabe der Instanz; Funktion `userLanguage`.
- **Anmeldung im Web aus der App** – `POST /jsonapi.php/v1/app-connect/web-login` liefert
  einen einmaligen Link (60 Sekunden, nur als Hash gespeichert); der Browser fragt „Als
  <Name> anmelden?“, bevor er anmeldet. Funktion `webLogin`, Schalter
  `APP_CONNECT_FEATURE_WEB_LOGIN`.
- **Courseware** – die Discovery meldet `courseware`, solange das Kernmodul Courseware
  aktiv ist; `APP_CONNECT_FEATURE_COURSEWARE` schaltet es aus.
- **Betreiber-Einstellungen** – Konfigurationseinträge der Sektion „Stud.App“ unter Admin →
  System → Konfiguration: App-Verbindung, Kopplung, Profileintrag, Name und Kurzname,
  Datenschutzerklärung, Store-Links, Support-Adresse, Logo, Akzentfarbe, Links, Mensa-Quelle,
  Raumfinder und Schalter der Funktionen. Adressen nur mit `https://`.
- **Aufbewahrung** – täglicher Cronjob: Kopplungscodes einen Tag nach Ablauf, Links für die
  Anmeldung im Web einen Tag nach Ausstellung, Gerätezeilen einen Monat nach Ablauf ihres
  Refresh-Tokens; das Löschen eines Kontos entfernt dessen Zeilen sofort. Einträge des
  Ereignisprotokolls zu Web-Anmeldung und Kalender-Schlüssel laufen nach 90 Tagen ab.
- **Sprachen** – Seiten, Verbindungsmail und Cronjob auf Deutsch und Englisch (eigene
  Textdomäne `app-connect`); die Seiten siezen wie die übrige Oberfläche von Stud.IP.
- **Datenauskunft** – Geräte, Kopplungscodes und Links für die Anmeldung im Web erscheinen
  in der Personendaten-Auskunft von Stud.IP (`PrivacyPlugin`).
- **Schutz der Seiten** – die Seiten des Plugins lassen sich nicht einbetten
  (`X-Frame-Options`, `frame-ancestors`).
- **Lizenz** – GPL-3.0-or-later mit Zusatzbedingungen nach Abschnitt 7
  (`SUPPLEMENTAL-TERMS.txt`): Namensnennung und Lizenzhinweis bleiben in den rechtlichen
  Hinweisen erhalten, geänderte Fassungen sind zu kennzeichnen. Die Hilfelasche von
  „Stud.App Verbindung“ und „App holen“ erklärt die Seite und zeigt am Ende diese Hinweise.
- **Vertrag** – JSON-Schema der Discovery, Regeln für Verbindungslinks, Landing-Page und
  Routen sowie gemeinsame Test-Vektoren für Plugin und Apps.
- **Dokumentation** – Anleitungen für Nutzende, Betreiber und Entwicklung; `make dist`
  baut das Plugin-ZIP, derselbe Commit ergibt immer dieselbe Datei.

---

## English

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/).

### [Unreleased]

#### Features

- The plugin serves the institution logo itself: `GET /jsonapi.php/v1/app-connect/logo`
  without sign-in, with `ETag`/`304` and cacheable for a day. The apps load the logo only
  from the instance's server; a logo on another server did not show so far. The plugin
  fetches the address in `APP_CONNECT_LOGO_URL` after it is saved and daily in the cronjob
  and keeps a checked copy in the new table `app_connect_logo`: https only, no internal
  networks, at most three redirects, PNG, JPEG or WebP up to 512 KB with 32 to 2048 pixels
  per side; SVG is refused. If a later fetch fails, the last good copy stays.
- `branding.logoUrl` in the discovery points to this route, with a version for caches, and
  is left out without a valid copy.
- For root, "Stud.App connection" shows the stored logo, when it was fetched or why it was
  refused, and "Logo neu laden" (fetch the logo again).
- Instead of an address, root can upload a picture there (PNG, JPEG or WebP, same checks). It
  takes precedence over `APP_CONNECT_LOGO_URL` until it is removed.

#### Changes

- The column "Zuletzt verwendet" on "Stud.App Verbindung" is now called "Zuletzt aktiv" (last
  active). It shows the day of the last token refresh; the help text explains this (#11).

#### Changes

- "Gerät entfernen" (remove device) now says what ends and what does not: the app on the
  device loses access; a calendar subscription and a session open in the browser stay.
  Confirmation, message, help text and user docs say so (#7).

#### Documentation

- The admin docs say that the feature switches control what the app offers and are not
  permissions; only the plugin's own routes are blocked on the server (#10).

#### Changes

- If Stud.IP is installed below a path (such as `/studip`), the app connection can no longer
  be switched on, and the plugin says why. The apps cannot reach such instances; admin docs
  and contract name the requirement (#6).

#### Changes

- Switching `APP_CONNECT_ENABLED` off ends every connection for good: all devices count as
  removed and all tokens of the app are revoked. So far, old connections became valid again
  after switching on. Disabling the plugin still only pauses (#9).

#### Changes

- Accounts with the roles admin and root use the web login from the app only when the new
  switch `APP_CONNECT_WEB_LOGIN_PRIVILEGED` is on; it is off by default (migration 016) (#8).

#### Development

- Integration tests against MariaDB (`make test-integration`): pairing code and web ticket
  can be redeemed only once even with ten concurrent requests, plus the queries on the
  OAuth2 tables. GitHub Actions checks every change with PHP 8.1 to 8.3 (#12).

#### Security

- The limit counters (pairing, revocation, calendar key) live in the new table
  `app_connect_counters` (migration 017) and count atomically. In the cache, concurrent
  requests could overwrite each other, so the limits did not hold (GHSA-h233-jcr9-9x32).
- Refresh, removal and revocation lock the device row and the refresh token in a fixed
  order. Two simultaneous refreshes or a refresh during removal could leave a valid token
  family without a device (GHSA-mpgv-rh37-3q3h).
- The core's token endpoint refuses to refresh tokens of the app client; the apps refresh
  through the plugin anyway. A chain refreshed there could escape "remove device". If a
  chain is broken all the same, removal ends every valid sign-in of the person without a
  device instead of looking for successors by time stamp (GHSA-96vf-38vj-xw35).
- Pairing and token refresh check the account like the core's password login. An expired,
  locked or not yet activated account cannot connect a device, and a connected device loses
  access at its next refresh (GHSA-74w7-wfwq-mqj6).

### [1.1.1] – 2026-11-08

#### Changes

- The manifest names Stud.IP 6.3 as the highest version. The plugin runs on Stud.IP 6.0
  to 6.3; the core compares only major and minor version, so every 6.3.x fits.
- Revocation allows 300 instead of 30 requests per address in 10 minutes. Behind a shared
  university network (NAT, proxy) many people share one address; the limit for the whole
  instance stays at 300 per minute.

### [1.1.0] – 2026-11-08

#### Features

- Revocation under RFC 7009: `POST /jsonapi.php/v1/app-connect/revoke`, announced in the
  discovery as `oauth2.revocationEndpoint`. Signing out in the app ends the tokens and the
  device on the server as well.

#### Changes

- The rate limit counters store addresses only as an HMAC with a random secret of the
  installation.
- Without `APP_CONNECT_SUPPORT_EMAIL`, the configuration and "Stud.App Verbindung" point
  out that content reports go to support@studapp2.de.

#### Documentation

- The privacy paragraph names OpenMensa, links, the web login, the event log and the
  reporting path; the limit of `REMOTE_ADDR` behind proxies is described.

### [1.0.0] – 2026-11-02

First published version.

#### Features

- **Discovery** – `GET /jsonapi.php/v1/app-connect/discovery` returns the public instance
  data from a fixed field allowlist, with `ETag`/`304` and `404 app-connect-disabled`
  while the app connection is off; it includes the site language (`instance.locale`) and
  the enabled features.
- **One OAuth2 client** – one public PKCE client "Stud.App" for all app installations,
  created or reused on switch-on and revoked on switch-off as well as when the plugin is
  disabled or uninstalled. "Entwickelt durch" on "Drittanwendungen" links to studapp2.de.
- **Token endpoint** – `POST /jsonapi.php/v1/app-connect/token` for the app client
  (`authorization_code`, `refresh_token`, `urn:studip:app-connect:pairing`); refreshing
  keeps the device binding.
- **Connect from the profile** – page Profile → Settings → "Stud.App Verbindung", right
  above "Drittanwendungen": a one-time pairing code (26 characters, five minutes, stored
  only as a hash) shown as QR code, live confirmation, a list of all devices with removal
  and ending sign-ins without a device. The first redemption of a code wins; failures are
  rate limited per address and globally (`429 slow_down`).
- **Registration after a browser sign-in** – `POST /jsonapi.php/v1/app-connect/devices`
  binds the calling token to a device (`201`, `409` when already bound).
- **Revocation** – removing a device revokes its whole token lineage, including tokens
  renewed at the core endpoint; revoking the app on "Drittanwendungen" ends the device as
  well.
- **Connection mail** – on every new connection (in the person's language), without
  code or IP address; the link uses the address recorded at switch-on.
- **Landing page** – `plugins.php/appconnect/get` with store links and "Open in the
  app"; the code stays in the URL fragment (`no-store`, `no-referrer`).
- **Calendar** – `GET /jsonapi.php/v1/app-connect/user-events` returns personal entries,
  dates of courses shown in the calendar and cancelled dates for two weeks, each course
  date with the person's colour group of the course (`color`), in the resource shapes of
  the core route `users/{id}/events`, every occurrence of a series as a resource of its
  own; feature `userEvents`, switch `APP_CONNECT_FEATURE_USER_EVENTS`.
- **Calendar key** – `GET`/`DELETE /jsonapi.php/v1/app-connect/me/ical-key` hands out the
  person's own iCal key and the `webcal://` address or resets it, only for the app's
  token; feature `icalKey`, switch `APP_CONNECT_FEATURE_ICAL_KEY`. New keys are made by
  the plugin (32 characters from `random_int`), at most five per person and hour (`429`);
  an existing key stays.
- **Places of course dates** – `GET /jsonapi.php/v1/app-connect/course-dates/places`
  returns the booked rooms of up to 50 dates with coordinates and a room finder link, or
  the free-text room; feature `eventPlaces`.
- **Language of the person** – `GET /jsonapi.php/v1/app-connect/user-language` returns the
  profile language or the site's default; feature `userLanguage`.
- **Web login from the app** – `POST /jsonapi.php/v1/app-connect/web-login` returns a
  one-time link (60 seconds, stored only as a hash); the browser asks "Als <name>
  anmelden?" before signing in. Feature `webLogin`, switch `APP_CONNECT_FEATURE_WEB_LOGIN`.
- **Courseware** – the discovery reports `courseware` while the core module Courseware is
  active; `APP_CONNECT_FEATURE_COURSEWARE` switches it off.
- **Operator settings** – configuration entries of the section "Stud.App" under Admin →
  System → Konfiguration: app connection, pairing, profile entry, name and short name,
  privacy policy, store links, support address, logo, accent colour, links, mensa source,
  room finder and feature switches. Addresses must be https.
- **Retention** – daily cronjob: pairing codes a day after they expired, web login links a
  day after they were issued, device rows a month after their refresh token expired;
  deleting an account removes its rows at once. Event log entries about the web login and
  the calendar key expire after 90 days.
- **Languages** – pages, connection mail and cronjob in German and English (own text
  domain `app-connect`); the pages address people formally, like the rest of Stud.IP.
- **Data export** – devices, pairing codes and web login links appear in the Stud.IP
  personal data view and export (`PrivacyPlugin`).
- **Page protection** – the plugin's pages may not be framed (`X-Frame-Options`,
  `frame-ancestors`).
- **License** – GPL-3.0-or-later with additional terms under section 7
  (`SUPPLEMENTAL-TERMS.txt`): attribution and license notice stay in the legal notices,
  modified versions must be marked. The help bar of "Stud.App connection" and "Get the app"
  explains the page and shows these notices at the end.
- **Contract** – JSON schema of the discovery, rules for connect links, landing page and
  routes, and shared test vectors for plugin and apps.
- **Documentation** – guides for users, operators and developers; `make dist` builds the
  plugin ZIP, the same commit always yields the same file.
