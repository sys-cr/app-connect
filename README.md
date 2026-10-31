# app-connect

**Deutsch** · [English](#english)

Ein Stud.IP-Plugin, das die Stud.App für iOS und Android mit einer Stud.IP-Instanz
verbindet. Die Apps enthalten keine Instanzdaten: Adresse, OAuth2-Client und verfügbare
Funktionen erfahren sie über dieses Plugin.

- **Discovery** – `GET /jsonapi.php/v1/app-connect/discovery` liefert die öffentlichen
  Instanzdaten, die die Apps brauchen, aus einer festen Feld-Allowlist.
- **Ein OAuth2-Client** – das Plugin legt einen öffentlichen PKCE-Client für alle
  App-Installationen der Instanz an und verwaltet ihn.
- **Verbinden aus dem Profil** – angemeldete Personen zeigen einen QR-Code mit
  kurzlebigem Kopplungscode; die App scannt ihn und ist verbunden. Jedes verbundene Gerät
  steht in einer Liste und lässt sich einzeln entfernen.
- **Was der Kern-API fehlt** – der Kalender der Person mit Kursterminen, Ausfällen und
  Kursfarben, der Kalender-Schlüssel zum Abonnieren, die Orte von Terminen, die
  Profilsprache und eine einmalige Web-Anmeldung aus der App – als Plugin-Routen, ohne
  Änderung am Stud.IP-Kern.

Unterstützt: Stud.IP 6.0 und neuere 6.x-Versionen. Für Betreiber:
[`docs/admin/README.md`](docs/admin/README.md) mit Einrichtung, allen API-Endpunkten,
gespeicherten Daten und Aufbewahrung. Für Studierende, Lehrende und Mitarbeitende:
[`docs/user/README.md`](docs/user/README.md).

## Aufbau des Repositorys

| Pfad | Inhalt | Lizenz |
|---|---|---|
| `plugin/` | das Stud.IP-Plugin (PHP) | GPL-3.0-or-later |
| `contract/` | JSON-Schema der Discovery, Regeln für Verbindungslinks und Routen, gemeinsame Test-Vektoren für Plugin und Apps | GPL-3.0-or-later |
| `docs/` | Dokumentation für Nutzende (`docs/user`), Betreiber (`docs/admin`) und Entwicklung (`docs/dev`) | GPL-3.0-or-later |

## Entwicklung

```bash
make lint            # php -l über alle Plugin-Dateien
make test-plugin     # PHPUnit (plugin/tests)
make test-contract   # prüft die Test-Vektoren des Vertrags gegen das Schema
```

Installation in ein lokales Stud.IP: [`docs/dev/README.md`](docs/dev/README.md).

## Lizenz

GNU GPL-3.0-or-later **mit Zusatzbedingungen nach Abschnitt 7** – siehe
[`LICENSES/GPL-3.0-or-later.txt`](LICENSES/GPL-3.0-or-later.txt) und
[`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt). Die Zusatzbedingungen gelten nur für
dieses Plugin, nicht für das zugrunde liegende Stud.IP-Kernsystem.

Die Zusatzbedingungen verlangen, dass die grafische Oberfläche sichtbar Autor,
Quellcode-Repository und Lizenz nennt. app-connect zeigt sie in der Hilfelasche der
Seiten, die Nutzende sehen: „Stud.App Verbindung“ im Profil und „App holen“. Das
Plugin-Verzeichnis enthält eigene Kopien beider Texte (`plugin/LICENSE`,
`plugin/SUPPLEMENTAL-TERMS.txt`), damit jede installierte Kopie sie mitbringt.

Beiträge: [`CONTRIBUTING.md`](CONTRIBUTING.md). Sicherheitsmeldungen:
[`SECURITY.md`](SECURITY.md).

## Autor

Entwickelt von **Bodo Steffen** – Quellcode: <https://github.com/sys-cr/app-connect>

---

## English

A Stud.IP plugin that connects the Stud.App mobile apps (iOS, Android) to a Stud.IP
instance. Nothing about an instance is compiled into the apps: they learn the address,
the OAuth2 client and the available features from this plugin.

- **Discovery** – `GET /jsonapi.php/v1/app-connect/discovery` returns the public instance
  data the apps need, from a fixed field allowlist.
- **One OAuth2 client** – the plugin creates and manages one public PKCE client for all
  app installations of the instance.
- **Connect from the profile** – signed-in users show a QR code with a short-lived pairing
  code; the app scans it and is connected. Every connected device is listed and can be
  removed individually.
- **Data the core API lacks** – the person's calendar with course dates, cancellations
  and course colours, the calendar key for subscribing, the places of course dates, the
  profile language, and a one-time web login from the app – as plugin routes, without
  changes to the Stud.IP core.

Supported: Stud.IP 6.0 and later 6.x releases. Operators: see
[`docs/admin/README.md`](docs/admin/README.md) for setup, all API endpoints, stored data
and retention. Students, teachers and staff: see [`docs/user/README.md`](docs/user/README.md).

### Repository layout

| Path | Content | License |
|---|---|---|
| `plugin/` | the Stud.IP plugin (PHP) | GPL-3.0-or-later |
| `contract/` | JSON schema of the discovery, rules for connect links and routes, shared test vectors for plugin and apps | GPL-3.0-or-later |
| `docs/` | user (`docs/user`), operator (`docs/admin`) and developer (`docs/dev`) documentation | GPL-3.0-or-later |

### Development

```bash
make lint            # php -l over all plugin files
make test-plugin     # PHPUnit (plugin/tests)
make test-contract   # validates the contract test vectors against the schema
```

Installing into a local Stud.IP: [`docs/dev/README.md`](docs/dev/README.md).

### License

GNU GPL-3.0-or-later **with additional terms under section 7** – see
[`LICENSES/GPL-3.0-or-later.txt`](LICENSES/GPL-3.0-or-later.txt) and
[`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt). The additional terms apply to this
plugin only, not to the Stud.IP core system it runs on.

The additional terms require the graphical interface to visibly name the author, the
source-code repository and the license. app-connect shows them in the help bar ("Hilfe")
of the pages people use: "Stud.App Verbindung" in the profile and "App holen". The plugin
directory carries its own copies of both texts (`plugin/LICENSE`,
`plugin/SUPPLEMENTAL-TERMS.txt`), so every installed copy includes them.

Contributions: see [`CONTRIBUTING.md`](CONTRIBUTING.md). Security reports: see
[`SECURITY.md`](SECURITY.md).

### Author

Developed by **Bodo Steffen** – source code: <https://github.com/sys-cr/app-connect>
