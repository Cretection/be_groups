# Backend Group Kinds (`be_groups`)

[![CI](https://github.com/Cretection/be_groups/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Cretection/be_groups/actions/workflows/ci.yml)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14.3_LTS-orange.svg)](https://get.typo3.org/version/14)
[![Latest Stable Version](https://poser.pugx.org/cretection/be-groups/v)](https://packagist.org/packages/cretection/be-groups)
[![TER](https://img.shields.io/badge/TER-be__groups-orange.svg)](https://extensions.typo3.org/extension/be_groups)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.txt)

🇬🇧 [English](#english) · 🇩🇪 [Deutsch](#deutsch)

---

## English

Backend Group Kinds turns TYPO3 backend user groups into **kinds**: small
building blocks with exactly one purpose and **roles** that are composed of
them. Backend users only get roles.

The official TYPO3 documentation recommends exactly this structure – and
states that "TYPO3 currently lacks the feature to categorize backend user
groups by context or purpose". This extension closes the gap: real group
kinds with a guided user interface instead of naming conventions.

> Originally created by **Michael Klapper** (2012). See
> [Credits](#credits).

### Features

- **11 kinds** aligned with the official TYPO3 guideline: role, access rights
  (ACL), page permission group, page tree entry points, file mounts, file
  operations, category mounts, languages, TSconfig, workspace and classic.
- **One form per kind** – every group only shows the fields of its kind.
- **Roles** are composed in the core field `subgroup`, with the building
  blocks grouped by kind.
- **Backend users only get roles** (configurable during migration).
- **What is not visible does not apply:** permission fields that do not
  belong to a kind are cleared on every save – in the backend, via the
  DataHandler API and by import or sync tools. Stored assignments are never
  dropped, other data on the group is never touched.
- **Core native:** only one additional field (`tx_begroups_kind`). TYPO3
  resolves permissions as usual.
- **Overview module** "Roles & Building Blocks" (Administration): which role
  consists of which building blocks, who has which role, where a building
  block is used – sortable, filterable and with markers for inconsistencies.
- **Assistants** for existing installations: `begroups:classify` proposes a
  kind for every classic group, `begroups:split` splits mixed groups into
  building blocks and a role that keeps the uid – verified and rolled back
  if any effective permission would change.
- **Consistency check** `begroups:audit` with exit codes and JSON output for
  deployments and monitoring, extensible through a PSR-14 event.
- **Upgrade wizard** for data of be_groups 0.0.x and the AOE version 1.x –
  without changing any effective permission.

### Requirements

- TYPO3 14.3 LTS
- PHP 8.2 – 8.5

### Installation

```bash
composer require cretection/be-groups
vendor/bin/typo3 extension:setup
```

After the installation all existing groups are of kind "Classic" – nothing
changes for your users until you convert them.

### Documentation

The manual is available in English and German on
[docs.typo3.org](https://docs.typo3.org/p/cretection/be-groups/main/en-us/)
and in [`Documentation/`](Documentation/).

### Contributing

Contributions are welcome! Read [CONTRIBUTING.md](CONTRIBUTING.md) and the
[coding guidelines](CODING_GUIDELINES.md). Report security issues privately,
see [SECURITY.md](SECURITY.md).

### Credits

be_groups was invented by **Michael Klapper**, who developed it in 2012 at
morphodo / AOE. His concept of building blocks and META groups is the model
the official TYPO3 documentation recommends today. The relaunch for TYPO3 14
happens with his explicit consent – thank you, Michael!

Thanks also to Christian Zenker, Tomas Norre Mikkelsen, Stefan Rotsch,
Dragan Tomic, Jonathan Klauck and Martin Tepper, who maintained the extension
at AOE. Maintained since 2022 by Jonathan Starck (Cretection). The full story
is in the [Credits & History](Documentation/Credits/Index.rst) chapter.

### License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt).

---

## Deutsch

Backend Group Kinds macht aus TYPO3-Backend-Benutzergruppen
**Gruppentypen**: kleine Bausteine mit genau einer Aufgabe und **Rollen**, die
sich aus ihnen zusammensetzen. Backend-Benutzer bekommen nur Rollen.

Die offizielle TYPO3-Dokumentation empfiehlt genau diese Struktur – und
stellt fest, dass TYPO3 Backend-Benutzergruppen derzeit nicht nach Kontext
oder Zweck einordnen kann. Diese Extension schließt die Lücke: echte
Gruppentypen mit geführter Oberfläche statt Namenskonventionen.

> Ursprünglich entwickelt von **Michael Klapper** (2012). Siehe
> [Credits](#credits-1).

### Funktionen

- **11 Gruppentypen** nach der offiziellen TYPO3-Empfehlung: Rolle,
  Zugriffsrechte (ACL), Seitenrechte-Gruppe, Seitenbaum-Einstiegspunkte,
  Dateifreigaben, Dateioperationen, Kategorie-Freigaben, Sprachen, TSconfig,
  Workspace und Klassisch.
- **Ein Formular pro Typ** – jede Gruppe zeigt nur die Felder ihres Typs.
- **Rollen** werden im Core-Feld `subgroup` zusammengesetzt, die Bausteine
  nach Typ gruppiert.
- **Backend-Benutzer bekommen nur Rollen** (während der Umstellung
  einstellbar).
- **Was nicht sichtbar ist, wirkt nicht:** Rechtefelder, die nicht zu einem
  Typ gehören, werden bei jedem Speichern geleert – im Backend, über die
  DataHandler-API und durch Import- oder Sync-Werkzeuge. Gespeicherte
  Zuweisungen gehen nie verloren, andere Daten der Gruppe bleiben unberührt.
- **Core-nativ:** nur ein zusätzliches Feld (`tx_begroups_kind`). TYPO3
  wertet die Rechte wie gewohnt aus.
- **Übersichtsmodul** „Rollen & Bausteine“ (Administration): welche Rolle aus
  welchen Bausteinen besteht, wer welche Rolle hat, wo ein Baustein verwendet
  wird – sortier- und filterbar, mit Markierung von Inkonsistenzen.
- **Assistenten** für bestehende Installationen: `begroups:classify` schlägt
  für jede klassische Gruppe einen Typ vor, `begroups:split` teilt gemischte
  Gruppen in Bausteine und eine Rolle auf, die die uid behält – geprüft und
  zurückgerollt, falls sich ein wirksames Recht ändern würde.
- **Konsistenzprüfung** `begroups:audit` mit Exit-Codes und JSON-Ausgabe für
  Deployments und Monitoring, erweiterbar über ein PSR-14-Event.
- **Upgrade-Wizard** für Daten von be_groups 0.0.x und der AOE-Version 1.x –
  ohne wirksame Rechte zu verändern.

### Voraussetzungen

- TYPO3 14.3 LTS
- PHP 8.2 – 8.5

### Installation

```bash
composer require cretection/be-groups
vendor/bin/typo3 extension:setup
```

Nach der Installation sind alle bestehenden Gruppen vom Typ „Klassisch“ – für
Ihre Benutzer ändert sich nichts, bis Sie umstellen.

### Dokumentation

Das Handbuch gibt es auf Englisch und Deutsch auf
[docs.typo3.org](https://docs.typo3.org/p/cretection/be-groups/main/de-de/)
und in [`Documentation/Localization.de_DE/`](Documentation/Localization.de_DE/).

### Mitwirken

Beiträge sind willkommen! Siehe [CONTRIBUTING.de.md](CONTRIBUTING.de.md) und
die [Coding-Leitlinien](CODING_GUIDELINES.de.md). Sicherheitslücken bitte
vertraulich melden, siehe [SECURITY.md](SECURITY.md).

### Credits

be_groups wurde von **Michael Klapper** erfunden, der die Extension 2012 bei
morphodo / AOE entwickelt hat. Sein Konzept aus Bausteinen und META-Gruppen
ist das Modell, das die offizielle TYPO3-Dokumentation heute empfiehlt. Der
Relaunch für TYPO3 14 erfolgt mit seiner ausdrücklichen Zustimmung – danke,
Michael!

Danke auch an Christian Zenker, Tomas Norre Mikkelsen, Stefan Rotsch, Dragan
Tomic, Jonathan Klauck und Martin Tepper, die die Extension bei AOE gepflegt
haben. Seit 2022 gepflegt von Jonathan Starck (Cretection). Die ganze
Geschichte steht im Kapitel
[Credits & Geschichte](Documentation/Localization.de_DE/Credits/Index.rst).

### Lizenz

GPL-2.0-or-later, siehe [LICENSE.txt](LICENSE.txt).
