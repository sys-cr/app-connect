# Sicherheitsrichtlinie

**Deutsch** · [English](#english)

## Unterstützte Versionen

Sicherheitskorrekturen gibt es für die jeweils neueste veröffentlichte Plugin-Version.
Ältere Versionen erhalten keine Korrekturen.

## Schwachstellen melden

Sicherheitslücken bitte **vertraulich** melden, nicht in öffentlichen Issues oder Pull
Requests:

- über die **private Schwachstellenmeldung von GitHub**: Reiter *Security* dieses
  Repositorys → *Report a vulnerability* (GitHub Security Advisories),
- falls GitHub nicht nutzbar ist, an <admin@studapp2.de>.

Bitte Plugin- und Stud.IP-Version, Schritte zur Reproduktion und die erwartete Auswirkung
angeben. Wir bestätigen Meldungen möglichst innerhalb von 72 Stunden. Koordinierte
Veröffentlichung ist willkommen; den Zeitpunkt stimmen wir mit dir ab.

## Geltungsbereich

- die Discovery-Route und ihre Feld-Allowlist
- Anlage des OAuth2-Clients, der Token-Endpunkt und die Gerätebindung
- Kopplungscodes und ihre Einlösung
- die Web-Anmeldung aus der App und der Kalender-Schlüssel
- die Profilseite und die öffentliche Landing-Page
- Rechteprüfungen (`root` für die Konfiguration, nur eigene Daten für Nutzende)

Schwachstellen in Stud.IP selbst gehören zum Stud.IP-Projekt, Schwachstellen in den Apps
zu den App-Repositorys. Im Zweifel hier melden; wir leiten die Meldung weiter.

## Danksagung

Wer eine Schwachstelle verantwortungsvoll meldet, wird auf Wunsch in den Release-Notes der
Korrektur genannt.

---

## English

### Supported versions

Security fixes are provided for the latest released plugin version. Older versions do not
receive patches.

### Reporting a vulnerability

Please report security issues **privately**, not in public issues or pull requests:

- Use **GitHub private vulnerability reporting**: *Security* tab of this repository →
  *Report a vulnerability* (GitHub security advisories).
- If you cannot use GitHub, write to <admin@studapp2.de>.

Please include the affected version, the Stud.IP version, steps to reproduce and the
impact you expect. We aim to acknowledge reports within 72 hours. Coordinated
vulnerability disclosure is welcome; we agree on a publication date with you.

### Scope

- the discovery route and its field allowlist
- OAuth2 client provisioning, the token endpoint and the device binding
- pairing codes and their redemption
- the web login from the app and the calendar key
- the profile page and the public landing page
- permission checks (`root` for configuration, own data only for users)

Vulnerabilities in Stud.IP itself belong to the Stud.IP project; vulnerabilities in the
apps belong to the app repositories. If in doubt, report here and we will forward the
report.

### Acknowledgements

Anyone who reports a vulnerability responsibly is credited in the release notes of the
fix, if they wish.
