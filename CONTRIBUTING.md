# Beitragen

**Deutsch** · [English](#english)

Danke für dein Interesse an app-connect.

## Verhaltensregeln

Das Projekt folgt dem
[Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/)
2.1: respektvoll, konstruktiv, inklusiv.

## Ablauf

1. Lege ein Issue an, das die Änderung beschreibt.
2. Erstelle einen Branch von `main`: `feature/<kurzname>` oder `fix/<kurzname>`.
3. Tests und Dokumentation gehören in denselben Pull Request. Eine Änderung an
   `contract/` kommt zusammen mit den passenden Tests des Plugins und beider Apps.
4. `make lint` und `make test` müssen grün sein, jede neue Datei trägt ihren SPDX-Kopf,
   jeder Commit ist signiert.
5. Bitte um ein Review. Vor dem Merge ist mindestens eine Freigabe nötig.

## Commit-Nachrichten

[Conventional Commits](https://www.conventionalcommits.org/):
`<typ>(<bereich>): <kurze Beschreibung>` mit den Typen `feat`, `fix`, `docs`,
`refactor`, `test`, `chore`, `perf`; Bereiche wie `plugin`, `discovery`, `oauth2`,
`contract`, `docs`.

## Developer Certificate of Origin

Jeder Commit wird signiert (`git commit -s`). Mit der Signatur bestätigst du das
[Developer Certificate of Origin 1.1](https://developercertificate.org). Es gibt keine
Contributor License Agreement (CLA).

## Code-Stil

- PHP: Stud.IP-Code-Stil auf Basis von PSR-12, `declare(strict_types=1)` in jeder Datei,
  typisierte Parameter, Rückgaben und Eigenschaften.
- Nur öffentliche APIs des Stud.IP-Kerns, mit den engen, dokumentierten Ausnahmen für den
  Token-Endpunkt und die Gerätebindung.
- SQL nur über SimpleORMap oder Prepared Statements; Ausgaben mit `htmlReady()`
  maskieren; Formulare mit dem CSRF-Schutz von Stud.IP.
- Keine personenbezogenen Daten in Protokollen.
- Jede Quelldatei beginnt mit `SPDX-License-Identifier: GPL-3.0-or-later`, gefolgt von der
  Zeile `SPDX-FileCopyrightText` und dem Verweis auf `SUPPLEMENTAL-TERMS.txt`.
- Kommentare sind englisch und beschreiben den Code, wie er ist.
- Dokumentation ist auf Deutsch, mit einer englischen Fassung im selben Dokument.

## Lizenz der Beiträge

Beiträge werden unter GPL-3.0-or-later mit den Zusatzbedingungen nach Abschnitt 7 in
[`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt) angenommen. Beiträge unter
inkompatiblen Lizenzen können nicht angenommen werden. Der Hinweis in der Hilfelasche
(Autor, Quellcode-Repository, Lizenz) muss sichtbar bleiben; ein Beitrag darf ihn
verschieben, aber nicht entfernen.

Mit dem Einreichen stellst du deinen Beitrag zusätzlich unter GPL-2.0-or-later ohne die
Zusatzbedingungen, damit er in das Stud.IP-Kernsystem (GPL-2.0-or-later) übernommen werden
kann. So bleibt die geplante Übernahme der Funktionen von app-connect in den Kern möglich;
das Plugin selbst bleibt unter GPL-3.0-or-later mit Zusatzbedingungen.

## Sicherheit

Schwachstellen bitte vertraulich melden, wie in [`SECURITY.md`](SECURITY.md) beschrieben,
nicht in öffentlichen Issues.

---

## English

Thanks for your interest in app-connect.

### Code of conduct

The project follows the
[Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/)
v2.1: respectful, constructive, inclusive.

### Workflow

1. Open an issue describing the change.
2. Create a branch from `main`: `feature/<short-name>` or `fix/<short-name>`.
3. Add tests and documentation in the same pull request. A change to `contract/` goes
   together with the matching tests of the plugin and of both apps.
4. Make sure `make lint` and `make test` pass, every new file has its SPDX header and
   every commit is signed off.
5. Request a review. At least one approval is required before merge.

### Commit messages

[Conventional Commits](https://www.conventionalcommits.org/):
`<type>(<scope>): <short description>` with the types `feat`, `fix`, `docs`, `refactor`,
`test`, `chore`, `perf`; scopes such as `plugin`, `discovery`, `oauth2`, `contract`,
`docs`.

### Developer Certificate of Origin

Every commit must be signed off (`git commit -s`). By signing off you certify the
[Developer Certificate of Origin 1.1](https://developercertificate.org). There is no
contributor license agreement (CLA).

### Code style

- PHP: Stud.IP code style on the basis of PSR-12, `declare(strict_types=1)` in every file,
  typed parameters, returns and properties.
- Only public Stud.IP core APIs, with the narrow exceptions documented for the token
  endpoint and device binding.
- SQL only through SimpleORMap or prepared statements; output escaped with `htmlReady()`;
  forms with Stud.IP's CSRF protection.
- No personal data in logs.
- Every source file starts with `SPDX-License-Identifier: GPL-3.0-or-later`, followed by
  the `SPDX-FileCopyrightText` line and the pointer to `SUPPLEMENTAL-TERMS.txt`.
- Comments are in English and describe the code as it is.
- Documentation is in German, with an English version in the same document.

### License of contributions

Contributions are accepted under GPL-3.0-or-later with the additional terms under section
7 in [`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt). Contributions under incompatible
licenses cannot be accepted. The attribution notice in the help bar (author, source
repository, license) must stay visible; a contribution may move it but not remove it.

By submitting a contribution you also license it under GPL-2.0-or-later, without the
additional terms, for inclusion in the Stud.IP core system (licensed GPL-2.0-or-later).
This keeps the planned move of app-connect's functions into the core open; the plugin
itself stays under GPL-3.0-or-later with the additional terms.

### Security

Please report vulnerabilities privately as described in [`SECURITY.md`](SECURITY.md), not
in public issues.
