# Betreiberhandbuch

**Deutsch** · [English](#english)

Mit app-connect verbinden sich die Stud.App-Apps (iOS, Android) mit eurer Stud.IP-Instanz.
Die Apps enthalten keine Daten einer Instanz; sie lesen alles aus diesem Plugin. Das Plugin
verändert den Stud.IP-Kern nicht: Alles, was die Apps über die JSON:API des Kerns hinaus
brauchen, kommt aus den eigenen Routen des Plugins.

## Voraussetzungen

- Stud.IP 6.0 bis 6.3, PHP 8.1 oder neuer. Das Plugin-ZIP braucht zur
  Laufzeit keine Composer-Pakete.
- HTTPS für die ganze Instanz.
- Stud.IP im Wurzelpfad seines Servers, etwa `https://studip.example.org/`. Die
  Verbindungslinks tragen nur den Server, deshalb erreichen die Apps eine Instanz unter einem
  Unterpfad wie `https://example.org/studip/` nicht. Enthält `$ABSOLUTE_URI_STUDIP` einen
  Pfad, lässt sich die App-Verbindung nicht einschalten.
- `$ABSOLUTE_URI_STUDIP` in `config/config_local.inc.php` gesetzt. Ohne diesen Wert leitet
  Stud.IP seine eigene Adresse aus dem `Host`-Header jeder Anfrage ab, und ein gefälschter
  Header kann eine fremde Adresse in erzeugte Links bringen. Das Plugin speichert die
  Adresse beim Einschalten der App-Verbindung und verwendet sie für den Link in der
  Verbindungsmail; Kern und Discovery verwenden die Adresse der Anfrage.
- Hinter einem Reverse-Proxy oder Load-Balancer muss `REMOTE_ADDR` die echte
  Client-Adresse sein (Apache `mod_remoteip`, nginx `real_ip`, jeweils nur für die Adressen
  eurer Proxys). Das Plugin liest `REMOTE_ADDR` wie der Kern; Stud.IP wertet
  `X-Forwarded-For` nicht aus, und das Plugin tut es auch nicht, weil ein Client diesen
  Header selbst setzen kann. Die Kopplung zählt Fehlversuche je Adresse, der
  Widerrufs-Endpunkt Anfragen je Adresse. Kommen alle Anfragen von der Adresse des Proxys,
  teilen sich alle Personen einen Zähler: Wenige falsche Codes sperren dann die Kopplung für
  alle, und nach 300 Abmeldungen in 10 Minuten widerruft der Server keine weiteren Tokens
  (die Apps melden sich trotzdem ab, das Token bleibt dann bis zu seinem Ablauf gültig).
- Der Cron-Worker von Stud.IP muss laufen: Der Aufräum-Job des Plugins löscht abgelaufene
  Daten.

## App-Verbindung einschalten

1. Das Plugin-ZIP `app-connect-<Version>.zip` aus den
   [Releases](https://github.com/sys-cr/app-connect/releases/latest) herunterladen, als `root` unter Admin → System →
   Plugins hochladen und aktivieren. Wer aus dem Quellcode baut, erhält mit `make dist`
   dasselbe ZIP unter `dist/`. Die Migrationen des Plugins laufen bei der Installation und
   bei jedem Update (`cli/studip plugin:migrate app-connect`).
2. Als `root` Admin → System → Konfiguration öffnen, Sektion **Stud.App**, und
   `APP_CONNECT_ENABLED` einschalten.
   - Das Plugin legt einen öffentlichen OAuth2-Client (PKCE, ohne Secret) für alle
     App-Installationen an oder verwendet den bisherigen wieder.
   - Diesen Client bitte nicht in der OAuth2-Verwaltung des Kerns bearbeiten; das Plugin
     verwaltet ihn.
   - Das Plugin gibt sich außerdem für die Rolle „Nobody“ frei, weil Discovery, Token- und
     Widerrufs-Endpunkt und das Logo ohne Sitzung aufgerufen werden.
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
   | `APP_CONNECT_SUPPORT_EMAIL` | Support-Adresse eurer Einrichtung; die Apps bieten sie für Hilfe und zum Melden von Inhalten an (Barrieren in der App gehen an den Stud.App-Support). **Bitte eintragen:** Ohne Adresse gehen Meldungen von Inhalten an support@studapp2.de, den Support des Projekts Stud.App, und nicht an eure Hochschule. Solange die Adresse fehlt, zeigen die Konfiguration nach dem Speichern und „Stud.App Verbindung“ für `root` einen Hinweis |
   | `APP_CONNECT_LOGO_URL` | Adresse des Logos (https); das Plugin ruft das Bild ab und liefert es den Apps von diesem Server aus, siehe [Logo](#logo) |
   | `APP_CONNECT_ACCENT_COLOR` | Akzentfarbe (`#RRGGBB`); die Apps leiten daraus ein kontrastsicheres Farbschema ab |
   | `APP_CONNECT_LINK_HOMEPAGE`, `…_CAMPUS_MANAGEMENT`, `…_LIBRARY`, `…_IT_HELP` | Links in der App (https) |
   | `APP_CONNECT_MENSA_OPENMENSA_IDS`, `APP_CONNECT_MENSA_URL` | Quelle der Mensa: OpenMensa-IDs (durch Komma getrennt, die erste ist die Vorgabe) oder ein Link zum Speiseplan; nötig für `APP_CONNECT_FEATURE_MENSA` |
   | `APP_CONNECT_FEATURE_MESSAGES`, `APP_CONNECT_FEATURE_EVENT_PLACES` | Nachrichten und Orte von Terminen in der App (ab Werk an) |
   | `APP_CONNECT_FEATURE_COURSEWARE` | Courseware in der App lesen (ab Werk an; nur solange das Kernmodul Courseware aktiv ist) |
   | `APP_CONNECT_FEATURE_WEB_LOGIN` | Stud.IP-Seiten aus der App ohne erneute Anmeldung öffnen (ab Werk an, siehe [Anmeldung im Web aus der App](#anmeldung-im-web-aus-der-app)) |
   | `APP_CONNECT_FEATURE_ICAL_KEY` | Kalender aus der App abonnieren (ab Werk an, siehe [Kalender-Schlüssel](#kalender-schlüssel)) |
   | `APP_CONNECT_FEATURE_USER_EVENTS` | Kalender der Person in einem Abruf mit den Farben der Veranstaltungen (ab Werk an); aus setzt die App ihn aus den Routen des Kerns zusammen |

   Adressen müssen mit `https://` beginnen, die Support-Adresse muss eine E-Mail-Adresse
   sein, die Farbe `#RRGGBB` und die OpenMensa-IDs Zahlen. Der Editor des Kerns nimmt alles
   an; das Plugin zeigt deshalb nach dem Speichern einen Hinweis und verwendet andere Werte
   nicht. Die Adresse eures Stud.IP braucht keinen Eintrag: Das Plugin nimmt sie von der
   Instanz.

   Die Schalter `APP_CONNECT_FEATURE_*` steuern, was die App anbietet, und sind keine
   Berechtigungen. Die App erhält den OAuth2-Scope `api` und erreicht damit die JSON:API so
   weit, wie der Kern sie der Person freigibt, auch bei ausgeschaltetem Schalter. Serverseitig
   gesperrt werden nur die Routen des Plugins: Web-Anmeldung, Kalender-Schlüssel, Kalender der
   Person und Orte von Terminen. Soll ein Zugriff wirklich verboten sein, etwa auf
   Nachrichten oder Blubber, regelt ihr das über die Rechte, Module und Einstellungen des
   Kerns.

Ausschalten widerruft den Client: Angemeldete Apps verlieren sofort den Zugang. Erneutes
Einschalten verwendet denselben Client wieder. Dasselbe gilt, wenn ihr das Plugin in der
Plugin-Verwaltung deaktiviert oder deinstalliert: Der Client wird widerrufen. Aktiviert ihr
es wieder, während `APP_CONNECT_ENABLED` an ist, gilt der Client wieder.

## Logo

Die Apps laden das Logo nur vom Server eurer Stud.IP-Instanz: Ein Bild von einem anderen
Server verriete diesem, wann jemand die App öffnet. Das Plugin ruft deshalb die Adresse aus
`APP_CONNECT_LOGO_URL` selbst ab, prüft das Bild, speichert eine Kopie und liefert sie unter
`jsonapi.php/v1/app-connect/logo` aus. Die Discovery nennt diese Adresse als
`branding.logoUrl`, mit einer Version (`?v=…`), die sich mit jedem neuen Bild ändert. Das
gilt auch, wenn das Logo schon auf dem Stud.IP-Server liegt.

- Abgerufen wird gleich nach dem Speichern der Adresse und danach täglich durch den
  Cronjob „app-connect: Aufräumen“. Für root zeigt „Stud.App Verbindung“ das gespeicherte
  Logo, den Zeitpunkt des Abrufs oder den Grund der Ablehnung und die Schaltfläche „Logo neu
  laden“.
- Angenommen werden PNG, JPEG und WebP bis 512 KB mit 32 bis 2048 Pixeln je Seite. Die Art
  erkennt das Plugin an den Bytes, nicht am `Content-Type`. SVG wird abgelehnt: Die Apps
  zeigen nur Rastergrafiken, und ein SVG kann Skripte enthalten.
- Nur `https://` ohne Zugangsdaten, 5 Sekunden je Anfrage, höchstens drei Weiterleitungen,
  jede wieder auf `https://`. Adressen, deren Server in ein internes Netz zeigt (Loopback,
  private Netze, Link-Local, reservierte Bereiche), ruft das Plugin nicht ab; ausgenommen
  ist der Server eurer Instanz selbst. Ist `HTTP_PROXY` gesetzt, geht der Abruf wie die
  Anfragen des Kerns über den Proxy (außer für Server in `HTTP_PROXY_IGNORE`).
- Scheitert ein späterer Abruf derselben Adresse, liefert das Plugin weiter das zuletzt
  geladene Bild aus und zeigt den Fehler. Eine neue Adresse ersetzt das alte Bild; gelingt
  ihr Abruf nicht, bekommen die Apps kein Logo, bis er gelingt.
- Ohne gültiges Bild fehlt `branding.logoUrl` in der Discovery, und die Apps zeigen ein
  allgemeines Symbol. Leert ihr `APP_CONNECT_LOGO_URL`, löscht das Plugin die Kopie.
- Statt einer Adresse kann root auf „Stud.App Verbindung“ eine Bilddatei hochladen. Sie
  durchläuft dieselben Prüfungen (PNG, JPEG oder WebP, kein SVG) und hat Vorrang: Solange
  sie da ist, ruft das Plugin die Adresse nicht ab und liefert sie nicht aus. „Hochgeladenes
  Logo entfernen“ schaltet zurück auf die Adresse und ruft sie gleich ab.

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
beendet das Gerät ebenfalls. Meldet sich jemand in der App ab, widerruft die App ihre Tokens
am Widerrufs-Endpunkt des Plugins; das Gerät gilt dann als entfernt.

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
Der Schlüssel liegt dort, wo der Kern ihn ablegt, und der Export des Kerns liefert den
Kalender wie gewohnt aus. Neue Schlüssel erzeugt das Plugin aber selbst: 32 Kleinbuchstaben
und Ziffern aus einem kryptografischen Zufallsgenerator statt der acht Zeichen aus `rand()`,
die der Kern auf der Kalender-Seite vergibt. Eine Person bekommt höchstens fünf neue
Schlüssel pro Stunde (`429`). Ein vorhandener Schlüssel bleibt, bis die Person ihn
zurücksetzt, damit bestehende Abos weiterlaufen. Ausgeschaltet
(`APP_CONNECT_FEATURE_ICAL_KEY`) bleibt der Weg über die Kalender-Seite im Browser.
Einzelheiten: [`contract/ical-key.md`](../../contract/ical-key.md).

## API-Endpunkte des Plugins

Alle Routen liegen unter `/jsonapi.php/v1/`. Fehler sind JSON:API-Fehlerdokumente mit einem
Anwendungscode (`errors[0].code`); Token- und Widerrufs-Endpunkt antworten mit
OAuth2-Fehlern (`invalid_grant`, `invalid_client`, …) nach RFC 6749. Jede Route antwortet
`404 app-connect-disabled`, solange `APP_CONNECT_ENABLED` aus ist.

| Route | Anmeldung | Funktion |
|---|---|---|
| `GET app-connect/discovery` | keine | Öffentliche Instanzdaten, mit denen die Apps starten: Name, Adressen, Client-ID und Endpunkte des OAuth2-Clients, eingeschaltete Funktionen und – falls gesetzt – Gestaltung, Links, Mensa-Quelle. Aus einer festen Feldliste; enthält nie Geheimnisse. `ETag`/`304`, 5 Minuten cachebar. Schema: [`contract/discovery.schema.json`](../../contract/discovery.schema.json) |
| `GET app-connect/logo` | keine | Die Kopie des Logos (siehe [Logo](#logo)) mit dem geprüften `Content-Type`, `ETag`/`304`, einen Tag cachebar; `404 app-connect-no-logo` ohne gültige Kopie. In der Discovery als `branding.logoUrl` |
| `POST app-connect/token` | keine (Client-ID der App) | Token-Endpunkt des App-Clients: `authorization_code` (Browser-Anmeldung mit PKCE), `refresh_token` (behält die Gerätebindung) und der Kopplungs-Grant (QR-Code, `urn:studip:app-connect:pairing`). Andere Clients werden abgewiesen. Fehlgeschlagene Kopplungen sind begrenzt (`429 slow_down`) |
| `POST app-connect/revoke` | keine (Client-ID der App) | Widerruf nach RFC 7009 beim Abmelden in der App: `token` (Refresh- oder Access-Token) und `client_id`. Widerruft das Token, das andere Token seines Paars und das gebundene Gerät. Antwortet `200` auch für unbekannte, abgelaufene oder fremde Tokens, damit sich Tokens damit nicht prüfen lassen. Höchstens 300 Anfragen je Adresse in 10 Minuten und 300 je Minute insgesamt (`503`, `Retry-After`). In der Discovery als `oauth2.revocationEndpoint`. [`contract/token-revocation.md`](../../contract/token-revocation.md) |
| `POST app-connect/devices` | App-Token | Registriert das Gerät nach einer Browser-Anmeldung und schickt die Verbindungsmail. `201`; `409 app-connect-already-registered`, wenn die Anmeldung schon gebunden ist; `401`/`403` für andere Tokens |
| `GET app-connect/course-dates/places?filter[ids]=…` | ja | Wo bis zu 50 Termine stattfinden: gebuchte Räume mit Koordinaten (Raum, Gebäude oder Standort) und Raumfinder-Link, sonst die Freitext-Raumangabe. Termine, die die Person nicht sehen darf, fehlen. Funktion `eventPlaces`. [`contract/course-date-places.md`](../../contract/course-date-places.md) |
| `GET app-connect/user-language` | ja | Die Sprache, in der die Person Stud.IP nutzt (Profil, sonst die Vorgabe der Instanz). Funktion `userLanguage`. [`contract/user-language.md`](../../contract/user-language.md) |
| `GET app-connect/user-events?filter[timestamp]=…` | ja | Der Kalender der Person für zwei Wochen wie im Web-Kalender: eigene Termine, Termine der im Kalender angezeigten Veranstaltungen und Ausfälle, jeder Veranstaltungstermin mit der Farbgruppe der Person (`color`, 0–8). Dieselben Ressourcen wie die Kern-Route `users/{id}/events`. Funktion `userEvents`. [`contract/user-events.md`](../../contract/user-events.md) |
| `POST app-connect/web-login` | App-Token oder Passwort | Einmaliger Link für die Anmeldung im Web (oben). `429` nach 10 Links in 5 Minuten. [`contract/web-login.md`](../../contract/web-login.md) |
| `GET`/`DELETE app-connect/me/ical-key` | App-Token | Der Kalender-Schlüssel der Person und die `webcal://`-Adresse; der erste Aufruf legt einen Schlüssel an (höchstens fünf pro Stunde, sonst `429`), `DELETE` setzt ihn zurück und beendet jedes Abo darauf. `401`/`403` für andere Anmeldungen. Funktion `icalKey`. [`contract/ical-key.md`](../../contract/ical-key.md) |

Seiten des Plugins (`plugins.php/appconnect/…`):

| Seite | Anmeldung | Funktion |
|---|---|---|
| `connect` | ab `autor` | „Stud.App Verbindung“: QR-Code, Geräteliste, Geräte entfernen; für root zusätzlich der Zustand des Logos, „Logo neu laden“ und das Hochladen eines Logos |
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

Derselbe Job ruft das Logo neu ab (siehe [Logo](#logo)).

Wird ein Konto gelöscht, verschwinden seine Geräte, Codes und Links sofort. Die
Personendaten von Stud.IP (Benutzerverwaltung → Aktionen → „Anzeige Personendaten“ bzw.
„Export Personendaten als CSV“) enthalten Geräte, Kopplungscodes und Links einer Person;
Codes und Links nur als Hash.
Ereignisprotokoll-Einträge zu Web-Anmeldungen und zum Kalender-Schlüssel löscht der Kern
nach 90 Tagen; die übrigen Einträge des Plugins folgen euren Einstellungen des
Ereignisprotokolls.

| Daten | Ort | Form |
|---|---|---|
| Kopplungscodes | `app_connect_pairing_codes` | SHA-256-Hash, Person, Geräte-ID, Zeitpunkte |
| Geräte | `app_connect_devices` | in der App gewählter Gerätename, Plattform, Art der Verbindung, IDs der OAuth2-Tokens (nicht die Tokens), Zeitpunkte |
| Links für die Anmeldung im Web | `app_connect_web_login_tickets` | SHA-256-Hash, Person, zu öffnende Seite, Verweis auf Token und Gerät, Zeitpunkte |
| Kalender-Schlüssel | Konfiguration der Person (Kern, `IcalExport`) | wie vom Kern verwaltet; das Plugin legt ihn an oder löscht ihn, ein Zähler der neuen Schlüssel liegt eine Stunde im Cache |
| Logo | `app_connect_logo` | eine Zeile: Adresse oder Kennung `upload`, Bild (PNG, JPEG oder WebP, höchstens 512 KB), SHA-256, Zeitpunkte von Abruf oder Hochladen und letztem Versuch, Grund einer Ablehnung; keine Personendaten. Gelöscht, wenn `APP_CONNECT_LOGO_URL` geleert und kein Logo hochgeladen ist, wenn root das hochgeladene Logo entfernt oder wenn das Plugin deinstalliert wird |
| Einstellungen | `config` (Sektion „Stud.App“), `app_connect_settings` | eure Einstellungen, Client-ID, gespeicherte Adresse, zufälliger Schlüssel für die Zähler der Grenzen |
| Fehlversuche beim Koppeln | Stud.IP-Cache | Zähler je Adresse, 10 Minuten |
| Widerrufe von Tokens | Stud.IP-Cache | Zähler je Adresse, 10 Minuten |

Die Zähler im Cache enthalten keine IP-Adressen. Statt der Adresse steht dort ein HMAC-SHA256
mit dem Schlüssel der Installation, gekürzt auf 128 Bit; ohne diesen Schlüssel lässt sich
die Adresse auch durch Ausprobieren aller IPv4-Adressen nicht zurückgewinnen. Der Schlüssel
entsteht bei der ersten Anfrage und liegt nur in `app_connect_settings`.

## Ereignisprotokoll

Ein- und Ausschalten, Anlegen oder Wiederverwenden des Clients, geänderte Einstellungen,
Anmeldungen im Web (angefordert, angemeldet, abgelehnt) sowie Anlegen und Zurücksetzen des
Kalender-Schlüssels stehen im Ereignisprotokoll von Stud.IP (Aktionen `APPCONNECT_*`) – mit
der Person, nie mit Link oder Schlüssel.

## Datenschutz-Textbaustein

Die Nutzung der App ist eine Verarbeitung **eurer Hochschule**, nicht des Herausgebers der
App. Ergänzt eure Datenschutzerklärung; ersetzt die Platzhalter, streicht die Absätze in
eckigen Klammern, deren Funktion bei euch aus ist, und lasst den Text prüfen.
`{{SUPPORT-ADRESSE}}` ist der Wert von `APP_CONNECT_SUPPORT_EMAIL`; ist er leer, gehen
Meldungen an support@studapp2.de, und der Satz lautet dann nur „Sie geht an
support@studapp2.de (Projekt Stud.App).“

```markdown
### Nutzung der App Stud.App

Sie können Stud.IP auch über die App Stud.App für iOS und Android nutzen. Ihre Inhalte
(Veranstaltungen, Dateien, Nachrichten usw.) ruft die App nur von unserer Stud.IP-Instanz ab.
Die Anmeldung erfolgt über unsere Anmeldeseite im Browser Ihres Geräts oder über einen
QR-Code aus Ihrem Stud.IP-Profil; die App erhält kein Passwort, sondern ein Zugriffstoken,
das auf Ihrem Gerät gespeichert wird. Melden Sie sich in der App ab, widerruft die App das
Token bei uns. Zu jedem verbundenen Gerät speichern wir Gerätename, Plattform, Zeitpunkt der
Verbindung und der letzten Nutzung; Sie sehen und entfernen Ihre Geräte im Profil unter
„Stud.App Verbindung“ und erhalten nach jeder Verbindung eine E-Mail. Die in der App
angezeigten Inhalte werden auf Ihrem Gerät zwischengespeichert. Der Herausgeber der App
erhält nur Nachrichten, die Sie ihm selbst schicken (siehe „Inhalte melden“).

Für die Einrichtung ruft die App ohne Anmeldung öffentliche Angaben unserer Instanz ab
(Name, Adressen, Farben, Logo, Links, verfügbare Funktionen). Dabei verarbeiten wir wie bei jedem
Seitenaufruf Ihre IP-Adresse in den Server-Protokollen ({{SPEICHERDAUER-SERVERLOG}}).

Links der App (z. B. zur Bibliothek oder zur Website der Hochschule) öffnet die App in Ihrem
Browser; für diese Seiten gelten die Datenschutzerklärungen ihrer Anbieter.

[Wenn die Anmeldung im Web eingeschaltet ist:] Öffnen Sie aus der App eine Seite unserer
Stud.IP-Weboberfläche, meldet ein einmaliger Link (60 Sekunden gültig) Sie dort im Browser
an, nachdem Sie bestätigt haben. Das Anfordern und Einlösen solcher Links sowie abgelehnte
Einlösungen halten wir mit Ihrer Kennung im Ereignisprotokoll von Stud.IP fest; diese Einträge löschen wir nach
90 Tagen.

[Wenn eine Mensa über OpenMensa eingerichtet ist:] Den Speiseplan der Mensa ruft die App
direkt bei OpenMensa (openmensa.org) ab. Dabei erhält OpenMensa Ihre IP-Adresse und die
Kennung der gewählten Mensa, aber keine Angaben zu Ihrer Person oder Ihrem Stud.IP-Konto.

[Wenn der Kalender-Schlüssel eingeschaltet ist:] Richten Sie in der App ein Kalender-Abo
ein, legt Stud.IP dafür einen persönlichen Schlüssel an. Ihre Kalender-App ruft Ihre Termine
damit unmittelbar bei uns ab, auch wenn Sie sich in der App abmelden; dabei verarbeiten wir
Ihre IP-Adresse in den Server-Protokollen. Sie können den Schlüssel in der App zurücksetzen,
womit jedes Abo darauf endet. Das Anlegen und Zurücksetzen halten wir im Ereignisprotokoll
fest und löschen diese Einträge nach 90 Tagen.

Inhalte melden: Melden Sie in der App einen Inhalt (z. B. eine Nachricht oder einen
Blubber-Beitrag), bereitet die App eine E-Mail in Ihrem Mail-Programm vor. Sie geht an
{{SUPPORT-ADRESSE}} oder, falls keine Support-Adresse hinterlegt ist, an support@studapp2.de
(Projekt Stud.App). Die vorbereitete Nachricht enthält nur die Art und die Kennungen des
Inhalts und die Uhrzeit, nicht den Inhalt selbst. Mit dem Senden erhält die empfangende
Stelle wie bei jeder E-Mail Ihre Absenderangaben und alles, was Sie ergänzen (z. B. den
Grund der Meldung). Rückmeldungen zu Barrieren in der App gehen auf demselben Weg an
support@studapp2.de.

[Wenn Nutzungsstatistik eingerichtet:] Wenn Sie in der App der Nutzungsstatistik zustimmen,
sendet die App pseudonyme Nutzungsereignisse (z. B. geöffnete Bereiche, App-Version,
Plattform) an unsere Matomo-Instanz. Rechtsgrundlage ist Ihre Einwilligung (Art. 6 Abs. 1
lit. a DSGVO, § 25 Abs. 1 TDDDG). Sie können sie in der App jederzeit widerrufen.

Rechtsgrundlage der App-Nutzung ist {{RECHTSGRUNDLAGE}}.
```

## Lizenzhinweis

Die Hilfelasche („Hilfe“) von „Stud.App Verbindung“ und „App holen“ erklärt kurz die Seite
und nennt am Ende, klein abgesetzt, den rechtlichen Hinweis: „app-connect wurde von Bodo
Steffen (© 2026) entwickelt“, die Lizenz (GPL-3.0-or-later mit den Zusatzbedingungen in
`SUPPLEMENTAL-TERMS.txt`), das Recht zur Weitergabe, den Ausschluss der Gewährleistung und
einen Link zur Lizenz. Die Lasche zeigt der Kern erst ab dem Status „autor“; wer „App holen“
ohne Anmeldung öffnet, sieht den Hinweis deshalb am Ende der Seite. Die Zusatzbedingungen
verlangen, dass diese Hinweise die Nennung und den Lizenzhinweis behalten; eine geänderte
Fassung darf sie umformulieren und an anderer Stelle zeigen und ist als geändert zu
kennzeichnen. Das Plugin-Verzeichnis enthält `LICENSE` und `SUPPLEMENTAL-TERMS.txt`.

---

## English

app-connect lets the Stud.App apps (iOS, Android) connect to your Stud.IP instance. The
apps contain no data about any instance; they read everything from this plugin. The plugin
does not change the Stud.IP core: everything the apps need beyond the core's JSON:API comes
from the plugin's own routes.

### Requirements

- Stud.IP 6.0 to 6.3, PHP 8.1 or later. The plugin ZIP needs no Composer
  packages at runtime.
- HTTPS for the whole instance.
- Stud.IP at the root path of its server, such as `https://studip.example.org/`. The connect
  links carry the server only, so the apps cannot reach an instance below a path such as
  `https://example.org/studip/`. If `$ABSOLUTE_URI_STUDIP` contains a path, the app
  connection cannot be switched on.
- `$ABSOLUTE_URI_STUDIP` set in `config/config_local.inc.php`. Without it Stud.IP derives
  its own address from the `Host` header of each request, and a forged header could put a
  foreign address into generated links. The plugin records the address when you switch the
  app connection on and uses it for the link in the connection mail; the core and the
  discovery use the request's address.
- Behind a reverse proxy or load balancer, `REMOTE_ADDR` must be the real client address
  (Apache `mod_remoteip`, nginx `real_ip`, each trusting only the addresses of your
  proxies). The plugin reads `REMOTE_ADDR` as the core does; Stud.IP does not evaluate
  `X-Forwarded-For`, and neither does the plugin, because a client can set that header
  itself. Pairing counts failed attempts per address, the revocation endpoint counts
  requests per address. If every request comes from the proxy's address, everyone shares
  one counter: a few wrong codes then block pairing for everyone, and after 300 sign-outs in
  10 minutes the server revokes no further tokens (the apps still sign out; the token then
  stays valid until it expires).
- The Stud.IP cron worker must run: the plugin's cleanup job deletes expired data.

### Switching the app connection on

1. Download the plugin ZIP `app-connect-<version>.zip` from the
   [Releases](https://github.com/sys-cr/app-connect/releases/latest), then, as `root`, upload and activate it under Admin →
   System → Plugins. If you build from source, `make dist` produces the same ZIP in
   `dist/`. The plugin's migrations run on installation and on every update
   (`cli/studip plugin:migrate app-connect`).
2. As `root`, open Admin → System → Konfiguration and the section **Stud.App**, and set
   `APP_CONNECT_ENABLED` to on.
   - The plugin creates one public OAuth2 client (PKCE, no secret) for all app
     installations, or reuses the one it created before.
   - Please do not edit this client in the core OAuth2 administration; the plugin manages
     it.
   - The plugin also allows the role "Nobody" to load it, because the discovery, the logo,
     the token and the revocation endpoint are called without a session.
3. Optional, in the same section:

   | Entry | Meaning |
   |---|---|
   | `APP_CONNECT_PAIRING_ENABLED` | connecting by QR code from the profile (off by default: switch it on once the instance is configured) |
   | `APP_CONNECT_PROFILE_ENTRY` | the entry "Stud.App connection" under Profile → Settings (on by default) |
   | `APP_CONNECT_INSTANCE_NAME`, `APP_CONNECT_INSTANCE_SHORT_NAME` | name and short name shown in the app |
   | `APP_CONNECT_PRIVACY_POLICY_URL` | address of your privacy policy |
   | `APP_CONNECT_STORE_APP_STORE`, `APP_CONNECT_STORE_GOOGLE_PLAY` | store links on the page "Get the app" |
   | `APP_CONNECT_FEATURE_COURSE_CATALOGUE`, `…_BLUBBER`, `…_MENSA` | additional features of the app |
   | `APP_CONNECT_ROOM_FINDER_PROPERTY` | name of the room property that holds a link to your room finder (e.g. `Lageplan`); the apps show it next to the room of a course date (https only) |
   | `APP_CONNECT_SUPPORT_EMAIL` | support address of your institution; the apps offer it for help and for reporting content (barriers in the app go to the Stud.App support). **Please set it:** without an address, content reports go to support@studapp2.de, the support of the Stud.App project, and not to your university. While it is missing, the configuration after saving and "Stud.App connection" for `root` show a notice |
   | `APP_CONNECT_LOGO_URL` | address of the logo (https); the plugin fetches the picture and serves it to the apps from this server, see [Logo](#logo-1) |
   | `APP_CONNECT_ACCENT_COLOR` | accent colour (`#RRGGBB`); the apps derive a contrast-safe colour scheme from it |
   | `APP_CONNECT_LINK_HOMEPAGE`, `…_CAMPUS_MANAGEMENT`, `…_LIBRARY`, `…_IT_HELP` | links shown in the app (https) |
   | `APP_CONNECT_MENSA_OPENMENSA_IDS`, `APP_CONNECT_MENSA_URL` | mensa source: OpenMensa ids (comma separated, the first is the default) or a web menu link; needed for `APP_CONNECT_FEATURE_MENSA` |
   | `APP_CONNECT_FEATURE_MESSAGES`, `APP_CONNECT_FEATURE_EVENT_PLACES` | messages and places of course dates in the app (on by default) |
   | `APP_CONNECT_FEATURE_COURSEWARE` | reading Courseware in the app (on by default; only while the core module Courseware is active) |
   | `APP_CONNECT_FEATURE_WEB_LOGIN` | web pages opened from the app without signing in again (on by default, see [Web login from the app](#web-login-from-the-app)) |
   | `APP_CONNECT_FEATURE_ICAL_KEY` | subscribing to the calendar from the app (on by default, see [Calendar key](#calendar-key)) |
   | `APP_CONNECT_FEATURE_USER_EVENTS` | the person's calendar in one call with the course colours (on by default); when off, the app builds it from the core routes |

   Addresses must start with `https://`, the support address must be an e-mail address,
   the colour `#RRGGBB` and the OpenMensa ids numbers; the core editor accepts anything, so
   the plugin shows a warning after saving and does not use other values. The address of
   your Stud.IP needs no entry: the plugin takes it from the instance.

   The `APP_CONNECT_FEATURE_*` switches control what the app offers; they are not
   permissions. The app gets the OAuth2 scope `api` and reaches the JSON:API as far as the
   core grants it to the person, even with a switch off. Only the plugin's own routes are
   blocked on the server: web login, calendar key, the person's calendar and places of
   course dates. To really forbid an access, for example to messages or Blubber, use the
   core's permissions, modules and settings.

Switching off revokes the client: signed-in apps lose access immediately. Switching on
again reuses the same client. The same happens when you disable or uninstall the plugin in
the plugin administration: the client is revoked. Enabling it again while
`APP_CONNECT_ENABLED` is on lifts the revocation.

### Logo

The apps load the logo only from your Stud.IP instance's own server: a picture from another
server would tell that server when someone opens the app. The plugin therefore fetches the
address in `APP_CONNECT_LOGO_URL` itself, checks the picture, keeps a copy and serves it at
`jsonapi.php/v1/app-connect/logo`. The discovery names that address as `branding.logoUrl`,
with a version (`?v=…`) that changes with every new picture. This also applies when the logo
already lives on the Stud.IP server.

- The plugin fetches the logo right after the address is saved and then daily in the cronjob
  "app-connect: cleanup". For root, "Stud.App connection" shows the stored logo, when it was
  fetched or why it was refused, and the button "Logo neu laden" (fetch the logo again).
- PNG, JPEG and WebP up to 512 KB with 32 to 2048 pixels per side are accepted. The plugin
  recognises the type by the bytes, not by the `Content-Type`. SVG is refused: the apps show
  raster pictures only, and an SVG can contain scripts.
- Only `https://` without credentials, 5 seconds per request, at most three redirects, each
  to `https://` again. Addresses whose server points into an internal network (loopback,
  private networks, link-local, reserved ranges) are not fetched; your instance's own server
  is the exception. With `HTTP_PROXY` set, the fetch goes through the proxy like the core's
  own requests (except for servers in `HTTP_PROXY_IGNORE`).
- If a later fetch of the same address fails, the plugin keeps serving the last good picture
  and shows the error. A new address replaces the old picture; if it cannot be fetched, the
  apps get no logo until it can.
- Without a valid picture, `branding.logoUrl` is left out of the discovery and the apps show
  a generic symbol. Emptying `APP_CONNECT_LOGO_URL` deletes the copy.
- Instead of an address, root can upload a picture on "Stud.App connection". It passes the
  same checks (PNG, JPEG or WebP, no SVG) and takes precedence: while it is there, the plugin
  neither fetches nor serves the address. "Hochgeladenes Logo entfernen" (remove uploaded
  logo) switches back to the address and fetches it at once.

### Connecting devices

People with at least `autor` status find **Stud.App connection** under Profile → Settings,
right above "Drittanwendungen". There they

- create a QR code with a one-time pairing code (valid five minutes, the first scan wins;
  the database keeps only its hash),
- see every connected device – by QR code or by browser sign-in – and remove it; removing
  ends the device's access at once, including tokens the device renewed later, and the
  device can be connected again afterwards,
- end app sign-ins that are not assigned to a device.

Once connected, the app is also listed on "Drittanwendungen" (by the core). Devices are
managed and removed on "Stud.App connection"; revoking the app on "Drittanwendungen" ends
the device as well. When someone signs out in the app, the app revokes its tokens at the
plugin's revocation endpoint; the device then counts as removed.

Every new connection sends a mail to the account with the device name, the platform, the
time and a link to "Stud.App connection". In the configuration section "Stud.App" you can
switch off the entry "Stud.App connection" or only the QR code (the apps then connect
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
password. The key lives where the core keeps it, and the core's export serves the calendar
as before. New keys, however, are made by the plugin: 32 lower-case letters and digits from a
cryptographic random generator instead of the eight characters from `rand()` that the core
hands out on the calendar page. A person gets at most five new keys an hour (`429`). An
existing key stays until the person resets it, so existing subscriptions keep working.
Switched off
(`APP_CONNECT_FEATURE_ICAL_KEY`), the way through the calendar page in the browser remains.
Details: [`contract/ical-key.md`](../../contract/ical-key.md).

### API endpoints of the plugin

All routes live below `/jsonapi.php/v1/`. Errors are JSON:API error documents with an
application code (`errors[0].code`); the token and revocation endpoints answer OAuth2
errors (`invalid_grant`, `invalid_client`, …) as RFC 6749 describes. Every route answers
`404 app-connect-disabled` while `APP_CONNECT_ENABLED` is off.

| Route | Sign-in | What it does |
|---|---|---|
| `GET app-connect/discovery` | none | Public instance data the apps start with: name, addresses, OAuth2 client id and endpoints, enabled features, and – if you set them – branding, links, mensa source. Built from a fixed list of fields; never contains secrets. `ETag`/`304`, cacheable for 5 minutes. Schema: [`contract/discovery.schema.json`](../../contract/discovery.schema.json) |
| `GET app-connect/logo` | none | The copy of the logo (see [Logo](#logo-1)) with the checked `Content-Type`, `ETag`/`304`, cacheable for a day; `404 app-connect-no-logo` without a valid copy. Announced in the discovery as `branding.logoUrl` |
| `POST app-connect/token` | none (app client id) | Token endpoint of the app client: `authorization_code` (browser sign-in with PKCE), `refresh_token` (keeps the device binding) and the pairing grant (QR code, `urn:studip:app-connect:pairing`). Other clients are refused. Failed pairing attempts are rate limited (`429 slow_down`) |
| `POST app-connect/revoke` | none (app client id) | Revocation per RFC 7009 when someone signs out in the app: `token` (refresh or access token) and `client_id`. Revokes the token, the other token of its pair and the bound device. Answers `200` for unknown, expired or foreign tokens as well, so it cannot be used to test tokens. At most 300 requests per address in 10 minutes and 300 per minute overall (`503`, `Retry-After`). Announced in the discovery as `oauth2.revocationEndpoint`. [`contract/token-revocation.md`](../../contract/token-revocation.md) |
| `POST app-connect/devices` | app token | Registers the device after a browser sign-in and sends the connection mail. `201`; `409 app-connect-already-registered` when the sign-in is bound already; `401`/`403` for other tokens |
| `GET app-connect/course-dates/places?filter[ids]=…` | yes | Where up to 50 course dates take place: booked rooms with coordinates (room, building or site) and the room finder link, else the free-text room. Dates the person may not see are left out. Feature `eventPlaces`. [`contract/course-date-places.md`](../../contract/course-date-places.md) |
| `GET app-connect/user-language` | yes | The language the person uses Stud.IP in (profile, else the site default). Feature `userLanguage`. [`contract/user-language.md`](../../contract/user-language.md) |
| `GET app-connect/user-events?filter[timestamp]=…` | yes | The person's calendar for two weeks as the web calendar shows it: personal entries, dates of courses shown in the calendar and cancelled dates, each course date with the person's colour group of the course (`color`, 0–8). Same resource shapes as the core route `users/{id}/events`. Feature `userEvents`. [`contract/user-events.md`](../../contract/user-events.md) |
| `POST app-connect/web-login` | app token or password | One-time link for the web login (above). `429` after 10 links in 5 minutes. [`contract/web-login.md`](../../contract/web-login.md) |
| `GET`/`DELETE app-connect/me/ical-key` | app token | The person's calendar key and the `webcal://` address; the first call creates a key (at most five an hour, otherwise `429`), `DELETE` resets it and stops every subscription built on it. `401`/`403` for other sign-ins. Feature `icalKey`. [`contract/ical-key.md`](../../contract/ical-key.md) |

Pages of the plugin (`plugins.php/appconnect/…`):

| Page | Sign-in | What it does |
|---|---|---|
| `connect` | `autor` and up | "Stud.App connection": QR code, device list, removing devices; for root also the state of the logo, "Logo neu laden" and uploading a logo |
| `get` | none | "Get the app": store links and "Open in the app"; the pairing code stays in the URL fragment |
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

A cronjob "app-connect: cleanup" (German: "app-connect: Aufräumen"; registered and switched
on by the plugin, daily at 03:17; Admin → System → Cronjobs) deletes

- pairing codes a day after they expired,
- web login links a day after they were issued,
- device rows a month after the device's refresh token expired – removed devices as well
  as devices that were not used again.

The same job fetches the logo again (see [Logo](#logo-1)).

When an account is deleted, its devices, codes and web login links are removed at once.
The Stud.IP personal data view and export (user administration → actions → "Anzeige
Personendaten" / "Export Personendaten als CSV") contain a person's devices, pairing
codes and web login links; codes and links as hashes only.
Event log entries about web logins and the calendar key are deleted by the core after 90
days; the other entries of the plugin follow your event log settings.

| Data | Where | Form |
|---|---|---|
| pairing codes | `app_connect_pairing_codes` | SHA-256 hash, person, device id, times |
| devices | `app_connect_devices` | device name chosen in the app, platform, how it was connected, ids of the OAuth2 tokens (not the tokens), times |
| web login links | `app_connect_web_login_tickets` | SHA-256 hash, person, page to open, token/device reference, times |
| calendar key | the person's configuration (core, `IcalExport`) | as the core keeps it; the plugin creates or deletes it, a count of new keys stays in the cache for an hour |
| logo | `app_connect_logo` | one row: address or the marker `upload`, picture (PNG, JPEG or WebP, at most 512 KB), SHA-256, times of the fetch or upload and the last attempt, reason of a refusal; no personal data. Deleted when `APP_CONNECT_LOGO_URL` is emptied and no logo is uploaded, when root removes the uploaded logo, or when the plugin is uninstalled |
| settings | `config` (section "Stud.App"), `app_connect_settings` | your settings, client id, recorded address, random key for the limit counters |
| failed pairing attempts | Stud.IP cache | counter per address, 10 minutes |
| token revocations | Stud.IP cache | counter per address, 10 minutes |

The counters in the cache hold no IP addresses. In place of the address they hold an
HMAC-SHA256 with the installation's key, shortened to 128 bits; without that key the address
cannot be recovered, not even by trying every IPv4 address. The key is created on the first
request and lives only in `app_connect_settings`.

### Event log

Switching on and off, creating or reusing the client, changing settings, web logins
(requested, signed in, refused) and creating or resetting the calendar key are recorded in
the Stud.IP event log (actions `APPCONNECT_*`) – with the person, never the link or the
key.

### Privacy paragraph for your policy

Using the app is processing by **your university**, not by the app's publisher. Add a
paragraph to your privacy policy; replace the placeholders, drop the paragraphs in square
brackets whose feature is off on your instance, and have the text reviewed. The German
section above carries the wording for a German policy; this is the same text in English.
`{{SUPPORT-ADDRESS}}` is the value of `APP_CONNECT_SUPPORT_EMAIL`; if it is empty, reports
go to support@studapp2.de, and the sentence then only reads "It goes to support@studapp2.de
(the Stud.App project)."

```markdown
### Using the Stud.App app

You can also use Stud.IP through the app Stud.App for iOS and Android. The app fetches your
content (courses, files, messages and so on) from our Stud.IP instance only. You sign in on
our sign-in page in your device's browser or with a QR code from your Stud.IP profile; the
app never receives your password but an access token, which is stored on your device. When
you sign out in the app, the app revokes the token with us. For every connected device we
store the device name, the platform, when it was connected and when it was last used; you
see and remove your devices in your profile under "Stud.App Verbindung" and receive an
e-mail after every new connection. Content shown in the app is cached on your device. The
app's publisher receives only messages you send them yourself (see "Reporting content").

To set itself up, the app fetches public information about our instance without signing in
(name, addresses, colours, logo, links, available features). As with every page request, we
process your IP address in the server logs ({{SERVER-LOG-RETENTION}}).

Links in the app (for example to the library or the university website) open in your
browser; the privacy policies of their providers apply to those pages.

[If the web login is switched on:] When you open a page of our Stud.IP web interface from
the app, a one-time link (valid for 60 seconds) signs you in there in the browser after you
confirm. We record requesting and redeeming such links, and refused redemptions, with your
user id in the Stud.IP event log and delete these entries after 90 days.

[If a canteen is set up through OpenMensa:] The app fetches the canteen menu directly from
OpenMensa (openmensa.org). OpenMensa receives your IP address and the id of the chosen
canteen, but nothing about you or your Stud.IP account.

[If the calendar key is switched on:] When you set up a calendar subscription in the app,
Stud.IP creates a personal key for it. Your calendar app uses it to fetch your events
directly from us, even after you sign out of the app; we process your IP address in the
server logs when it does. You can reset the key in the app, which ends every subscription
built on it. We record creating and resetting the key in the event log and delete these
entries after 90 days.

Reporting content: when you report content in the app (for example a message or a Blubber
post), the app prepares an e-mail in your mail program. It goes to {{SUPPORT-ADDRESS}} or,
if no support address is set, to support@studapp2.de (the Stud.App project). The prepared
message contains only the kind and the ids of the content and the time, not the content
itself. When you send it, the recipient receives your sender details, as with any e-mail,
and anything you add (for example the reason for the report). Feedback about barriers in the app goes
to support@studapp2.de the same way.

[If usage statistics are set up:] If you agree to usage statistics in the app, the app
sends pseudonymous usage events (for example sections opened, app version, platform) to our
Matomo instance. The legal basis is your consent (Art. 6(1)(a) GDPR, § 25(1) TDDDG). You can
withdraw it in the app at any time.

The legal basis for using the app is {{LEGAL-BASIS}}.
```

### License notice

The help bar ("Hilfe") of "Stud.App connection" and "Get the app" briefly explains the page
and, small and set apart at the end, shows the legal notice: "app-connect was developed by
Bodo Steffen (© 2026)", the license (GPL-3.0-or-later with the additional terms in
`SUPPLEMENTAL-TERMS.txt`), the right to convey, the absence of warranty and a link to the
license. The core shows the help bar from the status "autor" on, so visitors who open "Get
the app" without signing in see the notice at the end of the page. The additional terms
require these notices to keep the attribution and the license notice; a modified version may
reword them, show them elsewhere and must be marked as modified. The plugin directory
contains `LICENSE` and `SUPPLEMENTAL-TERMS.txt`.
