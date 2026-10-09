# Arbeitsstand be_groups-Relaunch

Stand: **2026-10-09** · Branch **`relaunch`**, wird zu `dev` (CI grün) · Ziel: **1.0.0 für TYPO3 14.3 LTS**

Dieses Dokument ist der Einstiegspunkt, um die Arbeit fortzusetzen. Das Konzept steht in [`RELAUNCH.md`](RELAUNCH.md), die verbindlichen Regeln für den Code in [`CODING_GUIDELINES.de.md`](CODING_GUIDELINES.de.md) bzw. [`CODING_GUIDELINES.md`](CODING_GUIDELINES.md).

---

## 1. Kurzfassung

- **Umgesetzt:**
  - der Kern (M0 und M1): Gruppentypen, Regeln R1–R4,
  - das Übersichtsmodul ohne Bearbeitung (Teil von M2),
  - die Konsistenzprüfung `begroups:audit` mit dem Event `AfterAuditFindingsCollectedEvent` (Teil von M2),
  - die Assistenten `begroups:classify` und `begroups:split` und das Starter-Set über den Core-Befehl (M3),
  - das Event `ModifyKindOfNewGroupEvent` für Import- und Sync-Werkzeuge (Teil von M2),
  - der Typ als Präfix überall, wo TYPO3 Gruppen zeigt („META: Redakteur“), inklusive Core-Modul „Users“ und eigenem Modul,
  - der Release-Workflow für das TER mit Versionsprüfung (`.github/workflows/publish.yml`, ohne `ext_emconf.php`),
  - die Doku auf Deutsch und Englisch, mit Screenshots in hellem und dunklem Theme,
  - die Qualitätsziele aus `RELAUNCH.md` §8.1 als Prüfungen: E2E-Tests mit Playwright und axe in hellem und dunklem Theme (`-s e2e`), zusammengeführte Testabdeckung mit Mindestwert (`-s coverageCheck`) und Mutationstests mit Infection (`-s mutation`).
- **Reviews:**
  - Das erste strenge Review (2026-10-07) fand 15 echte Fehler. Alle sind behoben, jeder mit einem Test, der den alten Fehler nachweislich erkennt.
  - Ein unabhängiges Review der Teile vom 2026-10-08 fand 12 Punkte, darunter einen kritischen: Die Wiederverwendung bestehender Bausteine konnte Benutzern Rechte geben. Alle sind behoben (`c04fa92`, Details in Abschnitt 9).
  - Ein zweites unabhängiges Review dieses Fixes fand keinen Weg mehr, wirksame Rechte ohne fremde Hooks zu ändern, aber 8 weitere Punkte (u. a. Eigentümergruppe neuer Seiten, Hook-Änderungen außerhalb der Gruppe, überlebende Mutanten, kaputte deutsche Tabelle). Alle sind behoben (Abschnitt 9); 11 gezielte Mutanten werden von den Tests erkannt.
  - **Live-Test in DDEV** (2026-10-08, eigenes Projekt `begroups-test`, Abschnitt 5): `setup:begroups:default` + `begroups:split --all` mit den echten Core-Presets. Die wirksamen Rechte eines Redakteurs, über den Core wie beim Login ermittelt, sind vorher und nachher in 16 von 18 Aspekten identisch. Die zwei Unterschiede sind beabsichtigt: zusätzliche Bausteine in der Gruppenliste und `PG_Editor` als Eigentümergruppe neuer Seiten.
  - **Log-Test** (2026-10-08, nach `42071fe`): frische Testdaten, `classify`, `split --all`, `audit` und ein Rundgang durch die Backend-Bereiche (Users-Modul mit Listen, Details und Vergleich, Rechte-Modul, Status, Datensatzliste, Formulare, eigenes Modul). Ergebnis: 0 Fehler im Systemprotokoll, keine neuen Einträge im Datei-Log, keine Konsolenfehler.
  - **Sicherheitsprüfung** (2026-10-08, unabhängig, M4): Ein Nicht-Admin kann über die Extension keine Rechte gewinnen (`be_groups` und `be_users` sind im Core nur für Admins schreibbar). Gefunden und behoben, jeweils mit einem Test, der ohne die Korrektur fehlschlägt:
    - Umstellungen waren unter MySQL/MariaDB nicht atomar, wenn Page-TSconfig Caches leert (`f0ca415`).
    - Vier Wege um R2/R4 für Admins, Importe und Integrationen (`02f687d`).
    - Felder fremder Extensions ohne Typ (`7a59ae1`).
    - Härtungen der Konsolenausgabe und der Template-Overrides (`c1dde94`).
  - **Barrierefreiheit** (2026-10-08, M4): axe-core 4.13 nach WCAG 2.2 AA in hellem und dunklem Theme. Modul, Rollen- und Bausteinformular und die überschriebenen Templates des Users-Moduls haben 0 Verstöße; der einzige Fund (Zielgröße der Benutzer-Links) ist behoben (`9320b38`).
  - **Laufzeit** (2026-10-08, M4): bei 1.000 Gruppen und 5.000 Benutzern `classify` 24 s statt 199 s, `split --all` 100 s statt über 10 Minuten.
- **Testbasis:** Alle Prüfungen sind grün, auf allen Datenbanken, mit PHP 8.2 und 8.5, auf TYPO3 14.3 und 15-dev (Stand je Zeile in Abschnitt 3).
- **Nächster Schritt:** der Rest von M4: Klicktest, Crowdin, Release (Abschnitt 7). Der erste Lauf des Jobs `mutation` auf GitHub ist grün (2026-10-09, [Lauf 37924063344](https://github.com/Cretection/be_groups/actions/runs/37924063344), `dev` auf `dfa1a62`): Covered Code MSI 82 % bei Minimum 80 %, 1.111 Mutanten erkannt, 240 abgedeckte nicht.

---

## 2. Erledigt

| Commit | Inhalt |
|---|---|
| `[DOCS] Add relaunch concept and coding guidelines` | Machbarkeitsstudie, Konzept, Coding-Leitlinien (DE/EN) |
| `[!!!][TASK] Remove the legacy implementation` | alter Code entfernt, Lizenz GPL-2.0-or-later |
| `[TASK] Add quality tooling and continuous integration` | php-cs-fixer, PHPStan max, Rector, PHPUnit, `runTests.sh`, GitHub Actions |
| `[FEATURE] Add group kinds with enforced permission rules` | 11 Typen, TCA pro Typ, DataHandler-Regeln R1–R4 |
| `[FEATURE] Add upgrade wizard for groups of be_groups 0.0.x` | Migration der alten Daten (später entfernt, E14) |
| `[FEATURE] Add read-only module "Roles & Building Blocks"` | Übersichtsmodul (Administration) |
| `[DOCS] Add documentation and project files in English and German` | Handbuch EN/DE, README, CONTRIBUTING, SECURITY, CHANGELOG |
| `[TASK] Harden the tooling and test against the next TYPO3 version` | Job `typo3-next` gegen TYPO3 15-dev, Tooling-Korrekturen aus dem Review |
| `[BUGFIX] Enforce the kind rules without losing data` | Review-Korrekturen: Regeln, Formulare, Typ-Registry |
| `[BUGFIX] Migrate legacy groups without changing effective permissions` | Review-Korrekturen: Upgrade-Wizard |
| `[BUGFIX] Show unknown kinds and rule violations in the overview` | Review-Korrekturen: Übersichtsmodul |
| `[DOCS] Document compatibility, support and the reworked rules` | Support-Regel (gebunden an das TYPO3-EOL), Doku zu allen Änderungen |
| `[DOCS] Add the status document for continuing the relaunch` | dieses Dokument |
| `[FEATURE] Add the consistency check begroups:audit` | Konsistenzprüfung (CLI, Exit-Codes, JSON), Event für eigene Prüfungen |
| `[FEATURE] Add the assistants begroups:classify and begroups:split` | Umstellung klassischer Gruppen ohne Änderung wirksamer Rechte |
| `[TASK] Parse the page of new groups without relying on annotations` | PHPStan-Befund gegen TYPO3 15-dev behoben |
| `[DOCS] Document the starter set based on the default groups of TYPO3` | Starter-Set: `setup:begroups:default` + `begroups:split --all` |
| `[BUGFIX] Keep hidden groups restorable and report database errors` | sichtbare Bausteine für versteckte Gruppen, Datenbankfehler als Ergebnis statt Abbruch |
| `[FEATURE] Let listeners choose the kind of new groups` | Event `ModifyKindOfNewGroupEvent` |
| `[BUGFIX] Verify conversions per user and never share building blocks` | Korrekturen aus dem unabhängigen Review (Abschnitt 9) |
| `[BUGFIX] Keep the owner group of new pages and catch changes of other extensions` | Korrekturen aus dem zweiten Review (Abschnitt 9) |
| `[TASK] Use icons in the style of TYPO3 14 for light and dark themes` | einfarbige Core-Icons für die Typen, eigenes Modul-Icon im Core-Stil, Extension-Icon als Kachel |
| `[!!!][TASK] Remove the migration of earlier versions` | Upgrade-Wizard entfernt (E14) |
| `[BUGFIX] Give groups on a cycle a page group as owner of new pages` | Befund aus dem Massentest mit generierten Daten |
| `[FEATURE] Show the kind as prefix wherever TYPO3 shows a group` | „META: Redakteur“ überall, inklusive Core-Modul „Users“ |
| `[DOCS] Explain roles in roles after splitting and record the prefix decision` | Doku: Rollen in Rollen nach dem Aufteilen, Entscheidung g |
| `[BUGFIX] Log intended field clearing as information, not as error` | Leerungen nach R1 beim Typwechsel als Information, vom Aufrufer selbst geleerte Felder ohne Meldung |
| `[TASK] Give PHPStan the same memory limit locally as in CI` | `check:php:stan` mit `--memory-limit=4G` |
| `[TASK] Show the kind of role members that are no building blocks` | Modul: „kein Baustein“ mit Typ-Präfix und Erklärung, „Verwendet in“ mit Präfix |
| `[TASK] Speed up the assistants for large installations` | Nachprüfung nur der Gruppen-Kombinationen, die die Gruppe erreichen; Benutzer roh vergleichen; Klassifizierung aus dem Abzug; Typen und Felder pro TCA-Schema gepuffert |
| `[BUGFIX] Detect removed groups and new users when verifying conversions` | Rohdatenvergleich über alle Datensätze vorher und nachher |
| `[TASK] Add the release workflow for the TER` | `publish.yml`, `checkReleaseVersion.php`, `setVersion.php`, Release-Schritte in `CONTRIBUTING.md` |
| `[TASK] Name rejected groups with kind and title` | Meldungen wie „META: Advanced Editor [2]“ statt nur der uid |
| `[DOCS] Explain how to update the extension` | Nach Updates Caches leeren und `extension:setup` |
| `[BUGFIX] Keep conversions atomic when page TSconfig flushes caches` | Cache-Befehle (`TCEMAIN.clearCacheCmd`) erst nach dem Commit |
| `[SECURITY] Close ways around the rules for roles and users` | Gespeicherte Listen wie der Core lesen, Platzhalter nur für Gruppen, `TCAdefaults` prüfen, Admin-Prüfung im Konverter |
| `[TASK] Report fields of other extensions that belong to no kind` | Audit `unassigned-fields`; Assistenten stellen solche Gruppen nicht um |
| `[TASK] Harden the console output and the template overrides` | Unsichtbare Steuerzeichen, Vergleichstest Override gegen Core |
| `[TASK] Meet WCAG 2.2 AA in the module "Roles & Building Blocks"` | Abstand der Benutzer-Links, Kommas, Zusammenfassung ohne Plural |
| `[DOCS] Add screenshots of the forms and the module` | 5 Screenshots, hell und dunkel, in beiden Handbüchern |
| `[TASK] Check defaults only for fields of the record type` | `TCAdefaults` nur für Felder, die der Satztyp hat |
| `[DOCS] Record that the TER key and the Packagist package already exist` | Key und Paket gehören `cretection` |
| `[DOCS] Move the editing matrix of the module to version 1.1` | Entscheidung E2 angepasst |
| `[TASK] Merge the coverage of all tests and check the minimum in CI` | Job `coverage`, `-s coverageCheck` (≥ 90 % in `Classes/`) |
| `[TASK] Add end-to-end tests with accessibility checks` | Playwright und axe, Instanz aus `setupE2E.sh`, Job `e2e` |
| `[TASK] Translate through the Crowdin project of TYPO3` | `.crowdin.yml`, Workflow `Crowdin`, E15 und E16 |
| `[FEATURE] Show the TSconfig precedence of roles in the module` | Vorrangfolge bei zwei oder mehr TSconfig-Bausteinen |
| `[TASK] Add mutation tests with Infection` | `-s mutation`, PHAR mit Prüfsumme, Job `mutation` (wöchentlich) |
| `[TASK] Test the gaps in the rules that mutation testing found` | Neue Regeltests, kein Fehler im Code |
| `[TASK] Compare file mounts and file operations of the model with the core` | Abgleich über öffentliche API |

**Stand der Meilensteine** (Details in `RELAUNCH.md` §9):

| Meilenstein | Stand |
|---|---|
| M0 Fundament | ✅ erledigt. Erster CI-Lauf auf GitHub grün (2026-10-08). |
| M1 Kern | ✅ erledigt. Die eigenen PSR-14-Events sind nach M2 verschoben. |
| M2 Übersicht | 🟡 teilweise. Fertig: Modul ohne Bearbeitung, `begroups:audit` mit Event, `ModifyKindOfNewGroupEvent`. Typ als Präfix überall (Entscheidung g). Verworfen: Typ-Filter im Users-Modul (E12). Die Matrix-Bearbeitung kommt mit 1.1 (E2, 2026-10-08). |
| M3 Umstieg | ✅ erledigt: `begroups:classify`, `begroups:split`, Starter-Set über den Core-Befehl (E13). |
| M4 Härtung und Release | 🟡 teilweise. Fertig: Sicherheitsprüfung, Performance-Benchmark, Barrierefreiheit (axe, jetzt als E2E-Test), Screenshots, Release-Workflow, Testabdeckung und Mutationstests, Übersetzungsweg (E15), CI auf GitHub mit Branch-Schutz. Offen: Crowdin-Projekt beantragen, Release (Credits sind abgestimmt, optional liest Michael Klapper sie gegen). |

---

## 3. Qualitätsnachweis (letzter Lauf am 2026-10-08)

| Prüfung | Ergebnis |
|---|---|
| Unit-Tests | 37 Tests grün |
| Functional Tests | 170 Tests grün (2026-10-08, SQLite) |
| CI auf GitHub | Erster Lauf nach dem Push (2026-10-08, Stand `589dc5c`, [Lauf 37818522748](https://github.com/Cretection/be_groups/actions/runs/37818522748)): alle 23 Jobs grün, inklusive `typo3-next`, `e2e` und `coverage`; `mutation` läuft nur wöchentlich und auf Abruf. |
| Lokales CI-Raster | Alle Jobs von `ci.yml` lokal nachgestellt (2026-10-08, Stand `06795e9`): 40 von 40 grün. Statische Prüfungen mit PHP 8.2; Unit-Tests mit PHP 8.2, 8.3, 8.4 und 8.5, jeweils niedrigste und höchste Abhängigkeiten; Functional Tests mit PHP 8.2 (SQLite) und 8.5 auf SQLite, MariaDB 10.11 und 11.8 (mysqli und pdo_mysql), MySQL 8.0 und 8.4 (mysqli und pdo_mysql), PostgreSQL 14 und 18; TYPO3 15-dev (Core `784b432`) mit Unit- und Functional Tests; Doku. Der Folgestand `16e219e` zusätzlich auf SQLite, MariaDB 10.11, MySQL 8.0 und PostgreSQL 14 grün, ebenso der Stand `a4abc2d` (170 Functional Tests, PHP 8.5). Die neuen Jobs `coverage`, `e2e` und `mutation` liefen lokal (Abschnitt 3); `typo3-next` gegen den neuen Stand steht aus und läuft mit dem ersten CI-Lauf. Skript: `run-matrix.sh` im Scratchpad. |
| Massentest | Generierte Installation (150 Gruppen, 403 Benutzer, alle Sonderfälle): `classify` + `split --all`, wirksame Rechte aller Benutzer vorher und nachher über den Core verglichen. Wiederholt am 2026-10-08 mit korrigiertem Dump, der auch deaktivierte Benutzer lädt und nichts schreibt: 44 + 101 Gruppen umgestellt, 0 fehlgeschlagen, 0 Abweichungen. Bei 387 Benutzern ist eine neue Seitenrechte-Gruppe Eigentümerin neuer Seiten (beabsichtigt). Danach meldet das Audit 143 × `role-invalid-member` (Rollen in Rollen), 1 × `role-missing-member` (gelöschte Gruppe aus den Testdaten) und 4 klassische Gruppen zur Entscheidung. Werkzeuge im Testprojekt: `generate-test-data.php`, `dump-permissions.php`. |
| Lasttest | 1.000 Gruppen, 5.000 Benutzer (Seed 11), Endstand 2026-10-08: `classify` 24 s (240 umgestellt), `split --all` 100 s (739 umgestellt), 0 fehlgeschlagen; wirksame Rechte aller 5.007 Benutzer über den Core verglichen: 0 Abweichungen; 0 Fehler im Systemprotokoll. Vorher: `classify` 199 s, `split --all` über 10 Minuten. Werkzeuge im Testprojekt: `benchmark.sh`, Skript `e2e.sh` im Scratchpad (Erzeugen, Dump, Assistenten, Vergleich, Log). |
| Log-Test | Nach Testdaten, Assistenten und Rundgang durch das Backend: 0 Fehler im Systemprotokoll (nur Einträge des DataHandler), keine neuen Einträge im Datei-Log, keine Konsolenfehler (2026-10-08). |
| PHP- und TYPO3-Versionen | PHP 8.2 bis 8.5 mit niedrigsten und höchsten Abhängigkeiten, TYPO3 14.3.7 und 15.0-dev: siehe lokales CI-Raster |
| Testabdeckung | 94,09 % der Zeilen in `Classes/` (1.530 von 1.626), Unit- und Functional-Tests (SQLite) zusammengeführt, PHP 8.5 mit Xdebug (2026-10-08). Ziel laut `CODING_GUIDELINES.md` §13: mindestens 90 %; die CI prüft das im Job `coverage` (`-s coverageMerge`, `-s coverageCheck`). Der Bericht enthält auch `Configuration/` und `ext_localconf.php` (0 %, weil TYPO3 sie vor der Messung lädt); sie bleiben in `<source>`, damit Notices und Deprecations dort die Tests scheitern lassen. Größte Lücken: `BackendGroupRepository` (28 Zeilen), `BackendUserRepository` (13), `GroupConverter` (11). |
| Mutationsproben | 11 gezielte Mutanten in Prüfung und Modell, dazu Gegenproben aller Korrekturen vom 2026-10-08 (u. a. MariaDB mit `clearCacheCmd`, Platzhalter, `TCAdefaults`, gespeicherte Listen, Override-Vergleich): alle von Tests erkannt |
| Mutationstests | Infection 0.35.4 über `Classes/Domain` und `Classes/DataHandling` mit Unit- und Functional-Tests (2026-10-08, Stand `4dd5839`): 1.342 Mutanten, 1.082 getötet, 3 Fehler, 2 Timeouts, 255 überlebt, MSI **81 %** (Ziel 80 %), 1 h 27 min. Danach geschlossen: Lücken in den Regeln (`8be8dab`) und im Abgleich des Modells mit dem Core für Dateifreigaben und Dateioperationen (`a4abc2d`); kein Fehler im Code gefunden. Nachlauf nur für die Regel-Engine (`DataHandling/`, Stand `8be8dab`): 364 Mutanten, 297 getötet, 67 überlebt, MSI **81,6 %** (Ziel 80 % laut `RELAUNCH.md` §8.1); alle gezielten Mutanten (u. a. `trim` des Typs, abgeschaltete klassische Gruppen, Platzhalter späterer Gruppen, Ausdruck für unsichere Werte) werden erkannt. Die Überlebenden im Modell, die beide Seiten des Vorher-nachher-Vergleichs gleich verändern (z. B. `array_values`, `intExplode(…, false)` mit nachgelagertem Filter), können keine Rechteänderung verdecken. |
| Changelog | git-cliff 2.14.2 (`cliff.toml`, `runTests.sh -s changelog`), 2026-10-09 gegen die Historie und an einem simulierten Release getestet: `[FEATURE]` → Added, `[BUGFIX]` → Fixed, `[SECURITY]` → Security, `[!!!]` → Breaking changes, `[TASK]`/`[DOCS]`/Commits ohne Präfix fehlen; `Changelog: changed\|added\|removed\|deprecated\|fixed\|security\|skip` als letzte Zeile im Commit überschreibt. `--prepend` setzt den Eintrag über die bestehenden, Überschriften tragen den Vergleichslink. CI prüft die Konfiguration im Job `static`. |
| E2E-Tests | 32 Tests grün (2026-10-08, `-s e2e`, Playwright 1.63, TYPO3 14.3.7): Modul (Listen, Filter, Sortierung, Inkonsistenzen, Vorrang im TSconfig, Bearbeiten-Links), Formulare aller Bausteintypen und der Rolle, Ablehnung „Rolle in Rolle“ und „Baustein am Benutzer“ samt Sudo-Mode, Präfixe im Core-Modul „Users“; axe (WCAG 2.2 AA) im hellen und im dunklen Theme, eine Prüfung der Textfarbe stellt sicher, dass der dunkle Lauf wirklich dunkel ist. Gegenprobe: ein Bild ohne Alt-Text im Modul lässt beide axe-Tests scheitern. |
| Barrierefreiheit | axe-core 4.13 (WCAG 2.2 AA) per Playwright 1.63 in hellem und dunklem Theme: 0 Verstöße in Modul, Rollen- und Bausteinformular und überschriebenen Users-Templates. Offene „needs review“-Hinweise betreffen das Modulmenü des Core. Bedienung nur über native Links und Buttons, keine eigenen Widgets. |
| Release-Probe | Release 1.0.0 in einer Kopie simuliert: `setVersion.php`, Changelogs, `checkReleaseVersion.php`, annotierter Tag, `git archive`; `tailor create-artefact` validiert das Paket. Kein Upload ins TER. |
| PHPStan | Level max, ohne Baseline, mit Prüfung auf `@internal`: 0 Fehler. Gegen 15-dev (Core `784b432`, 2026-10-08): `Classes/` ohne Befund; 403 Meldungen nur in den Tests (`mixed` aus `get()`, weil das Testing-Framework für v15 die Typisierung geändert hat; vor dem Wechsel auf v15 zu lösen). |
| Weitere Prüfungen | php-cs-fixer, Lizenzköpfe, Rector, Lint, PSR-4, Integrität (Exception-Codes, Testkonventionen), XLIFF, YAML, JSON, `composer normalize`: alle grün |
| Doku | `render-guides` (EN und DE) ohne Warnungen |
| Gegen TYPO3 15 | 0 genutzte APIs, die in v15 deprecated oder intern sind |

---

## 4. Schnellstart: lokal weiterarbeiten

```bash
git checkout dev
composer install                      # installiert die Werkzeuge nach .Build/

composer check:static                 # alle statischen Prüfungen
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml
typo3DatabaseDriver=pdo_sqlite .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
```

**Mit Containern wie in der CI** (Docker muss laufen):

```bash
Build/Scripts/runTests.sh -h                                   # alle Suiten und Optionen
Build/Scripts/runTests.sh -b docker -p 8.5 -s functional -d mariadb
Build/Scripts/runTests.sh -b docker -p 8.5 -s functional -d postgres
Build/Scripts/runTests.sh -b docker -s docs                    # Doku rendern, Warnungen = Fehler
```

**Hinweise:**
- **`-p` passend zur Installation wählen.** `.Build/` wird für die PHP-Version aufgelöst, mit der `composer install` lief. Für eine andere PHP-Version oder für den Test gegen TYPO3 15 eine **Kopie** des Repos verwenden. Sonst wird das lokale `.Build/` überschrieben:
  ```bash
  rsync -a --exclude .Build ./ /tmp/be_groups-next/ && cd /tmp/be_groups-next
  Build/Scripts/runTests.sh -b docker -p 8.5 -s composerUpdateDev
  Build/Scripts/runTests.sh -b docker -p 8.5 -s unit
  Build/Scripts/runTests.sh -b docker -p 8.5 -s functional -d sqlite
  ```
- **Speicher:** Die Functional Tests brauchen mehr als 128 MB; `Build/phpunit/FunctionalTests.xml` setzt deshalb 1 GB.

**Eigene Test-Installation** (optional, unabhängig vom DDEV-Projekt):

```bash
composer create-project "typo3/cms-base-distribution:^14.3" /tmp/t3v14 && cd /tmp/t3v14
TYPO3_DB_DRIVER=sqlite TYPO3_SETUP_ADMIN_USERNAME=admin TYPO3_SETUP_ADMIN_PASSWORD='<Passwort>' \
  TYPO3_SETUP_ADMIN_EMAIL=admin@example.org TYPO3_PROJECT_NAME=test TYPO3_SERVER_TYPE=other \
  vendor/bin/typo3 setup --no-interaction --force
ln -s ~/Developer/GitHub.com/Cretection/be_groups packages/be_groups
composer require "cretection/be-groups:@dev" typo3/cms-workspaces:^14.3
vendor/bin/typo3 extension:setup && vendor/bin/typo3 cache:flush
```

---

## 5. Testen im DDEV-Projekt

**Eigene Testumgebung (seit 2026-10-08):** DDEV-Projekt `begroups-test` in `~/Developer/Local/begroups-test`.
- TYPO3 14.3 mit PHP 8.4 und MariaDB 10.11, erreichbar unter https://begroups-test.ddev.site/typo3. Die Zugangsdaten (admin, editor) stehen in `ADMIN-LOGIN.txt` im Projekt, nicht im Repo.
- Das Repo ist **schreibgeschützt** eingebunden (`.ddev/docker-compose.be_groups.yaml`) und per Composer-Path-Repository verlinkt. Die Umgebung testet also immer den aktuellen Arbeitsstand.
- `check-permissions.php <uid>` im Projekt gibt die wirksamen Rechte eines Benutzers als JSON aus, über den Core wie beim Login. `dump-permissions.php` macht das für alle Benutzer. Beide rollen ihre Schreibzugriffe zurück: Beim Laden eines Benutzers korrigiert der Core sonst `workspace_id` und protokolliert das.
- `generate-test-data.php <Gruppen> <Benutzer> [Seed]` erzeugt eine gewachsene Installation mit allen Sonderfällen und entfernt vorher den letzten Lauf.
- `create-docs-scenario.php` ersetzt erzeugte Daten durch ein kleines, realistisches Szenario: Rollen „Content manager“, „Marketing“ und „Team lead“ (mit einer Rolle als Mitglied), passende Bausteine, eine klassische Gruppe und die Benutzer anna, ben, carla und dan. **Das ist der aktuelle Stand der Testumgebung**; die Screenshots der Doku stammen daraus. Platzhalter in Verknüpfungen dürfen keinen Unterstrich enthalten, weil TYPO3 dort `tabelle_uid` liest.
- `benchmark.sh` misst die Assistenten, `set-color-scheme.php <Benutzer> <auto|light|dark>` setzt das Farbschema eines Benutzers (für Screenshots in beiden Themes; der Admin steht wieder auf „dark“).
- Für Browser-Automatisierung ist die Umgebung unter http://127.0.0.1:64263 erreichbar, Playwright-Container im Netz `ddev-begroups-test_default` unter `http://ddev-begroups-test-web`.
- Starten mit `cd ~/Developer/Local/begroups-test && ddev start`. Die anderen DDEV-Projekte bleiben unberührt; nie `ddev poweroff` oder `ddev stop --all` verwenden, kein `docker system prune` und keinen Neustart von Docker, weil das laufende Testcontainer abbricht.

Das andere DDEV-Projekt gehört zu einem **anderen Projekt**. Deshalb zuerst einen Snapshot anlegen und die Extension nur als Kopie einbinden. Voraussetzung ist TYPO3 14.3 im Composer-Modus.

```bash
ddev snapshot --name vor-be-groups                       # Zurück mit: ddev snapshot restore vor-be-groups
git clone -b dev ~/Developer/GitHub.com/Cretection/be_groups packages/be_groups
ddev composer config repositories.be-groups path "packages/be_groups"
ddev composer require "cretection/be-groups:@dev"
ddev typo3 extension:setup && ddev typo3 cache:flush
```

**Entfernen:**

```bash
ddev composer remove cretection/be-groups && rm -rf packages/be_groups
```

Oder einfach `ddev snapshot restore vor-be-groups`.

**Was getestet werden sollte:**
1. Nach der Installation sind alle Gruppen vom Typ „Klassisch“. An den Rechten ändert sich nichts.
2. Bausteine anlegen (z. B. *Seitenbaum-Freigabe* mit einer Seite und *Zugriffsrechte* mit Modulen), dann eine *Rolle* mit diesen Bausteinen. Die Auswahl ist eine zweispaltige Liste, gruppiert nach Typ.
3. Die Rolle einem Benutzer zuweisen und mit „Benutzer wechseln“ prüfen, was er sieht.
4. Einem Benutzer direkt einen Baustein zuweisen. Das wird beim Speichern abgelehnt, mit Hinweis. Bestehende Zuweisungen bleiben erhalten.
5. Den Typ eines Bausteins wechseln. Fremde Einstellungen werden geleert und im Protokoll vermerkt (*Administration > Protokoll*).
6. Einstellungen > Extension-Konfiguration > be_groups: „Klassische Gruppen erlauben“ abschalten. „Klassisch“ verschwindet sofort aus der Auswahl, und neue Gruppen starten als Rolle.
7. Das Modul *Administration > Rollen & Bausteine*: Sortierung, Typ-Filter, markierte Inkonsistenzen und Bearbeiten-Links.
8. Konsistenzprüfung: `ddev typo3 begroups:audit` (auch `--format=json`, `--fail-on-warnings`). Neue Benutzer erscheinen mit `user-permissions`, weil der Core ihnen alle Dateioperationen gibt.
9. Assistenten, immer zuerst mit `--dry-run`: `ddev typo3 begroups:classify --dry-run`, dann ohne; `ddev typo3 begroups:split --all --dry-run`, dann ohne. Jede Umstellung wird intern pro Benutzer nachgeprüft und sonst zurückgerollt. Trotzdem mit „Benutzer wechseln“ prüfen, dass Benutzer genau dasselbe sehen und dürfen wie vorher (Seitenbaum, Module, Dateien, TSconfig-Wirkung), und `begroups:audit` erneut ausführen. Als „failed“ oder „manual“ gemeldete Gruppen mit Begründung notieren.
10. Starter-Set in einer leeren Testumgebung: `ddev typo3 setup:begroups:default --groups=Both`, dann `ddev typo3 begroups:split --all`.

Beim Ändern von Typ oder Rollenzusammensetzung fragt TYPO3 nach dem Passwort. Das ist der Sudo-Mode des Core und gewollt.

**Ergebnisse festhalten:** Auffälligkeiten mit Schritt, erwartetem und tatsächlichem Verhalten notieren. Sie werden zuerst als Test reproduziert, dann behoben.

---

## 6. Offene Entscheidungen und Rückfragen

| # | Frage | Stand |
|---|---|---|
| a | Titel der Extension | ✅ Bestätigt von Jonathan (2026-10-08): „Backend Group Kinds - Roles and building blocks for TYPO3 backend permissions“ (`composer.json`, Doku). |
| b | Review-Ablauf | ✅ Entschieden von Jonathan (2026-10-08, E16): Jeder Pull Request durchläuft die komplette Pipeline und ein strenges Code-Review, Jonathan gibt frei. Kein zusätzliches Community-Review. |
| c | Übersetzungen | ✅ Entschieden von Jonathan (2026-10-08, E15): Crowdin-Projekt von TYPO3. Umgesetzt: `.crowdin.yml` nach der offiziellen Vorlage, Workflow `Crowdin` (lädt die englischen Labels hoch, ohne Secrets übersprungen). Deutsch bleibt im Repo und hat Vorrang vor dem Sprachpaket. Schritte bei Jonathan: siehe unten. |
| d | Versionsnummer | Empfehlung 1.0.0 (E5). `composer.json` steht auf `1.0.0-dev`. |
| e | Alte Branches und Crowdin-PR #3 | Nach dem Relaunch archivieren bzw. schließen (M4). |
| – | E14 Keine Migration früherer Versionen | ✅ Entschieden von Jonathan (2026-10-08); Wizard entfernt. |
| – | Keine Fehler im Log | ✅ Vorgabe von Jonathan (2026-10-08): Bei der Nutzung der Extension entstehen keine Fehler im Log, weil keine erzeugt werden, nicht weil sie unterdrückt werden. Umgesetzt in `42071fe` (siehe Abschnitt 9), geprüft im Log-Test (Abschnitt 3). |
| f | E12 Typ-Filter im Users-Modul verworfen, E13 Starter-Set über den Core-Befehl | ✅ Bestätigt von Jonathan (2026-10-08). |
| – | Matrix-Bearbeitung | ✅ Entschieden von Jonathan (2026-10-08): kommt mit Version 1.1 (E2). |
| – | Infection und E2E-Tests | ✅ Entschieden von Jonathan (2026-10-08): beide vor 1.0. Umgesetzt als `-s mutation` und `-s e2e`. |
| – | PHP-Versionen | ✅ Vorgabe von Jonathan (2026-10-08): die volle Spanne, die TYPO3 14 erlaubt (PHP 8.2 bis 8.5). Werkzeuge mit höheren Anforderungen (Infection braucht PHP 8.3) laufen nur als PHAR im Container und werden keine Abhängigkeit. |
| g | Präfixe in Listen | ✅ Entschieden von Jonathan (2026-10-08): Der Typ erscheint überall automatisch als Präfix vor dem Titel („META: Redakteur“), Titel enthalten keinen Typ. Umgesetzt (`f208342`), inklusive Core-Modul „Users“ per abgesichertem Template-Override. |
| – | Mit Michael Klapper | ✅ Die Erlaubnis für den Relaunch hat Jonathan (Stand 2026-10-09, kein Nachweis im Repo nötig). Credits: Name mit morphodo / AOE, Link auf `github.com/michaelklapper`, keine E-Mail-Adresse, Hinweis „1.0 komplett neu geschrieben, Idee und Name von ihm“. Optional: er liest die Credits-Seite gegen; das blockiert 1.0.0 nicht. |

**Zugänge, die Jonathan einrichtet:**
- **GitHub, SSH-Schlüssel:** ✅ Gepusht wird nur als `Cretection` mit dem Secretive-Schlüssel `cretection@github.com` (Host `github.com-cretection`, eingetragen am 2026-10-08). Optional verhindert `IdentitiesOnly yes` im Host-Eintrag einen Rückfall auf den 8devs-Schlüssel, falls GitHub den Schlüssel einmal ablehnt (derzeit nicht gesetzt).
- **GitHub, eigenständiges Repo:** ✅ Am 2026-10-09 aus dem Fork-Netz von `AOEpeople/be_groups` (archiviert) gelöst. Als Fork erschien das Repo nicht in der GitHub-Suche, und Dependabot lief nicht. Die Versionsupdates von Dependabot starten, sobald `.github/dependabot.yml` mit dem Merge auf `main` liegt (Dependabot liest nur den Standard-Branch). Unter „Advanced Security“ empfohlen: Malware alerts und Dependabot security updates an; Private vulnerability reporting aus, weil `SECURITY.md` Meldungen an das TYPO3 Security Team leitet. Offen: Beschreibung unter „About“ (noch die alte, neu wie in `composer.json`), Website-Link, Social-Preview-Bild.
- **GitHub, Branch-Modell (Entscheidung von Jonathan, 2026-10-09):** `dev` wird der Standard-Branch und nimmt alle Änderungen und Dependabot-PRs auf, `main` ist der Release-Branch. Es enthält nur Veröffentlichtes und wird bei einem Release per Fast-Forward auf den geprüften Stand von `dev` vorgezogen (`git push origin origin/dev:main`), danach der Tag. docs.typo3.org rendert `main` und Tags, nicht `dev`. Dependabot, der Wochenlauf der Mutationstests und der Knopf „Run workflow“ brauchen die Dateien auf dem Standard-Branch, deshalb geht das erst mit `dev` als Standard. Umstellung (Jonathan):
  1. *Settings → Branches*: `relaunch` in `dev` umbenennen; *Settings → General → Default branch*: auf `dev` stellen. Lokal: `git fetch origin && git branch -m relaunch dev && git branch -u origin/dev dev && git remote set-head origin -a`.
  2. Ruleset `main` (id 24783350): Ziel von „Default branch“ auf `main` ändern, die Regel „Require a pull request“ entfernen, der Rest bleibt (Löschen, Force-Push, Status-Check `Required checks` aktuell). Ein Push von `dev` nach `main` ist erlaubt, weil der Check auf diesem Commit schon bestanden hat.
  3. Neues Ruleset `dev`: Ziel „Default branch“, Löschen und Force-Push sperren, sonst nichts. Der Check `Required checks` wird erst später auch für `dev` verlangt (dann nur noch über Pull Requests).
  Bis dahin gilt: Der Check `Required checks` und das Ruleset `main` sind eingerichtet (2026-10-09, per API geprüft).
- **TER:** ✅ Token „Release: be_groups“ (Scopes `extension:read`, `extension:write`, nur `be_groups`) liegt als Secret `TYPO3_API_TOKEN` in der GitHub-Environment `ter` (2026-10-09). Die Environment ist auf Tags `[0-9]*.[0-9]*.[0-9]*` beschränkt (per API geprüft). **Der Token läuft am 2026-10-15 ab:** Vor dem Tag `1.0.0` auf https://extensions.typo3.org/my-access-tokens/list erneuern („Refresh an access token“) und das Secret nachtragen, sonst scheitert der Upload. Der Extension-Key `be_groups` gehört bereits `cretection` (TER-API, geprüft am 2026-10-08; letzte Version 0.0.9 für TYPO3 11).
- **docs.typo3.org:** den Webhook für das Rendering der Doku.
- **Crowdin (E15):** Im TYPO3-Slack, Kanal `#typo3-localization-team`, die Aufnahme von `be_groups` beantragen (Name der Extension, E-Mail-Adresse für die Einladung). Danach im Repo die Secrets `CROWDIN_PROJECT_ID` und `CROWDIN_PERSONAL_TOKEN` anlegen (Token-Rechte laut [Doku](https://docs.typo3.org/permalink/t3coreapi:crowdin-extension-integration)), die deutschen Übersetzungen als „existing translations“ hochladen (`zip translations.zip Resources/Private/Language/*.*.xlf Resources/Private/Language/Modules/*.*.xlf`) und freigeben. Den Link zum Projekt in `CONTRIBUTING*.md` eintragen.
- **Packagist:** Das Paket `cretection/be-groups` besteht bereits (0.0.1–0.0.9 aus `Cretection/be_groups`, geprüft am 2026-10-08). Offen: prüfen, ob der Auto-Update-Hook aktiv ist.

**An den TYPO3 Core zu melden (Forge, öffentlich, keine Sicherheitslücke):**
- `SelectCheckBoxElement` (TYPO3 14.3.7, Zeile 164) rendert die Köpfe gruppierter Checkboxen als `role="tab"` ohne Tabliste und mit einem Button darin; axe meldet `aria-required-parent` und `nested-interactive`. Betrifft z. B. die Dateioperationen jeder Backend-Gruppe, auch ohne be_groups. Die E2E-Tests nehmen genau diese Elemente aus.
- Ohne `typo3/cms-frontend` lässt sich TYPO3 14.3.7 nicht hochfahren (`PageTypeLinkResolver` verlangt `LinkFactory`). Die E2E-Instanz installiert deshalb das Frontend mit.

**Inzwischen geklärt:**
- `render-guides` unterstützt übersetzte Handbücher (`Documentation/Localization.de_DE/`).
- Die Regeln greifen auch bei Importen (`isImporting`), aus Sicherheitsgründen ohne Ausnahme.

---

## 7. Nächste Aufgaben (Reihenfolge)

1. **Nach dem ersten CI-Lauf** (✅ grün am 2026-10-08; gepusht von Jonathan als `Cretection` per SSH mit dem Secretive-Schlüssel, Remote `git@github.com-cretection:Cretection/be_groups.git`). Vor jedem Push alle Commits auf Hinweise auf LLMs prüfen.
   - ✅ Der Job `mutation` lief am 2026-10-09 einmal über „Actions → CI → Run workflow“ auf `dev`: grün, MSI 82 %.
   - Die Pipeline beobachten, besonders die Container-Images und den Job `typo3-next`. Der Runner `ubuntu-26.04` ist seit dem 2026-09-17 allgemein verfügbar; `actionlint` 1.7.12 kennt das Label noch nicht (falscher Alarm).
   - ✅ Branch-Schutz eingerichtet (2026-10-09, Abschnitt 6). Das Branch-Modell (`dev` Standard, `main` Release) steht in Abschnitt 6.
   - Die Testabdeckung wird zusammengeführt und geprüft (Job `coverage`, siehe Abschnitt 3).
2. **Klicktest von Jonathan** in `begroups-test` (Abschnitt 5, aktuelles Szenario). Befunde zuerst als Test reproduzieren, dann beheben.
   - Sichtprüfung am 2026-10-08 abgeschlossen: alle Typ-Formulare, Ablehnungen im Formular (Rolle in Rolle, Baustein am Benutzer) mit Titel und uid in der Meldung, Typ-Auswahl bei abgeschalteten klassischen Gruppen (neue Gruppen starten als Rolle, bestehende klassische Gruppen behalten „Classic“), Rollenformular mit über 3.000 Gruppen in der Auswahl (Aufbau etwa 1 s).
   - Vorschlag: Das Modul zeigt die Bausteine einer Rolle nach Typ gruppiert, nicht in der gespeicherten Reihenfolge, die den TSconfig-Vorrang bestimmt. Die Reihenfolge zusätzlich anzeigen. ✅ Entschieden von Jonathan (2026-10-08): ja, nur wo sie zählt. Umgesetzt (`f8d5d29`): „Vorrang im TSconfig“ ab zwei sichtbaren TSconfig-Bausteinen.
   - Hinweis zur Testumgebung: `backend:user:create` legt Benutzer ohne `workspace_perms` an. Mit EXT:workspaces sehen sie dann kein Modul; das Backend-Formular setzt den Standardwert 1. Für den Testbenutzer `editor` ist der Wert gesetzt.
3. **Für Version 1.1** (Rest von M2, nicht Teil von 1.0):
   - **Matrix-Bearbeitung** im Modul: Rollen × Bausteine. Geschrieben wird ausschließlich über den DataHandler (AJAX-Route mit `methods: POST`), mit Sudo-Mode und Barrierefreiheit (Tastatur, ARIA-Grid). Alle Themes hell und dunkel. ✅ Entschieden von Jonathan (2026-10-08): **Version 1.1** (E2), weil sie das ganze Frontend-Tooling voraussetzt und das Bearbeiten über die normalen Formulare geht.
   - **Frontend-Tooling** aufsetzen, bevor JavaScript entsteht: `package.json`, TypeScript strict, ESLint (Konfiguration des Core), Stylelint 17 (Konfiguration von tea), rollup ohne Bündelung, web-test-runner, Playwright mit axe (WCAG 2.2 AA). Die axe-Prüfung vom 2026-10-08 lief einmalig außerhalb des Repos.
   - **Weitere PSR-14-Events** nur bei einem konkreten Anwendungsfall (vorhanden: `AfterAuditFindingsCollectedEvent`, `ModifyKindOfNewGroupEvent`). Typen und Felder bleiben TCA (keine eigene Registry).
   - **Idee von Jonathan:** Die Vorschläge der Assistenten (`classify`/`split --dry-run`) auch im Modul „Rollen & Bausteine“ anzeigen.
   - **Zu prüfen:** Nach dem Massentest blieben 143 Rollen in Rollen. Ein Assistent könnte sie durch ihre Bausteine ersetzen. Das ändert aber die Mitgliedschaft in der inneren Rolle, die Seiten besitzen, Workspace-Mitglied sein oder in TSconfig-Bedingungen stehen kann. Ob und mit welchen Vorbedingungen das geht, ist **offen**.
4. **M4 (Rest):**
   - Übersetzungen (Entscheidung c), TER-Token und Webhooks (Abschnitt 6). Die Credits mit Michael Klapper sind erledigt (Abschnitt 6).
   - Starter-Set: am 2026-10-08 mit dem echten Core-Befehl in `begroups-test` geprüft (siehe Abschnitt 1). Automatisch geht das nicht: `setup:begroups:default` lässt sich in Functional Tests nicht instanziieren, weil seine Abhängigkeiten den Failsafe-Modus des Install-Tools verlangen. `SplitCoreDefaultGroupsTest` bildet deshalb die Inserts des Core nach (Stand 14.3.7); bei neuen Core-Versionen den Live-Test wiederholen.
   - Vor dem Wechsel auf TYPO3 15: Tests an das typisierungsfreie `get()` des Testing-Frameworks anpassen (siehe Abschnitt 3).
   - Release nach den Schritten in `CONTRIBUTING.md`: `php Build/Scripts/setVersion.php 1.0.0`, in `CHANGELOG.md` aus `## [Unreleased](…)` die Überschrift `## [1.0.0](https://github.com/Cretection/be_groups/compare/0.0.9...1.0.0) - JJJJ-MM-TT` machen (der Eintrag für 1.0.0 ist von Hand geschrieben, git-cliff gilt ab 1.0.1), Changelogs des Handbuchs datieren, `php Build/Scripts/checkReleaseVersion.php 1.0.0`, `main` auf `dev` vorziehen, annotierter Tag `1.0.0`. Vorabversionen (`1.0.0-rc1`) gehen nur zu Packagist.
   - Möglich: deutsche Screenshots (deutsches Sprachpaket im Testsystem); bisher zeigen beide Handbücher englische Oberflächen.

**Vor jedem Release:**
- Alle Prüfungen müssen grün sein, inklusive `typo3-next`.
- Die Daten zum Support-Ende von TYPO3 auf https://get.typo3.org prüfen.
- Die Tabellen in `SECURITY.md` und in der Doku aktualisieren, sobald TYPO3 15 und 16 Termine haben.

---

## 8. Bekannte Grenzen (dokumentiert in der Doku, Kapitel *Concept*)

- **Rechte am Benutzerdatensatz:** be_users hat eigene Rechtefelder (z. B. `file_permissions`, Module, TSconfig, Freigaben), die TYPO3 mit den Gruppenrechten zusammenführt. Das Rollenmodell steuert sie nicht. Neue Benutzer bekommen im Core alle Dateioperationen. `begroups:audit` meldet das als Warnung `user-permissions`.
- **Rechtefelder anderer Extensions:** Werden sie nur im Core-Formular ergänzt (Extension lädt vor be_groups und ordnet das Feld keinem Typ zu), erscheinen sie nur bei klassischen Gruppen und werden nicht durchgesetzt. Abhilfe: das Feld per `addToAllTCAtypes` einem Typ zuordnen.
- **Importe:** Lehnen die Regeln bei einem Import einen Datensatz ab, steht das im Systemprotokoll, nicht in der Fehlerliste des DataHandler. Diese ist `@internal`.
- **Transaktionen der Assistenten:** Sie umfassen die Datenbankverbindung von `be_groups`. Sind `sys_log`, `sys_history` oder `sys_refindex` einer anderen Verbindung zugeordnet, bleiben deren Einträge einer zurückgerollten Umstellung erhalten.
- **Alte Werte bleiben:** R2 und R4 lehnen nur *neu hinzugefügte* Beziehungen ab. Bereits gespeicherte Verstöße bleiben bestehen, damit niemand Zugriff verliert. Sie werden im Modul markiert.

---

## 9. Wichtige Designentscheidungen aus dem Review

Sie sind wichtig, damit niemand die behobenen Fehler versehentlich wieder einbaut:

- **Keine gefilterten Auswahlfelder** für `be_users.usergroup` und `be_groups.subgroup`. Ein Select-Feld behält beim Speichern nur Werte aus seiner Item-Liste, ein Filter löscht also gespeicherte Zuweisungen. Die Listen werden stattdessen nach Typ gruppiert, und die Regeln setzen das Modell beim Speichern durch.
- **Rollen nutzen die zweispaltige Core-Liste** (`selectMultipleSideBySide`), nicht `selectCheckBox`. Checkboxen sortieren die Mitglieder um und verändern damit den TSconfig-Vorrang.
- **Gültige Typen bestimmt allein die `KindRegistry`**, also die konfigurierten Items von `tx_begroups_kind`. Numerische Alt-Typen oder Typen deinstallierter Extensions sind weder Rolle noch Baustein.
- **R1 verwaltet nur Rechtefelder:** die Rechtefelder des Core und alle Felder, die das Formular einer Rolle oder eines Bausteins zeigt. Fremde Daten, etwa die ID eines Sync-Tools, bleiben unberührt.
- **Kinds niemals als Array-Schlüssel** verwenden, denn PHP macht aus numerischen Strings Ganzzahlen.
- **Neue Datensätze ohne Typ** bekommen den Typ, den auch der DataHandler setzen würde (TCA-Default, dann User-TSconfig, dann Page-TSconfig). Der Hook schreibt ihn ausdrücklich ins Datamap.
- **Meldungen erst in `processDatamap_afterAllOperations`:** Bei einem abgebrochenen Speichern, etwa im Sudo-Mode, wird nichts protokolliert. Der Hook ist deshalb `shared: false`.
- **Keine Migration früherer Versionen** (E14): Der Upgrade-Wizard für 0.0.x und AOE 1.x ist entfernt.
- **`be_groups.subgroup` ist auf 2048 Zeichen erweitert**, weil Rollen viele Bausteine bündeln.
- **Nur öffentliche Core-API**, geprüft per PHPStan `internalTag`. Ausnahme: der Wert `2` für `applicationType` in Tests, weil die Core-Konstante intern ist.
- **Assistenten ändern keine wirksamen Rechte** (nach dem unabhängigen Review vom 2026-10-08 neu gefasst):
  - Alles läuft über den DataHandler, pro Gruppe in einer Transaktion.
  - **Nachprüfung pro Benutzer:** Vor dem Commit löst `GroupPermissionResolver` jede vorkommende Gruppen-Kombination der Benutzer und die Gruppe selbst (auch versteckt) vorher und nachher auf. Verglichen werden die zusammengeführten Rechte, die Workspace-Rechte, das TSconfig in Anwendungsreihenfolge, die Mitgliedschaften und `firstMainGroup`. Bei jeder Abweichung folgt ein Rollback.
  - Das ist ein **Modell** der Core-Auflösung, weil `fetchGroupData()`, `GroupResolver`, `groupData` und `firstMainGroup` im Core `@internal` sind. `GroupPermissionResolverTest` gleicht das Modell über die öffentliche API (`userGroupsUID`, `check()`, `getWebmounts()`, `getTSConfig()`) mit dem Core ab.
  - **Bausteine werden nie wiederverwendet.** Bestehende Gruppen können als Seiten-Eigentümer, Workspace-Mitglied oder in TSconfig-Bedingungen referenziert sein; zusätzliche Mitglieder könnten Rechte gewinnen (Review-Befund 1, kritisch).
  - **Listen wie der Core lesen:** TYPO3 liest Gruppenlisten per `intExplode`. `be_groups_5` wird dabei zu 0 und trifft nichts, Duplikate zählen an ihrer letzten Position. Der DataHandler liest dieselben Listen anders. Gruppen mit solchen Listen behandeln die Assistenten nicht (`RelationList::isCanonical`); das Audit meldet sie (`unclean-group-list`).
  - Felder liest das Modell wie der Core: Seitenfreigabe `0` ist die Wurzel und zählt; Datei- und Kategoriefreigabe `0` zählt nicht. Gruppen mit Seitenfreigabe `0` lassen sich nicht aufteilen, weil der DataHandler `0` nicht in einen neuen Baustein schreibt.
  - TYPO3 löst Untergruppen **vor** der Gruppe auf. Neue Bausteine kommen deshalb **hinter** die bisherigen Untergruppen (TSconfig-Vorrang).
  - Bausteine versteckter Gruppen sind sichtbar. Sie sind nur über die versteckte Rolle erreichbar, und Einblenden stellt die Rechte wie vorher her.
  - Bei einem Fehlschlag nennt das Ergebnis die vom DataHandler protokollierten Datenbankfehler (`sys_log` Typ 1), weil das Protokoll mit zurückgerollt wird. Unter PostgreSQL bricht die Transaktion ab, dann nennt das Ergebnis nur das.
  - **Eigentümergruppe neuer Seiten:** Hat die Gruppe keine aktiven Untergruppen, wäre sie für ihre Benutzer `firstMainGroup`. Dann wird eine neue, leere Seitenrechte-Gruppe `PG_<Titel>` erstes Mitglied. Sie gehört nur zur Rolle, hat also genau deren Mitglieder; die Prüfung erlaubt nur diesen Wechsel (zweites Review, Befund 1).
  - **Rohdaten-Vergleich:** Vor dem Commit müssen alle anderen Gruppen und alle Benutzer byte-gleich sein, und an der Gruppe selbst dürfen sich nur `tstamp`, Typ, Untergruppen und die verwalteten Rechtefelder ändern. So fallen auch Hooks auf, die anderswo Daten ändern (zweites Review, Befund 2).
  - Das Modell liest Seitenfreigaben wie `filterValidWebMounts()` (`05`, `+5`, `abc` fallen weg, `0` und negative Zahlen bleiben) und `hidden` wie die `HiddenRestriction` (nur `0` ist sichtbar).
  - **Nur erreichbare Kombinationen** werden aufgelöst (`GroupPermissionResolver::findListsReaching()`): Eine Gruppen-Kombination, die die Gruppe über keine Untergruppen-Kette erreicht, kann sich nicht ändern, weil alle anderen Gruppen byte-gleich bleiben. Ein Zufallstest sichert, dass keine betroffene Kombination fehlt. Benutzer werden ohne Normalisierung verglichen (gleiche Verbindung, gleiche Typen); neue und verschwundene Datensätze zählen als Änderung.
  - **Cache-Befehle** aus Page-TSconfig (`TCEMAIN.clearCacheCmd`) hält `CacheCommandDeferral` (Hook `clearCachePostProc`) während einer Umstellung zurück und führt sie nach dem Commit aus: `TRUNCATE` der Cache-Tabellen beendet unter MySQL/MariaDB die Transaktion. Test mit Datenbank-Cache-Backend in `GroupConverterCacheTest`.
- **Präfix statt Typ im Titel:** `ctrl.label_userFunc` von be_groups (`GroupRecordTitle`, `KindPrefix`) setzt das Präfix überall, wo TYPO3 Datensatztitel zeigt. Das Core-Modul „Users“ gibt Titel aus seinem internen Extbase-Modell aus: Gruppenfilter über das öffentliche Event `AfterBackendGroupFilterListIsAssembledEvent`, sieben Templates per Page-TSconfig `templates."typo3/cms-beuser"` überschrieben (`Resources/Private/TemplateOverrides/`). Die Overrides werden nur aktiviert, wenn die Core-Templates exakt den hinterlegten SHA-256-Hashes entsprechen (`OverrideUserModuleTemplates::BASE_TEMPLATES`), damit nie eine alte Kopie eine Sicherheitskorrektur des Core verdeckt. **Bei jedem Core-Update** (der Test `UserModuleTest::theTemplatesOfTheCoreAreThoseTheOverridesAreBasedOn` schlägt fehl): Core-Template neu kopieren, Titel-Ausgaben wieder durch `begroups:groupTitle` ersetzen, Hash aktualisieren.
- **Icons:** Typen nutzen einfarbige `actions-*`-Icons des Core, die dem hellen und dunklen Theme folgen. Das Modul-Icon (`module-begroups-roles`) ist im Stil der Core-Modul-Icons gezeichnet (`currentColor`, Rolle in `--icon-color-accent`). Bei einer Änderung eines Icons immer eine **neue Kennung** vergeben, denn das Backend puffert Icon-Markup im `localStorage` des Browsers unter der Kennung.
- **Log-Stufen:** Beabsichtigte Leerungen nach R1 (Typwechsel) sind Informationen (Stufe 0). Abgelehnte Werte und Datensätze sind Benutzerfehler (Stufe 1), `SECURITY_NOTICE` wird nicht verwendet. Felder, die der Aufrufer selbst leert (etwa die Assistenten), werden nicht gemeldet.
- **Gespeicherte Listen wie der Core lesen:** `RelationList::fromStoredValue()` liest wie `intExplode()`. Nur so zählt ein gespeicherter Eintrag als „bereits vorhanden“ (R2/R4) oder als Mitglied im Modul. Eingehende Werte versteht `RelationList::fromValue()` in allen Schreibweisen des DataHandler.
- **Platzhalter nur für Gruppen:** Ein `NEW…`-Platzhalter, der in derselben Datamap auch für einen Datensatz einer anderen Tabelle steht, wird abgelehnt; der DataHandler ordnet Platzhalter tabellenübergreifend zu.
- **Standardwerte neuer Datensätze:** `usergroup` und `subgroup` aus TCA und `TCAdefaults` (auch typspezifisch) mischt der Core erst nach dem Hook ein. Die Regeln bilden die Reihenfolge mit öffentlicher API nach, prüfen den Wert und schreiben ihn ausdrücklich.
- **Felder ohne Typ** (nur im Formular klassischer Gruppen): `KindFieldResolver::getUnassignedFieldNames()`; der Klassifizierer stellt Gruppen mit Werten darin nicht um, das Audit meldet `unassigned-fields`.
- **Pufferung pro TCA-Schema:** `KindRegistry` und `KindFieldResolver` puffern in `WeakMap`s mit dem Schema-Objekt als Schlüssel; ein neu aufgebautes TCA ergibt neue Schema-Objekte, der Puffer kann also nicht veralten.
- **Ausgaben auf der Konsole** laufen durch `ConsoleTextUtility::escape()`: Titel sind Redakteursdaten und dürfen weder Konsolen-Formatierung noch Steuerzeichen (ANSI) oder unsichtbare Formatzeichen (z. B. bidirektionale Überschreibungen) ins Terminal tragen.
- **Testdaten:** Der CSV-Import des Testing-Frameworks füllt fehlende Spalten mit TCA-Defaults (z. B. `file_permissions` = alle Dateioperationen). Rechtefelder in Fixtures daher immer ausdrücklich setzen.

---

## 10. Wo was liegt

| Bereich | Dateien |
|---|---|
| Typen | `Classes/Domain/Kind/` (`GroupKind`, `KindRegistry`, `KindDefinition`, `KindFieldResolver`) |
| Regeln | `Classes/DataHandling/GroupKindRules.php`, `RelationList.php`, `RuleViolationReporter.php`, registriert in `ext_localconf.php` |
| Formular | `Configuration/TCA/Overrides/be_groups.php` und `be_users.php`, `Classes/Form/FormDataProvider/KindSelection.php` |
| Modul | `Configuration/Backend/Modules.php`, `Classes/Controller/OverviewController.php`, `Classes/Domain/Overview/`, `Resources/Private/Templates/Overview/Index.fluid.html` |
| Befehle | `Classes/Command/` (`AuditCommand`, `ClassifyCommand`, `SplitCommand`), Konsolenausgabe `Classes/Utility/ConsoleTextUtility.php` |
| Konsistenzprüfung | `Classes/Domain/Audit/` (`PermissionAudit`; öffentliche API: `AuditFinding`, `AuditSeverity`), `Classes/Event/AfterAuditFindingsCollectedEvent.php` (öffentliche API) |
| Assistenten | `Classes/Domain/Classification/`: `GroupClassifier` schlägt vor, `GroupConverter` setzt um und prüft nach, `GroupPermissionResolver` modelliert die Core-Auflösung; `Classes/DataHandling/CacheCommandDeferral.php` hält Cache-Befehle zurück |
| Release | `.github/workflows/publish.yml`, `Build/Scripts/setVersion.php`, `Build/Scripts/checkReleaseVersion.php`, Schritte in `CONTRIBUTING.md` |
| Events (öffentliche API) | `Classes/Event/` (`AfterAuditFindingsCollectedEvent`, `ModifyKindOfNewGroupEvent`) |
| Datenzugriff | `Classes/Domain/Repository/` (nur lesend; geschrieben wird ausschließlich über den DataHandler) |
| Labels | `Resources/Private/Language/` (`db.xlf`, `messages.xlf`, `Modules/overview.xlf`, jeweils mit `de.`) |
| Tests | `Tests/Unit/`, `Tests/Functional/` (Fixtures in `Fixtures/`, Test-Extensions in `Fixtures/Extensions/`) |
| E2E-Tests | `Build/tests/playwright/` (Specs in `e2e/`, Backend-Fixture mit axe in `fixtures/backend.ts`, Testdaten `scenario.php`), Instanz aus `Build/Scripts/setupE2E.sh` in `.Build/e2e`, Werkzeuge in `Build/package.json` |
| Mutationstests | `Build/infection/` (Konfiguration und gemeinsame PHPUnit-Konfiguration beider Suiten), PHAR mit Prüfsumme über `Build/Scripts/downloadVerifiedFile.php` |
| Tooling | `Build/` (runTests.sh, PHPStan, php-cs-fixer, Rector, PHPUnit, Integritätsprüfungen), `.github/workflows/ci.yml` |
| Doku | `Documentation/` (EN) und `Documentation/Localization.de_DE/` (DE) |
| Planung | `RELAUNCH.md` (Konzept, Entscheidungen E1–E16, Roadmap), `STATUS.md` (dieses Dokument) |

---

## 11. Arbeitsweise

- **Regeln:** Es gelten die Coding-Leitlinien und die Definition of Done (`RELAUNCH.md` §8.2).
- **Fehler:** Jeder Fehler wird zuerst mit einem Test reproduziert, der mit dem alten Code fehlschlägt.
- **Vor jedem Commit:** `composer check:static` sowie die Unit- und Functional-Tests ausführen; bei Änderungen an Datenbankzugriffen zusätzlich MariaDB und PostgreSQL per `runTests.sh`.
- **Commit-Nachrichten** nach `CODING_GUIDELINES.md` §17: `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` oder `[SECURITY]`, auf Englisch, mit einem Rumpf, der das *Warum* erklärt.
- **Sprachen:** Alles, was Menschen lesen, entsteht auf Deutsch und Englisch; beide Fassungen werden im selben Commit aktualisiert.
- **Dieses Dokument** wird am Ende jeder Arbeitssitzung aktualisiert.
