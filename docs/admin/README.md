# Betreiberhandbuch

**Deutsch** · [English](#english)

Mit app-connect verbinden sich die Stud.App-Apps (iOS, Android) mit eurer Stud.IP-Instanz.
Die Apps enthalten keine Daten einer Instanz; sie lesen alles aus diesem Plugin. Das Plugin
verändert den Stud.IP-Kern nicht: Alles, was die Apps über die JSON:API des Kerns hinaus
brauchen, kommt aus den eigenen Routen des Plugins.

## Voraussetzungen

- Stud.IP 6.0 oder eine neuere 6.x-Version, PHP 8.1 oder neuer. Das Plugin-ZIP braucht zur
  Laufzeit keine Composer-Pakete.
- HTTPS für die ganze Instanz.
- `$ABSOLUTE_URI_STUDIP` in `config/config_local.inc.php` gesetzt. Ohne diesen Wert leitet
  Stud.IP seine eigene Adresse aus dem `Host`-Header jeder Anfrage ab, und ein gefälschter
  Header kann eine fremde Adresse in erzeugte Links bringen. Das Plugin speichert die
  Adresse beim Einschalten der App-Verbindung und verwendet sie für den Link in der
  Verbindungsmail; Kern und Discovery verwenden die Adresse der Anfrage.
- Hinter einem Reverse-Proxy oder Load-Balancer muss `REMOTE_ADDR` die echte
  Client-Adresse sein (Apache `mod_remoteip`, nginx `real_ip`). Die Kopplung zählt
  Fehlversuche je Adresse; kommen alle Anfragen von der Adresse des Proxys, sperren wenige
  falsche Codes die Kopplung für alle.
- Der Cron-Worker von Stud.IP muss laufen: Der Aufräum-Job des Plugins löscht abgelaufene
  Daten.

## App-Verbindung einschalten

1. Plugin installieren und aktivieren (Admin → System → Plugins). Die Migrationen des
   Plugins laufen bei der Installation und bei jedem Update
   (`cli/studip plugin:migrate app-connect`).
2. Als `root` Admin → System → Konfiguration öffnen, Sektion **Stud.App**, und
   `APP_CONNECT_ENABLED` einschalten.
   - Das Plugin legt einen öffentlichen OAuth2-Client (PKCE, ohne Secret) für alle
     App-Installationen an oder verwendet den bisherigen wieder.
   - Diesen Client bitte nicht in der OAuth2-Verwaltung des Kerns bearbeiten; das Plugin
     verwaltet ihn.
   - Das Plugin gibt sich außerdem für die Rolle „Nobody“ frei, weil Discovery und
     Token-Endpunkt ohne Sitzung aufgerufen werden.
3. Optional, in derselben Sektion:

   | Eintrag | Bedeutung |
   |---|---|
   | `APP_CONNECT_PAIRING_ENABLED` | Verbinden per QR-Code aus dem Profil (ab Werk aus: einschalten, sobald die Instanz eingerichtet ist) |
   | `APP_CONNECT_PROFILE_ENTRY` | der Eintrag „Stud.App Verbindung“ unter Profil → Einstellungen (ab Werk an) |
   | `APP_CONNECT_INSTANCE_NAME`, `APP_CONNECT_INSTANCE_SHORT_NAME` | Name und Kurzname in der App |
   | `APP_CONNECT_PRIVACY_POLICY_URL` | Adresse eurer Datenschutzerklärung |
   | `APP_CONNECT_STORE_APP_STORE`, `APP_CONNECT_STORE_GOOGLE_PLAY` | Store-Links auf der Seite „App holen“ |
   | `APP_CONNECT_FEATURE_COURSE_CATALOGUE`, `…_BLUBBER`, `…_MENSA` | zusätzliche Funktionen der App |
   | `APP_CONNECT_ROOM_FINDER_PROPERTY` | Name der Raumeigenschaft mit dem Link zu eurem Raumfinder (z. B. `Lageplan`); die Apps zeigen ihn neben dem Raum eines Termins (nur https) |
   | `APP_CONNECT_SUPPORT_EMAIL` | Support-Adresse eurer Einrichtung; die Apps bieten sie für Hilfe und zum Melden von Inhalten an (Barrieren in der App gehen an den Stud.App-Support) |
   | `APP_CONNECT_LOGO_URL`, `APP_CONNECT_ACCENT_COLOR` | Logo (https) und Akzentfarbe (`#RRGGBB`); die Apps leiten daraus ein kontrastsicheres Farbschema ab |
   | `APP_CONNECT_LINK_HOMEPAGE`, `…_CAMPUS_MANAGEMENT`, `…_LIBRARY`, `…_IT_HELP` | Links in der App (https) |
   | `APP_CONNECT_MENSA_OPENMENSA_IDS`, `APP_CONNECT_MENSA_URL` | Quelle der Mensa: OpenMensa-IDs (durch Komma getrennt, die erste ist die Vorgabe) oder ein Link zum Speiseplan; nötig für `APP_CONNECT_FEATURE_MENSA` |
   | `APP_CONNECT_FEATURE_MESSAGES`, `APP_CONNECT_FEATURE_EVENT_PLACES` | Nachrichten und Orte von Terminen in der App (ab Werk an) |
   | `APP_CONNECT_FEATURE_COURSEWARE` | Courseware in der App lesen (ab Werk an; nur solange das Kernmodul Courseware aktiv ist) |
   | `APP_CONNECT_FEATURE_WEB_LOGIN` | Stud.IP-Seiten aus der App ohne erneute Anmeldung öffnen (ab Werk an, siehe [Anmeldung im Web aus der App](#anmeldung-im-web-aus-der-app)) |
   | `APP_CONNECT_FEATURE_ICAL_KEY` | Kalender aus der App abonnieren (ab Werk an, siehe [Kalender-Schlüssel](#kalender-schlüssel)) |

   Adressen müssen mit `https://` beginnen, die Support-Adresse muss eine E-Mail-Adresse
   sein, die Farbe `#RRGGBB` und die OpenMensa-IDs Zahlen. Der Editor des Kerns nimmt alles
   an; das Plugin zeigt deshalb nach dem Speichern einen Hinweis und verwendet andere Werte
   nicht. Die Adresse eures Stud.IP braucht keinen Eintrag: Das Plugin nimmt sie von der
   Instanz.

Ausschalten widerruft den Client: Angemeldete Apps verlieren sofort den Zugang. Erneutes
Einschalten verwendet denselben Client wieder.

## Geräte verbinden

Personen ab Status `autor` finden **Stud.App Verbindung** unter Profil → Einstellungen,
direkt über „Drittanwendungen“. Dort können sie

- einen QR-Code mit einmaligem Kopplungscode erzeugen (fünf Minuten gültig, der erste Scan
  gewinnt; die Datenbank speichert nur den Hash),
- jedes verbundene Gerät sehen – per QR-Code oder per Browser-Anmeldung – und entfernen;
  Entfernen beendet den Zugang des Geräts sofort, auch für Tokens, die das Gerät später
  erneuert hat, und das Gerät lässt sich danach wieder verbinden,
- Anmeldungen der App beenden, die keinem Gerät zugeordnet sind.

Verbundene Apps stehen außerdem unter „Drittanwendungen“ (durch den Kern). Verwaltet und
entfernt werden Geräte auf „Stud.App Verbindung“; ein Widerruf unter „Drittanwendungen“
beendet das Gerät ebenfalls.

Jede neue Verbindung schickt eine Mail an das Konto mit Gerätename, Plattform, Zeitpunkt und
einem Link zu „Stud.App Verbindung“. In der Sektion „Stud.App“ lassen sich der Eintrag
„Stud.App Verbindung“ oder nur der QR-Code ausschalten (die Apps verbinden sich dann über
die Browser-Anmeldung) und die Links für App Store und Google Play setzen, die die
öffentliche Seite `plugins.php/appconnect/get` zeigt. Diese Seite öffnet der QR-Code, wenn
die App nicht installiert ist.

Fehlgeschlagene Kopplungsversuche sind auf 10 je Adresse in 10 Minuten und 100 je Minute
insgesamt begrenzt.

## Anmeldung im Web aus der App

Öffnet eine App eine Seite der Weboberfläche (Eintragen in eine Veranstaltung, Courseware,
die die App nicht darstellen kann, „In Stud.IP öffnen“), fragt sie das Plugin nach einem
einmaligen Link (60 Sekunden gültig, nur als Hash gespeichert, höchstens 10 je Person in
5 Minuten). Der Browser fragt „Als <Name> anmelden?“ und meldet erst nach Bestätigung an;
ist in diesem Browser jemand anderes angemeldet, sagt die Seite das vorher. Ist dieselbe
Person schon angemeldet, öffnet sich die Seite direkt. Die Bestätigung verhindert, dass ein
Link aus einem fremden Konto einen Browser unbemerkt in dieses Konto anmeldet.

Ein zweiter Faktor eures Identity Providers (Shibboleth, OIDC) wird dabei nicht abgefragt,
die Zwei-Faktor-Authentisierung von Stud.IP schon. Schaltet `APP_CONNECT_FEATURE_WEB_LOGIN`
aus, wenn jede Web-Sitzung über euren Identity Provider laufen muss. Einzelheiten:
[`contract/web-login.md`](../../contract/web-login.md).

## Kalender-Schlüssel

Stud.IP liefert den persönlichen Kalender einer Person unter
`dispatch.php/ical/index/<Schlüssel>` ohne Sitzung aus; der Schlüssel ist die ganze
Berechtigung. Die App holt diesen Schlüssel über das Plugin, damit die Person den Kalender
mit einem Schritt im Gerätekalender abonnieren kann, und kann ihn zurücksetzen. Das geht
nur mit dem Token der App, nie mit dem Token einer anderen Anwendung oder mit Passwort.
Schlüssel und Erzeugung bleiben die des Kerns (`IcalExport`). Ausgeschaltet
(`APP_CONNECT_FEATURE_ICAL_KEY`) bleibt der Weg über die Kalender-Seite im Browser.
Einzelheiten: [`contract/ical-key.md`](../../contract/ical-key.md).

## API-Endpunkte des Plugins

Alle Routen liegen unter `/jsonapi.php/v1/`. Fehler sind JSON:API-Fehlerdokumente mit einem
Anwendungscode (`errors[0].code`); der Token-Endpunkt antwortet mit OAuth2-Fehlern
(`invalid_grant`, `invalid_client`, …) nach RFC 6749. Jede Route antwortet
`404 app-connect-disabled`, solange `APP_CONNECT_ENABLED` aus ist.

| Route | Anmeldung | Funktion |
|---|---|---|
| `GET app-connect/discovery` | keine | Öffentliche Instanzdaten, mit denen die Apps starten: Name, Adressen, Client-ID und Endpunkte des OAuth2-Clients, eingeschaltete Funktionen und – falls gesetzt – Gestaltung, Links, Mensa-Quelle. Aus einer festen Feldliste; enthält nie Geheimnisse. `ETag`/`304`, 5 Minuten cachebar. Schema: [`contract/discovery.schema.json`](../../contract/discovery.schema.json) |
| `POST app-connect/token` | keine (Client-ID der App) | Token-Endpunkt des App-Clients: `authorization_code` (Browser-Anmeldung mit PKCE), `refresh_token` (behält die Gerätebindung) und der Kopplungs-Grant (QR-Code, `urn:studip:app-connect:pairing`). Andere Clients werden abgewiesen. Fehlgeschlagene Kopplungen sind begrenzt (`429 slow_down`) |
| `POST app-connect/devices` | App-Token | Registriert das Gerät nach einer Browser-Anmeldung und schickt die Verbindungsmail. `201`; `409 app-connect-already-registered`, wenn die Anmeldung schon gebunden ist; `401`/`403` für andere Tokens |
| `GET app-connect/course-dates/places?filter[ids]=…` | ja | Wo bis zu 50 Termine stattfinden: gebuchte Räume mit Koordinaten (Raum, Gebäude oder Standort) und Raumfinder-Link, sonst die Freitext-Raumangabe. Termine, die die Person nicht sehen darf, fehlen. Funktion `eventPlaces`. [`contract/course-date-places.md`](../../contract/course-date-places.md) |
| `GET app-connect/user-language` | ja | Die Sprache, in der die Person Stud.IP nutzt (Profil, sonst die Vorgabe der Instanz). Funktion `userLanguage`. [`contract/user-language.md`](../../contract/user-language.md) |
| `GET app-connect/user-events?filter[timestamp]=…` | ja | Der Kalender der Person für zwei Wochen wie im Web-Kalender: eigene Termine, Termine der im Kalender angezeigten Veranstaltungen und Ausfälle, jeder Veranstaltungstermin mit der Farbgruppe der Person (`color`, 0–8). Dieselben Ressourcen wie die Kern-Route `users/{id}/events`. Funktion `userEvents`. [`contract/user-events.md`](../../contract/user-events.md) |
| `POST app-connect/web-login` | App-Token oder Passwort | Einmaliger Link für die Anmeldung im Web (oben). `429` nach 10 Links in 5 Minuten. [`contract/web-login.md`](../../contract/web-login.md) |
| `GET`/`DELETE app-connect/me/ical-key` | App-Token | Der Kalender-Schlüssel der Person und die `webcal://`-Adresse; der erste Aufruf legt einen Schlüssel an, `DELETE` setzt ihn zurück und beendet jedes Abo darauf. `401`/`403` für andere Anmeldungen. Funktion `icalKey`. [`contract/ical-key.md`](../../contract/ical-key.md) |

Seiten des Plugins (`plugins.php/appconnect/…`):

| Seite | Anmeldung | Funktion |
|---|---|---|
| `connect` | ab `autor` | „Stud.App Verbindung“: QR-Code, Geräteliste, Geräte entfernen |
| `get` | keine | „App holen“: Store-Links und „In der App öffnen“; der Kopplungscode bleibt im URL-Fragment |
| `web/enter`, `web/redeem`, `web/confirm` | keine | Anmeldung im Web aus der App |

Zu welcher Funktion eine Route gehört, kündigt die Discovery an; die Apps nutzen eine Route
nur, wenn ihre Funktion angekündigt ist. Die Funktionsliste steuert, was die App zeigt; sie
ist kein Zugriffsschutz: Die JSON:API-Routen des Kerns bleiben für angemeldete Personen
trotzdem erreichbar.

## Was die Apps vom Stud.IP-Kern nutzen

Alles Weitere kommt aus der JSON:API des Kerns mit dem OAuth2-Token der Person, ohne
Kernänderung: Veranstaltungen, Semester, Mitgliedschaften (mit der Farbgruppe, Attribut
`group`; die Apps können sie mit `PATCH course-memberships/{id}` ändern), Termine je
Veranstaltung (`courses/{id}/events`) und der persönliche Kalender, Stundenplan, Ordner und
Dateien (Download über `file-refs/{id}/content`), Ankündigungen, Nachrichten, Blubber,
Courseware sowie Bilder von Personen und Veranstaltungen (`meta.avatar`).

Mit eingeschaltetem `display_errors` landen PHP-Warnungen der Kern-Route
`file-refs/{id}/content` in der heruntergeladenen Datei. Im Betrieb `display_errors`
ausgeschaltet lassen.

## Aufbewahrung

Ein Cronjob „app-connect: Aufräumen“ (vom Plugin angelegt und eingeschaltet, täglich um
03:17; Admin → System → Cronjobs) löscht

- Kopplungscodes einen Tag nach Ablauf,
- Links für die Anmeldung im Web einen Tag nach Ausstellung,
- Gerätezeilen einen Monat nach Ablauf des Refresh-Tokens des Geräts – entfernte Geräte
  ebenso wie Geräte, die nicht mehr genutzt wurden.

Wird ein Konto gelöscht, verschwinden seine Geräte, Codes und Links sofort.
Ereignisprotokoll-Einträge zu Web-Anmeldungen und zum Kalender-Schlüssel löscht der Kern
nach 90 Tagen; die übrigen Einträge des Plugins folgen euren Einstellungen des
Ereignisprotokolls.

| Daten | Ort | Form |
|---|---|---|
| Kopplungscodes | `app_connect_pairing_codes` | SHA-256-Hash, Person, Geräte-ID, Zeitpunkte |
| Geräte | `app_connect_devices` | in der App gewählter Gerätename, Plattform, Art der Verbindung, IDs der OAuth2-Tokens (nicht die Tokens), Zeitpunkte |
| Links für die Anmeldung im Web | `app_connect_web_login_tickets` | SHA-256-Hash, Person, zu öffnende Seite, Verweis auf Token und Gerät, Zeitpunkte |
| Kalender-Schlüssel | Konfiguration der Person (Kern, `IcalExport`) | wie vom Kern verwaltet; das Plugin legt ihn nur an oder löscht ihn |
| Einstellungen | `config` (Sektion „Stud.App“), `app_connect_settings` | eure Einstellungen, Client-ID, gespeicherte Adresse |
| Fehlversuche beim Koppeln | Stud.IP-Cache | Zähler je gehashter Adresse, 10 Minuten |

## Ereignisprotokoll

Ein- und Ausschalten, Anlegen oder Wiederverwenden des Clients, geänderte Einstellungen,
Anmeldungen im Web (angefordert, angemeldet, abgelehnt) sowie Anlegen und Zurücksetzen des
Kalender-Schlüssels stehen im Ereignisprotokoll von Stud.IP (Aktionen `APPCONNECT_*`) – mit
der Person, nie mit Link oder Schlüssel.

## Lizenzhinweis

Die Hilfelasche („Hilfe“) von „Stud.App Verbindung“ und „App holen“ nennt Autor,
Quellcode-Repository und Lizenz (GPL-3.0-or-later mit den Zusatzbedingungen in
`SUPPLEMENTAL-TERMS.txt`). Die Zusatzbedingungen verlangen diesen Hinweis; eine geänderte
Fassung darf ihn verschieben, muss ihn aber sichtbar lassen. Das Plugin-Verzeichnis enthält
`LICENSE` und `SUPPLEMENTAL-TERMS.txt`.

---

## English

app-connect lets the Stud.App apps (iOS, Android) connect to your Stud.IP instance. The
apps contain no data about any instance; they read everything from this plugin. The plugin
does not change the Stud.IP core: everything the apps need beyond the core's JSON:API comes
from the plugin's own routes.

### Requirements

- Stud.IP 6.0 or a later 6.x release, PHP 8.1 or later. The plugin ZIP needs no Composer
  packages at runtime.
- HTTPS for the whole instance.
- `$ABSOLUTE_URI_STUDIP` set in `config/config_local.inc.php`. Without it Stud.IP derives
  its own address from the `Host` header of each request, and a forged header could put a
  foreign address into generated links. The plugin records the address when you switch the
  app connection on and uses it for the link in the connection mail; the core and the
  discovery use the request's address.
- Behind a reverse proxy or load balancer, `REMOTE_ADDR` must be the real client address
  (Apache `mod_remoteip`, nginx `real_ip`). The pairing limits count failed attempts per
  address; if every request comes from the proxy's address, a few wrong codes block pairing
  for everyone.
- The Stud.IP cron worker must run: the plugin's cleanup job deletes expired data.

### Switching the app connection on

1. Install and activate the plugin (Admin → System → Plugins). The plugin's migrations run
   on installation and on every update (`cli/studip plugin:migrate app-connect`).
2. As `root`, open Admin → System → Konfiguration and the section **Stud.App**, and set
   `APP_CONNECT_ENABLED` to on.
   - The plugin creates one public OAuth2 client (PKCE, no secret) for all app
     installations, or reuses the one it created before.
   - Please do not edit this client in the core OAuth2 administration; the plugin manages
     it.
   - The plugin also allows the role "Nobody" to load it, because the discovery and the
     token endpoint are called without a session.
3. Optional, in the same section:

   | Entry | Meaning |
   |---|---|
   | `APP_CONNECT_PAIRING_ENABLED` | connecting by QR code from the profile (off by default: switch it on once the instance is configured) |
   | `APP_CONNECT_PROFILE_ENTRY` | the entry "Stud.App Verbindung" under Profile → Settings (on by default) |
   | `APP_CONNECT_INSTANCE_NAME`, `APP_CONNECT_INSTANCE_SHORT_NAME` | name and short name shown in the app |
   | `APP_CONNECT_PRIVACY_POLICY_URL` | address of your privacy policy |
   | `APP_CONNECT_STORE_APP_STORE`, `APP_CONNECT_STORE_GOOGLE_PLAY` | store links on the page "App holen" |
   | `APP_CONNECT_FEATURE_COURSE_CATALOGUE`, `…_BLUBBER`, `…_MENSA` | additional features of the app |
   | `APP_CONNECT_ROOM_FINDER_PROPERTY` | name of the room property that holds a link to your room finder (e.g. `Lageplan`); the apps show it next to the room of a course date (https only) |
   | `APP_CONNECT_SUPPORT_EMAIL` | support address of your institution; the apps offer it for help and for reporting content (barriers in the app go to the Stud.App support) |
   | `APP_CONNECT_LOGO_URL`, `APP_CONNECT_ACCENT_COLOR` | logo (https) and accent colour (`#RRGGBB`); the apps derive a contrast-safe colour scheme from the accent |
   | `APP_CONNECT_LINK_HOMEPAGE`, `…_CAMPUS_MANAGEMENT`, `…_LIBRARY`, `…_IT_HELP` | links shown in the app (https) |
   | `APP_CONNECT_MENSA_OPENMENSA_IDS`, `APP_CONNECT_MENSA_URL` | mensa source: OpenMensa ids (comma separated, the first is the default) or a web menu link; needed for `APP_CONNECT_FEATURE_MENSA` |
   | `APP_CONNECT_FEATURE_MESSAGES`, `APP_CONNECT_FEATURE_EVENT_PLACES` | messages and places of course dates in the app (on by default) |
   | `APP_CONNECT_FEATURE_COURSEWARE` | reading Courseware in the app (on by default; only while the core module Courseware is active) |
   | `APP_CONNECT_FEATURE_WEB_LOGIN` | web pages opened from the app without signing in again (on by default, see [Web login from the app](#web-login-from-the-app)) |
   | `APP_CONNECT_FEATURE_ICAL_KEY` | subscribing to the calendar from the app (on by default, see [Calendar key](#calendar-key)) |

   Addresses must start with `https://`, the support address must be an e-mail address,
   the colour `#RRGGBB` and the OpenMensa ids numbers; the core editor accepts anything, so
   the plugin shows a warning after saving and does not use other values. The address of
   your Stud.IP needs no entry: the plugin takes it from the instance.

Switching off revokes the client: signed-in apps lose access immediately. Switching on
again reuses the same client.

### Connecting devices

People with at least `autor` status find **Stud.App Verbindung** under Profile → Settings,
right above "Drittanwendungen". There they

- create a QR code with a one-time pairing code (valid five minutes, the first scan wins;
  the database keeps only its hash),
- see every connected device – by QR code or by browser sign-in – and remove it; removing
  ends the device's access at once, including tokens the device renewed later, and the
  device can be connected again afterwards,
- end app sign-ins that are not assigned to a device.

Once connected, the app is also listed on "Drittanwendungen" (by the core). Devices are
managed and removed on "Stud.App Verbindung"; revoking the app on "Drittanwendungen" ends
the device as well.

Every new connection sends a mail to the account with the device name, the platform, the
time and a link to "Stud.App Verbindung". In the configuration section "Stud.App" you can
switch off the entry "Stud.App Verbindung" or only the QR code (the apps then connect
through the browser sign-in), and set the App Store and Google Play links shown on the
public page `plugins.php/appconnect/get`, which the QR code opens when the app is not
installed.

Failed pairing attempts are limited to 10 per address in 10 minutes and 100 per minute
overall.

### Web login from the app

When an app opens a page of the web interface (course enrolment, Courseware that the app
cannot show, "Open in Stud.IP"), it asks the plugin for a one-time link (valid 60 seconds,
stored only as a hash, at most 10 per person in 5 minutes). The browser shows "Als <name>
anmelden?" and signs in only after the person confirms; if someone else is signed in in
that browser, the page says so first. If the same person is signed in already, the page
opens directly. The confirmation prevents a link made from someone else's account from
signing a browser into that account unnoticed.

A second factor of your identity provider (Shibboleth, OIDC) is not asked for; Stud.IP's
own two-factor authentication still is. Switch `APP_CONNECT_FEATURE_WEB_LOGIN` off if every
web session must pass your identity provider. Details:
[`contract/web-login.md`](../../contract/web-login.md).

### Calendar key

Stud.IP serves a person's calendar at `dispatch.php/ical/index/<key>` without a session;
the key is the whole authorisation. The app fetches this key through the plugin, so the
person can subscribe the calendar in the device calendar in one step, and can reset it.
This works only with the app's token, never with another application's token or a
password. The key and its generator stay the core's (`IcalExport`). Switched off
(`APP_CONNECT_FEATURE_ICAL_KEY`), the way through the calendar page in the browser remains.
Details: [`contract/ical-key.md`](../../contract/ical-key.md).

### API endpoints of the plugin

All routes live below `/jsonapi.php/v1/`. Errors are JSON:API error documents with an
application code (`errors[0].code`); the token endpoint answers OAuth2 errors
(`invalid_grant`, `invalid_client`, …) as RFC 6749 describes. Every route answers
`404 app-connect-disabled` while `APP_CONNECT_ENABLED` is off.

| Route | Sign-in | What it does |
|---|---|---|
| `GET app-connect/discovery` | none | Public instance data the apps start with: name, addresses, OAuth2 client id and endpoints, enabled features, and – if you set them – branding, links, mensa source. Built from a fixed list of fields; never contains secrets. `ETag`/`304`, cacheable for 5 minutes. Schema: [`contract/discovery.schema.json`](../../contract/discovery.schema.json) |
| `POST app-connect/token` | none (app client id) | Token endpoint of the app client: `authorization_code` (browser sign-in with PKCE), `refresh_token` (keeps the device binding) and the pairing grant (QR code, `urn:studip:app-connect:pairing`). Other clients are refused. Failed pairing attempts are rate limited (`429 slow_down`) |
| `POST app-connect/devices` | app token | Registers the device after a browser sign-in and sends the connection mail. `201`; `409 app-connect-already-registered` when the sign-in is bound already; `401`/`403` for other tokens |
| `GET app-connect/course-dates/places?filter[ids]=…` | yes | Where up to 50 course dates take place: booked rooms with coordinates (room, building or site) and the room finder link, else the free-text room. Dates the person may not see are left out. Feature `eventPlaces`. [`contract/course-date-places.md`](../../contract/course-date-places.md) |
| `GET app-connect/user-language` | yes | The language the person uses Stud.IP in (profile, else the site default). Feature `userLanguage`. [`contract/user-language.md`](../../contract/user-language.md) |
| `GET app-connect/user-events?filter[timestamp]=…` | yes | The person's calendar for two weeks as the web calendar shows it: personal entries, dates of courses shown in the calendar and cancelled dates, each course date with the person's colour group of the course (`color`, 0–8). Same resource shapes as the core route `users/{id}/events`. Feature `userEvents`. [`contract/user-events.md`](../../contract/user-events.md) |
| `POST app-connect/web-login` | app token or password | One-time link for the web login (above). `429` after 10 links in 5 minutes. [`contract/web-login.md`](../../contract/web-login.md) |
| `GET`/`DELETE app-connect/me/ical-key` | app token | The person's calendar key and the `webcal://` address; the first call creates a key, `DELETE` resets it and stops every subscription built on it. `401`/`403` for other sign-ins. Feature `icalKey`. [`contract/ical-key.md`](../../contract/ical-key.md) |

Pages of the plugin (`plugins.php/appconnect/…`):

| Page | Sign-in | What it does |
|---|---|---|
| `connect` | `autor` and up | "Stud.App Verbindung": QR code, device list, removing devices |
| `get` | none | "App holen": store links and "In der App öffnen"; the pairing code stays in the URL fragment |
| `web/enter`, `web/redeem`, `web/confirm` | none | web login from the app |

The features a route belongs to are announced in the discovery; the apps use a route only
when its feature is announced. The feature list decides what the app shows; it is not
access control: the core's JSON:API routes stay reachable for signed-in users regardless.

### What the apps use from the Stud.IP core

Everything else comes from the core's JSON:API with the person's OAuth2 token, without
changes to the core: courses, semesters, course memberships (including the colour group,
attribute `group`; the apps may change it with `PATCH course-memberships/{id}`), course
dates (`courses/{id}/events`) and the personal calendar, schedule entries, folders and
files (download via `file-refs/{id}/content`), news, messages, Blubber, Courseware, and
user and course pictures (`meta.avatar`).

With `display_errors` on, PHP warnings of the core route `file-refs/{id}/content` end up in
the downloaded file. Keep `display_errors` off in production.

### Retention

A cronjob "app-connect: Aufräumen" (registered and switched on by the plugin, daily at
03:17; Admin → System → Cronjobs) deletes

- pairing codes a day after they expired,
- web login links a day after they were issued,
- device rows a month after the device's refresh token expired – removed devices as well
  as devices that were not used again.

When an account is deleted, its devices, codes and web login links are removed at once.
Event log entries about web logins and the calendar key are deleted by the core after 90
days; the other entries of the plugin follow your event log settings.

| Data | Where | Form |
|---|---|---|
| pairing codes | `app_connect_pairing_codes` | SHA-256 hash, person, device id, times |
| devices | `app_connect_devices` | device name chosen in the app, platform, how it was connected, ids of the OAuth2 tokens (not the tokens), times |
| web login links | `app_connect_web_login_tickets` | SHA-256 hash, person, page to open, token/device reference, times |
| calendar key | the person's configuration (core, `IcalExport`) | as the core keeps it; the plugin only creates or deletes it |
| settings | `config` (section "Stud.App"), `app_connect_settings` | your settings, client id, recorded address |
| failed pairing attempts | Stud.IP cache | counter per hashed address, 10 minutes |

### Event log

Switching on and off, creating or reusing the client, changing settings, web logins
(requested, signed in, refused) and creating or resetting the calendar key are recorded in
the Stud.IP event log (actions `APPCONNECT_*`) – with the person, never the link or the
key.

### License notice

The help bar ("Hilfe") of "Stud.App Verbindung" and "App holen" names the author, the
source-code repository and the license (GPL-3.0-or-later with the additional terms in
`SUPPLEMENTAL-TERMS.txt`). The additional terms require this notice; a modified version may
move it but must keep it visible. The plugin directory contains `LICENSE` and
`SUPPLEMENTAL-TERMS.txt`.
