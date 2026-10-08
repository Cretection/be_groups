# Arbeitsstand be_groups-Relaunch

Stand: **2026-10-08** · Branch **`relaunch`** (lokal, noch nicht gepusht) · Ziel: **1.0.0 für TYPO3 14.3 LTS**

Dieses Dokument ist der Einstiegspunkt, um die Arbeit fortzusetzen. Das Konzept steht in [`RELAUNCH.md`](RELAUNCH.md), die verbindlichen Regeln für den Code in [`CODING_GUIDELINES.de.md`](CODING_GUIDELINES.de.md) bzw. [`CODING_GUIDELINES.md`](CODING_GUIDELINES.md).

---

## 1. Kurzfassung

- **Umgesetzt:**
  - der Kern (M0 und M1): Gruppentypen, Regeln R1–R4, Upgrade-Wizard,
  - das Übersichtsmodul ohne Bearbeitung (Teil von M2),
  - die Konsistenzprüfung `begroups:audit` mit dem Event `AfterAuditFindingsCollectedEvent` (Teil von M2),
  - die Assistenten `begroups:classify` und `begroups:split` und das Starter-Set über den Core-Befehl (M3),
  - das Event `ModifyKindOfNewGroupEvent` für Import- und Sync-Werkzeuge (Teil von M2),
  - die Doku auf Deutsch und Englisch.
- **Reviews:**
  - Das erste strenge Review (2026-10-07) fand 15 echte Fehler. Alle sind behoben, jeder mit einem Test, der den alten Fehler nachweislich erkennt.
  - Ein unabhängiges Review der Teile vom 2026-10-08 fand 12 Punkte, darunter einen kritischen: Die Wiederverwendung bestehender Bausteine konnte Benutzern Rechte geben. Alle sind behoben (`c04fa92`, Details in Abschnitt 9).
  - Ein zweites unabhängiges Review dieses Fixes fand keinen Weg mehr, wirksame Rechte ohne fremde Hooks zu ändern, aber 8 weitere Punkte (u. a. Eigentümergruppe neuer Seiten, Hook-Änderungen außerhalb der Gruppe, überlebende Mutanten, kaputte deutsche Tabelle). Alle sind behoben (Abschnitt 9); 11 gezielte Mutanten werden von den Tests erkannt.
  - **Live-Test in DDEV** (2026-10-08, eigenes Projekt `begroups-test`, Abschnitt 5): `setup:begroups:default` + `begroups:split --all` mit den echten Core-Presets. Die wirksamen Rechte eines Redakteurs, über den Core wie beim Login ermittelt, sind vorher und nachher in 16 von 18 Aspekten identisch. Die zwei Unterschiede sind beabsichtigt: zusätzliche Bausteine in der Gruppenliste und `PG_Editor` als Eigentümergruppe neuer Seiten.
- **Testbasis:** Alle Prüfungen sind grün, auf allen Datenbanken, mit PHP 8.2 und 8.5, auf TYPO3 14.3 und 15-dev.
- **Nächster Schritt:** Test im DDEV-Projekt (Abschnitt 5), danach Push und erster CI-Lauf auf GitHub (Abschnitt 7).

---

## 2. Erledigt

| Commit | Inhalt |
|---|---|
| `[DOCS] Add relaunch concept and coding guidelines` | Machbarkeitsstudie, Konzept, Coding-Leitlinien (DE/EN) |
| `[!!!][TASK] Remove the legacy implementation` | alter Code entfernt, Lizenz GPL-2.0-or-later |
| `[TASK] Add quality tooling and continuous integration` | php-cs-fixer, PHPStan max, Rector, PHPUnit, `runTests.sh`, GitHub Actions |
| `[FEATURE] Add group kinds with enforced permission rules` | 11 Typen, TCA pro Typ, DataHandler-Regeln R1–R4 |
| `[FEATURE] Add upgrade wizard for groups of be_groups 0.0.x` | Migration der alten Daten |
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

**Stand der Meilensteine** (Details in `RELAUNCH.md` §9):

| Meilenstein | Stand |
|---|---|
| M0 Fundament | ✅ erledigt. Der CI-Lauf auf GitHub steht noch aus, weil nichts gepusht ist. |
| M1 Kern | ✅ erledigt. Die eigenen PSR-14-Events sind nach M2 verschoben. |
| M2 Übersicht | 🟡 teilweise. Fertig: Modul ohne Bearbeitung, `begroups:audit` mit Event, `ModifyKindOfNewGroupEvent`. Verworfen: Typ-Filter im Users-Modul (E12). Offen: Matrix-Bearbeitung, Präfixe (Entscheidung offen, siehe Abschnitt 6). |
| M3 Umstieg | ✅ erledigt: `begroups:classify`, `begroups:split`, Starter-Set über den Core-Befehl (E13). |
| M4 Härtung und Release | ⬜ offen |

---

## 3. Qualitätsnachweis (letzter Lauf am 2026-10-08)

| Prüfung | Ergebnis |
|---|---|
| Unit-Tests | 31 Tests grün |
| Functional Tests | 119 Tests grün auf SQLite, MariaDB 10.11, MySQL 8.0 und PostgreSQL 14 |
| PHP-Versionen | 8.2 (niedrigste Abhängigkeiten) und 8.5 (zuletzt mit 98 Tests geprüft, vor dem zweiten Review-Fix) |
| TYPO3-Versionen | 14.3.7 und 15.0-dev (Core `main`, PHPUnit 12) (zuletzt mit 98 Tests geprüft, vor dem zweiten Review-Fix) |
| Mutationsproben | 11 gezielte Mutanten in Prüfung und Modell, alle von Tests erkannt |
| PHPStan | Level max, ohne Baseline, mit Prüfung auf `@internal`: 0 Fehler. Gegen 15-dev: `Classes/` ohne Befund; die Tests melden dort `mixed` aus `get()`, weil das Testing-Framework für v15 die Typisierung geändert hat (vor dem Wechsel auf v15 zu lösen). |
| Weitere Prüfungen | php-cs-fixer, Lizenzköpfe, Rector, Lint, PSR-4, Integrität (Exception-Codes, Testkonventionen), XLIFF, YAML, JSON, `composer normalize`: alle grün |
| Doku | `render-guides` (EN und DE) ohne Warnungen |
| Gegen TYPO3 15 | 0 genutzte APIs, die in v15 deprecated oder intern sind |

---

## 4. Schnellstart: lokal weiterarbeiten

```bash
git checkout relaunch
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
- `check-permissions.php <uid>` im Projekt gibt die wirksamen Rechte eines Benutzers als JSON aus, über den Core wie beim Login.
- Starten mit `cd ~/Developer/Local/begroups-test && ddev start`. Die anderen DDEV-Projekte bleiben unberührt; nie `ddev poweroff` oder `ddev stop --all` verwenden.

Das andere DDEV-Projekt gehört zu einem **anderen Projekt**. Deshalb zuerst einen Snapshot anlegen und die Extension nur als Kopie einbinden. Voraussetzung ist TYPO3 14.3 im Composer-Modus.

```bash
ddev snapshot --name vor-be-groups                       # Zurück mit: ddev snapshot restore vor-be-groups
git clone -b relaunch ~/Developer/GitHub.com/Cretection/be_groups packages/be_groups
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
8. Mit Daten der alten Extension: zuerst *Analyze Database Structure*, dann den Wizard „Migrate be_groups kinds“ ausführen und seine Ausgabe lesen.
9. Konsistenzprüfung: `ddev typo3 begroups:audit` (auch `--format=json`, `--fail-on-warnings`). Neue Benutzer erscheinen mit `user-permissions`, weil der Core ihnen alle Dateioperationen gibt.
10. Assistenten, immer zuerst mit `--dry-run`: `ddev typo3 begroups:classify --dry-run`, dann ohne; `ddev typo3 begroups:split --all --dry-run`, dann ohne. Jede Umstellung wird intern pro Benutzer nachgeprüft und sonst zurückgerollt. Trotzdem mit „Benutzer wechseln“ prüfen, dass Benutzer genau dasselbe sehen und dürfen wie vorher (Seitenbaum, Module, Dateien, TSconfig-Wirkung), und `begroups:audit` erneut ausführen. Als „failed“ oder „manual“ gemeldete Gruppen mit Begründung notieren.
11. Starter-Set in einer leeren Testumgebung: `ddev typo3 setup:begroups:default --groups=Both`, dann `ddev typo3 begroups:split --all`.

Beim Ändern von Typ oder Rollenzusammensetzung fragt TYPO3 nach dem Passwort. Das ist der Sudo-Mode des Core und gewollt.

**Ergebnisse festhalten:** Auffälligkeiten mit Schritt, erwartetem und tatsächlichem Verhalten notieren. Sie werden zuerst als Test reproduziert, dann behoben.

---

## 6. Offene Entscheidungen und Rückfragen

| # | Frage | Stand |
|---|---|---|
| a | Titel der Extension | Vorschlag umgesetzt: „Backend Group Kinds - Roles and building blocks for TYPO3 backend permissions“ (`composer.json`, Doku). **Bestätigung offen.** |
| b | Review-Ablauf | Vorschlag: Jeder Pull Request durchläuft die komplette Pipeline und ein strenges Code-Review, Jonathan gibt frei, bei Meilensteinen kommt ein Review aus der Community. **Bestätigung offen.** |
| c | Übersetzungen | Offizielle TYPO3-Lokalisierung oder das eigene Crowdin-Projekt (`crowdin.yml` ist auf die neuen Dateien angepasst). **Offen, bis M4.** |
| d | Versionsnummer | Empfehlung 1.0.0 (E5). `composer.json` steht auf `1.0.0-dev`. |
| e | Alte Branches und Crowdin-PR #3 | Nach dem Relaunch archivieren bzw. schließen (M4). |
| f | E12 Typ-Filter im Users-Modul verworfen, E13 Starter-Set über den Core-Befehl | Autonom entschieden am 2026-10-08, begründet in `RELAUNCH.md` §11. **Bestätigung offen.** |
| g | Präfixe (R_, ACL_, …) in Listen anzeigen (M2) | Die Listen sind bereits nach Typ gruppiert, das Modul zeigt Typ-Icons, und `begroups:split` benennt neue Bausteine mit Präfix. Eine zusätzliche Präfix-Anzeige wäre doppelt. Vorschlag: streichen. **Entscheidung offen.** |
| – | Mit Michael Klapper | Namensnennung (ohne/mit Firma), Link und E-Mail-Adresse, ob er die Credits-Seite gegenliest, optional ein Blick auf das Konzept. Bis zur Freigabe wird nur sein Name genannt. |

**Zugänge, die Jonathan einrichtet:**
- **GitHub:** Branch-Schutz für `main` mit Pflichtprüfungen, sobald die Pipeline einmal gelaufen ist.
- **TER:** einen Token als GitHub-Secret für den Release-Workflow (M4).
- **docs.typo3.org:** den Webhook für das Rendering der Doku.
- **Packagist:** prüfen, ob der Auto-Update-Hook aktiv ist.

**Inzwischen geklärt:**
- `render-guides` unterstützt übersetzte Handbücher (`Documentation/Localization.de_DE/`).
- Die Regeln greifen auch bei Importen (`isImporting`), aus Sicherheitsgründen ohne Ausnahme.

---

## 7. Nächste Aufgaben (Reihenfolge)

1. **Test im DDEV-Projekt** (Abschnitt 5). Befunde zuerst als Test reproduzieren, dann beheben.
2. **Push und erster CI-Lauf** (`git push -u origin relaunch`).
   - Die Pipeline beobachten, besonders den Runner `ubuntu-26.04`, die Container-Images und den Job `typo3-next`.
   - Danach den Branch-Schutz einrichten.
   - Offen: Die Testabdeckung zusammenführen (TODO in `ci.yml`, braucht `phpunit/phpcov`).
3. **Sichtprüfung im Backend** der Testumgebung `begroups-test`: Formulare je Typ, Modul „Rollen & Bausteine“, Redakteur vor und nach der Umstellung. Die CLI-Prüfung ist erledigt (siehe Abschnitt 1).
4. **M2 vervollständigen:**
   - **Weitere PSR-14-Events** nur bei einem konkreten Anwendungsfall (vorhanden: `AfterAuditFindingsCollectedEvent`, `ModifyKindOfNewGroupEvent`). Typen und Felder bleiben TCA (keine eigene Registry).
   - **Frontend-Tooling** aufsetzen, bevor JavaScript entsteht: `package.json`, TypeScript strict, ESLint (Konfiguration des Core), Stylelint 17 (Konfiguration von tea), rollup ohne Bündelung, web-test-runner, Playwright mit axe (WCAG 2.2 AA).
   - **Matrix-Bearbeitung** im Modul: Rollen × Bausteine. Geschrieben wird ausschließlich über den DataHandler (AJAX-Route mit `methods: POST`), mit Sudo-Mode und Barrierefreiheit (Tastatur, ARIA-Grid). Alle Themes hell und dunkel.
   - **Präfixe** in Listen: Entscheidung (g) in Abschnitt 6.
5. **M4:**
   - Security-Review (`CODING_GUIDELINES.de.md` §15), Performance-Benchmark (1.000 Gruppen und 5.000 Benutzer), Prüfung der Barrierefreiheit. Bekannt: Jede Umstellung klassifiziert neu und liest dafür alle Gruppen und Benutzer; die Nachprüfung löst jede vorkommende Gruppen-Kombination zweimal auf. Bei sehr vielen Gruppen wächst der Aufwand von `begroups:split --all` quadratisch.
   - Starter-Set mit dem echten Core-Befehl prüfen (DDEV-Schritt 11). Automatisch geht das nicht: `setup:begroups:default` lässt sich in Functional Tests nicht instanziieren, weil seine Abhängigkeiten den Failsafe-Modus des Install-Tools verlangen (am 2026-10-08 ausprobiert). `SplitCoreDefaultGroupsTest` bildet deshalb die Inserts des Core nach (Stand 14.3.7, ohne `file_permissions` und TSconfig).
   - Vor dem Wechsel auf TYPO3 15: Tests an das typisierungsfreie `get()` des Testing-Frameworks anpassen (siehe Abschnitt 3).
   - Screenshots pro Theme, Übersetzungen, Credits mit Michael Klapper abstimmen.
   - Release-Workflow (`.github/workflows/publish.yml` mit tailor 2.x, TER-Upload ohne `ext_emconf.php` testen), Tag `1.0.0`.

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
- **Der Upgrade-Wizard ändert keine wirksamen Rechte:**
  - Er bricht ab, solange die Spalte noch vom Typ Integer ist.
  - Deaktivierte Gruppen bekommen deaktivierte Bausteine.
  - Er ergänzt keine Mitglieder aus den alten `subgroup_*`-Spalten, sondern meldet sie nur.
  - Er bezieht auch gelöschte Datensätze ein und lässt sich wiederholen.
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
- **Ausgaben auf der Konsole** laufen durch `ConsoleText::escape()`: Titel sind Redakteursdaten und dürfen weder Konsolen-Formatierung noch Steuerzeichen (ANSI) ins Terminal tragen.
- **Testdaten:** Der CSV-Import des Testing-Frameworks füllt fehlende Spalten mit TCA-Defaults (z. B. `file_permissions` = alle Dateioperationen). Rechtefelder in Fixtures daher immer ausdrücklich setzen.

---

## 10. Wo was liegt

| Bereich | Dateien |
|---|---|
| Typen | `Classes/Domain/Kind/` (`GroupKind`, `KindRegistry`, `KindDefinition`, `KindFieldResolver`) |
| Regeln | `Classes/DataHandling/GroupKindRules.php`, `RelationList.php`, `RuleViolationReporter.php`, registriert in `ext_localconf.php` |
| Formular | `Configuration/TCA/Overrides/be_groups.php` und `be_users.php`, `Classes/Form/FormDataProvider/KindSelection.php` |
| Modul | `Configuration/Backend/Modules.php`, `Classes/Controller/OverviewController.php`, `Classes/Domain/Overview/`, `Resources/Private/Templates/Overview/Index.fluid.html` |
| Migration | `Classes/Upgrades/` (`KindMigration`, `ColumnInspector`, `ColumnInfo`) |
| Befehle | `Classes/Command/` (`AuditCommand`, `ClassifyCommand`, `SplitCommand`), Konsolenausgabe `Classes/Utility/ConsoleTextUtility.php` |
| Konsistenzprüfung | `Classes/Domain/Audit/` (`PermissionAudit`; öffentliche API: `AuditFinding`, `AuditSeverity`), `Classes/Event/AfterAuditFindingsCollectedEvent.php` (öffentliche API) |
| Assistenten | `Classes/Domain/Classification/`: `GroupClassifier` schlägt vor, `GroupConverter` setzt um und prüft nach, `GroupPermissionResolver` modelliert die Core-Auflösung |
| Events (öffentliche API) | `Classes/Event/` (`AfterAuditFindingsCollectedEvent`, `ModifyKindOfNewGroupEvent`) |
| Datenzugriff | `Classes/Domain/Repository/` (nur lesend; geschrieben wird über den DataHandler bzw. im Wizard per SQL) |
| Labels | `Resources/Private/Language/` (`db.xlf`, `messages.xlf`, `Modules/overview.xlf`, jeweils mit `de.`) |
| Tests | `Tests/Unit/`, `Tests/Functional/` (Fixtures in `Fixtures/`, Test-Extensions in `Fixtures/Extensions/`) |
| Tooling | `Build/` (runTests.sh, PHPStan, php-cs-fixer, Rector, PHPUnit, Integritätsprüfungen), `.github/workflows/ci.yml` |
| Doku | `Documentation/` (EN) und `Documentation/Localization.de_DE/` (DE) |
| Planung | `RELAUNCH.md` (Konzept, Entscheidungen E1–E11, Roadmap), `STATUS.md` (dieses Dokument) |

---

## 11. Arbeitsweise

- **Regeln:** Es gelten die Coding-Leitlinien und die Definition of Done (`RELAUNCH.md` §8.2).
- **Fehler:** Jeder Fehler wird zuerst mit einem Test reproduziert, der mit dem alten Code fehlschlägt.
- **Vor jedem Commit:** `composer check:static` sowie die Unit- und Functional-Tests ausführen; bei Änderungen an Datenbankzugriffen zusätzlich MariaDB und PostgreSQL per `runTests.sh`.
- **Commit-Nachrichten** nach `CODING_GUIDELINES.md` §17: `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` oder `[SECURITY]`, auf Englisch, mit einem Rumpf, der das *Warum* erklärt.
- **Sprachen:** Alles, was Menschen lesen, entsteht auf Deutsch und Englisch; beide Fassungen werden im selben Commit aktualisiert.
- **Dieses Dokument** wird am Ende jeder Arbeitssitzung aktualisiert.
