# be_groups – Relaunch für TYPO3 14

Konzept und Umsetzungsplan · Stand: 2026-10-06 · Zielversion: TYPO3 14.3 LTS

---

## 1. Ziel und Leitidee

**Das alte Ziel (2012, Michael Klapper / AOE):** Große Installationen mit vielen Backend-Gruppen beherrschbar machen.
Jede Gruppe hat genau *eine* Aufgabe (Baustein). Rollen setzen sich aus Bausteinen zusammen. Benutzer bekommen nur Rollen.

**Die Lage 2026:** Die offizielle TYPO3-Dokumentation empfiehlt genau dieses Modell
(System-, ACL- und Rollen-Gruppen mit Präfixen `R_`, `PG_`, `DBM_`, `FM_`, `FO_`, `CM_`, `ACL_`, `L_`).
Sie stellt aber selbst fest:

> „TYPO3 currently lacks the feature to categorize backend user groups by context or purpose“
> – [TYPO3 Explained: Setting up backend user groups](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Administration/PermissionsManagement/SettingUpBackendGroups/Index.html)

Der Core von TYPO3 14 hat diese Lücke weiterhin. Keine andere Extension schließt sie.
Die Tools für „Rechte als Code“ (z. B. `b13/permission-sets`) lösen das Ausrollen, aber nicht die Typisierung und Führung im Backend.

**Leitbild des Relaunch:**
> *be_groups macht die offizielle TYPO3-Rechteempfehlung zu echten Gruppentypen – mit geführter Oberfläche statt Namenskonvention.*

### Drei Prinzipien

1. **Core-nativ.**
   - Rechte liegen ausschließlich in Core-Feldern.
   - Die Rollen-Zusammensetzung ist das Core-Feld `subgroup` und die einzige Datenquelle.
   - Die Extension speichert genau ein eigenes Feld: den Typ.
   - Die Rechteauswertung bleibt beim Core (`GroupResolver`).
2. **Was nicht sichtbar ist, wirkt nicht.** Der Core wertet *alle* Rechtefelder einer Gruppe aus, unabhängig vom Typ (geprüft in `BackendUserAuthentication::fetchGroupData()`, v14.3.7). Ein Baustein darf deshalb nur die Felder befüllt haben, die sein Typ anzeigt.
3. **Führen statt brechen.**
   - Bestehende Gruppen funktionieren nach der Installation unverändert (Typ „Klassisch“).
   - Assistenten und eine Konsistenzprüfung führen schrittweise zum sauberen Modell.

### Nicht-Ziele

- Kein eigenes Rechtesystem und keine eigene Rechteauswertung.
- Kein „Rechte als Code“. Dafür gibt es `b13/permission-sets` und `uandi/ui-permissions`, die Extension bleibt mit ihnen kompatibel.
- Keine Verwaltung von Seitenrechten (`perms_*`). Das bleibt im Core-Modul „Permissions“.

---

## 2. Gruppentypen

Die Typen sind an der offiziellen Doku ausgerichtet und um TSconfig und Workspace ergänzt (beides gab es schon in der alten Extension).

| Kennung | Name | Präfix (Doku) | Eigene Felder | Alt (0.0.x) |
|---|---|---|---|---|
| `role` | Rolle | `R_` | nur `subgroup` (nur Bausteine) und Beschreibung | 3 META |
| `acl` | Zugriffsrechte | `ACL_` | `groupMods`, Tabellenrechte (`tables_modify`/`tables_select`), `non_exclude_fields`, `explicit_allowdeny`, `pagetypes_select`, `custom_options`, `mfa_providers`, `availableWidgets`* | 1 Rights |
| `page_group` | Seitenrechte-Gruppe | `PG_` | keine (dient als Eigentümergruppe für Seitenrechte) | 4 |
| `db_mount` | Seitenbaum-Freigabe | `DBM_` | `db_mountpoints` | 6 |
| `file_mount` | Dateifreigabe | `FM_` | `file_mountpoints` | 5 |
| `file_operations` | Dateioperationen | `FO_` | `file_permissions` | **neu** (war in 1 und 5) |
| `category_mount` | Kategorie-Freigabe | `CM_` | `category_perms` | 9 |
| `language` | Sprachen | `L_` | `allowed_languages` | 2 |
| `tsconfig` | TSconfig | `TS_` | `TSconfig`, `tsconfig_includes` (neu in v14.2) | 7 |
| `workspace` | Workspace | `WS_` | `workspace_perms` (nur mit EXT:workspaces) | 8 |
| `classic` | Klassisch | – | alle Felder im Core-Layout | 0 „Default all“ |

\* `availableWidgets` nur mit EXT:dashboard.

Alle Typen haben außerdem Titel, Beschreibung und „Deaktiviert“.

---

## 3. Datenmodell

- **Ein Feld:** `be_groups.tx_begroups_kind`, `varchar(32)`, Default `classic`, mit Index.
  - **Warum ein String?** Die gruppierte Bausteinauswahl nutzt `foreign_table_item_group` (v13, #95808). Der Core verlangt dafür einen String. Mit dem alten Integer-Feld wirft v14 einen `TypeError` in `SelectItem` (im Spike nachgewiesen).
- **Keine Zusatzspalten.** Die alten `subgroup_*`-Spalten entfallen.
  - `subgroup` ist die einzige Datenquelle.
  - Der Sync-Hook entfällt und damit auch der Datenverlust-Fehler der alten Version.
- **Typen sind normale TCA-Typen:**
  - `ctrl.type = tx_begroups_kind` mit `types[<kind>]` pro Typ und `typeicon_classes`.
  - Typ-spezifischer Datensatztitel, z. B. „Neue Rolle“ (v14, #108027).
  - Die Core-Paletten (`permissionGeneral`, `permissionSpecific`, `permissionLanguages`, `authentication`) werden wiederverwendet. Neue Core-Oberflächen kommen damit automatisch mit (z. B. die kombinierte Tabellenrechte-Ansicht aus v13.3).

---

## 4. Oberfläche im Backend

### 4.1 Gruppenformular
- Der Typ wird zuerst gewählt. Ein Typwechsel lädt das Formular neu, dann sind nur die Felder des Typs sichtbar.
- Jeder Typ hat eigenes Icon, eigenen Titel und eigene Hilfetexte.

### 4.2 Rollen zusammenstellen (Kernfunktion)
Die Rolle nutzt das Core-Feld `subgroup` mit `columnsOverrides`:
- `renderType` ist `selectCheckBox`.
- Die Bausteine sind **nach Typ gruppiert** (`foreign_table_item_group` und `itemGroups`). Jede Gruppe bringt „Alle“, „Keine“ und „Umkehren“ mit.
- Rollen und klassische Gruppen sind nicht auswählbar (`foreign_table_where`).

Das Ergebnis ist im Spike gerendert, gegen v14.3.7.

Für sehr viele Bausteine lässt sich das Feld auf `selectMultipleSideBySide` umstellen (Optgroups mit Filter, ebenfalls Core).

### 4.3 Benutzer
- `be_users.usergroup` zeigt nur Rollen. Klassische Gruppen erscheinen nur, solange sie erlaubt sind.
- Beides ist in der Auswahl gruppiert.

### 4.4 Listen und Bezeichnungen
- Ein Icon pro Typ, sortiert nach Typ und Titel.
- Präfixe (`R_`, `ACL_` …) werden optional automatisch angezeigt. Sie müssen nicht mehr im Gruppennamen gepflegt werden.

### 4.5 Übersichtsmodul „Rollen & Bausteine“ (M2)
- **Matrix:** Rollen als Zeilen, Bausteine nach Typ gruppiert als Spalten, Haken per Klick.
- **Verwendungsnachweis:** In welchen Rollen steckt ein Baustein? Welche Benutzer haben eine Rolle?
- **Effektive Rechte:** Verlinkung auf die Core-Detailansicht für Gruppen (v14, #99065).
- **Schreiben:** ausschließlich über den DataHandler. Sudo-Mode, Historie und Log greifen damit wie im Core. Für AJAX existiert ein Core-`sudo-mode-interceptor`.

### 4.6 Integration in das Core-Modul „Users“ (M2, zu prüfen)
Ein Typ-Filter für die Gruppenliste über `AfterBackendGroupListConstraintsAssembledFromDemandEvent`.

---

## 5. Regeln und Konsistenz

| Regel | Inhalt |
|---|---|
| R1 | Ein Baustein vergibt nur, was sein Typ anzeigt. Beim Typwechsel werden fremde Felder geleert, und eine Meldung nennt sie. Optional (strikt): die Prüfung läuft bei jedem Speichern. |
| R2 | Eine Rolle enthält nur Bausteine und hat selbst keine Rechtefelder (folgt aus R1). |
| R3 | Bausteine haben keine Untergruppen. |
| R4 | Benutzer bekommen nur Rollen (abschaltbar, z. B. während der Migration). |

**Umsetzung:**
- Ein DataHandler-Hook für `be_groups` und `be_users` setzt die Regeln durch. In v14 gibt es für das Speichern weiterhin keine PSR-14-Events, Hooks sind dort die API.
- **Tolerant:** Der Hook korrigiert und protokolliert, statt Exceptions zu werfen. So brechen Sync-Tools nicht.
- **Sudo-Mode:** Die Core-Prüfung läuft in `processDatamap_postProcessFieldArray` auf dem finalen Feldarray. Geleerte, geschützte Felder lösen sie also aus, und das ist gewollt.

**Konsistenzprüfung:**
- CLI-Befehl `begroups:audit` mit Exit-Code für CI und Monitoring, optional als Status im Reports-Modul.
- Sie findet Verstöße, die per SQL oder durch Sync-Tools entstanden sind, und Felder anderer Extensions, die keinem Typ zugeordnet sind.

---

## 6. Erweiterbarkeit

- **Typen sind TCA.** Eigene Typen legt man mit der normalen TCA-API an (Item in `tx_begroups_kind` und `types[...]`). Eine eigene Registry-API gibt es nicht.
- **Felder anderer Extensions** werden mit `addToAllTCAtypes('be_groups', 'feld', 'acl')` einem Typ zugeordnet.
- **Ein Listener auf `AfterTcaCompilationEvent`** (in v14 vorhanden) leitet daraus ab. Er feuert nach der TCA-Migration, liefert deshalb nur das finale v14-Format und nutzt keine `TcaSchemaFactory` (`CODING_GUIDELINES.md` §5.2):
  - die `itemGroups` der Rollen-Auswahl aus den registrierten Typen,
  - den Filter für `be_users`,
  - die Liste nicht zugeordneter Felder. Diese erscheinen in „Klassisch“, die Konsistenzprüfung warnt.
- **Mitgelieferte Zuordnungen** für bekannte Extensions: dashboard, workspaces, permission-sets (Feldname noch zu prüfen).
- **Für die Konsistenzprüfung** werden die Felder pro Typ über die Schema-API ermittelt (`TcaSchema::getSubSchema()`).

---

## 7. Migration

### 7.1 Von be_groups 0.0.x bzw. AOE 1.x
Ein Upgrade-Wizard nutzt das neue Core-Attribut `TYPO3\CMS\Core\Attribute\UpgradeWizard` (v14) und
- bildet die alten Typen 0–9 auf die Kennungen aus Abschnitt 2 ab,
- **repariert die Rollen:** `subgroup` wird zur Vereinigung aus `subgroup` und allen `subgroup_*`. Das heilt Gruppen, die der alte Fehler geleert hat, und Abweichungen werden berichtet,
- **zieht `file_permissions` heraus:** Alte Rights- und Dateifreigabe-Gruppen mit `file_permissions` bekommen einen neuen `FO_`-Baustein, der automatisch in die betroffenen Rollen eingehängt wird,
- überlässt das Entfernen der `subgroup_*`-Spalten danach dem DB-Compare.

### 7.2 Von einem normalen TYPO3 (Hauptzielgruppe)
- **Nach der Installation:** Alle Gruppen sind `classic`, nichts ändert sich.
- **Klassifizierungs-Assistent** (`begroups:classify`, mit `--dry-run`): schlägt den Typ anhand der befüllten Felder vor (z. B. nur DB-Mounts ergibt `db_mount`).
- **Aufteilungs-Assistent:**
  - Er zerlegt gemischte Gruppen in Bausteine und eine Rolle.
  - **Die ursprüngliche uid bleibt die Rolle**, Benutzerzuweisungen bleiben also unangetastet.
- **Optionales Starter-Set:** die beiden Core-Standardgruppen (`setup:begroups:default`, „Editor“ und „Advanced Editor“) als Rolle mit Bausteinen.

---

## 8. Qualitätsstandard

**Anspruch:** be_groups soll die Extension sein, an der man sieht, wie TYPO3-Extensions gebaut werden.

**Leitlinie: Wir bauen so, wie der Core gebaut wird.**
- Der Core 14.3 setzt auf `Build/Scripts/runTests.sh` in Containern, auf PHPStan, php-cs-fixer, Playwright, TypeScript, Lit, ESLint und Stylelint und auf XLIFF-Integritätsprüfungen.
- be_groups übernimmt diese Struktur und diese Konventionen. Tooling und CI richten sich nach der offiziellen Best-Practice-Extension „tea“, weil sie auf Extensions zugeschnitten ist.
- **Die verbindlichen Regeln stehen in [`CODING_GUIDELINES.md`](CODING_GUIDELINES.md) (Englisch) und [`CODING_GUIDELINES.de.md`](CODING_GUIDELINES.de.md) (Deutsch).** Sie verbinden die offiziellen Coding Guidelines, die Muster im Core v14.3.7 und das Tooling von tea.
- Wer den Core kennt, findet sich sofort zurecht. Wer be_groups kennt, lernt den Core.

### 8.1 Messbare Qualitätsziele

Jedes Ziel wird in CI geprüft. Ein Pull Request ohne grüne Pipeline wird nicht gemergt.

| Bereich | Ziel | Prüfung |
|---|---|---|
| Codestil | 0 Abweichungen, einheitliche Dateiköpfe | `typo3/coding-standards` (php-cs-fixer) |
| Statische Analyse | höchste Stufe, **ohne Baseline** | PHPStan 2.x auf Level max, `saschaegerer/phpstan-typo3` 3.x, strict- und deprecation-rules |
| API-Hygiene | nur öffentliche Core-API, kein `@internal` (z. B. ist `GroupResolver` intern) | PHPStan `featureToggles.internalTag: true` und Review |
| Zukunftssicherheit | 0 Deprecations, 0 Treffer im Extension Scanner, Rector ohne Änderungen | Tests mit Deprecations als Fehler, `ssch/typo3-rector` im Prüfmodus, CI-Job gegen v15-dev (darf fehlschlagen) |
| Tests | Unit, Functional und E2E für alle Regeln, Wizards und Assistenten | PHPUnit 11.5 über `typo3/testing-framework` 9.7 mit `failOnAllIssues` und CSV-Fixtures, Playwright wie im Core, JavaScript-Tests mit web-test-runner |
| Datenbanken | läuft auf allen Core-Datenbanken | Functional Tests gegen SQLite, MariaDB, MySQL und PostgreSQL |
| Testabdeckung | ≥ 90 % Zeilen in `Classes/`; Mutation Score ≥ 80 % für die Regel-Engine | pcov-Bericht, Infection |
| Frontend | TypeScript strict, Lit-Webkomponenten, ES-Module über `JavaScriptModules.php`, **kein Inline-JS/CSS** (CSP-konform) | ESLint 9, Stylelint, CI-Prüfung „Build aktuell“ |
| Design | nur Backend-Komponenten und CSS-Variablen des Core; korrekt in den v14-Themes Fresh, Modern und Classic, jeweils hell und dunkel; RTL-tauglich | Playwright-Screenshots pro Theme und Farbschema |
| Barrierefreiheit | WCAG 2.2 AA für die eigene Oberfläche: Matrix komplett per Tastatur bedienbar, ARIA-Grid, Fokusführung, Kontraste | axe in Playwright und manueller Screenreader-Test vor jedem Release |
| Sicherheit | Schreiben nur über den DataHandler (Rechte, Sudo-Mode, Historie, Log); AJAX-Routen mit CSRF-Token; QueryBuilder mit Parametern; Modulzugriff über `Modules.php` | Security-Checkliste pro Release, Tests für Rechte und Sudo-Mode |
| Performance | flüssig bei 1.000 Gruppen und 5.000 Benutzern, keine N+1-Abfragen, TCA-Ableitung zur Compile-Zeit | Benchmark-Fixture in CI, Query-Zählung in den Tests |
| Übersetzung | keine fest eingebauten Texte; Englisch als Quelle, Deutsch mitgeliefert, weitere Sprachen über Crowdin | XLIFF-Lint, Integritätsprüfung wie im Core |
| Dokumentation | offizielle Doku auf docs.typo3.org | render-guides (`guides.xml`) in CI mit Warnungen als Fehler |
| Upgrade-Wizards | idempotent, Voraussetzungen deklariert, Probelauf mit Bericht | Functional Tests mit Fixtures aus 0.0.9 und AOE 1.2.2 |
| Erweiterbarkeit | eigene PSR-14-Events an den Erweiterungspunkten (Typ-Zuordnung, Klassifizierung, Audit-Befunde), klar markierte öffentliche API | Tests und Entwickler-Doku pro Event |

### 8.2 Definition of Done (pro Feature bzw. Pull Request)

- [ ] Alle Qualitätsziele aus 8.1 grün, keine neuen Baseline-Einträge.
- [ ] Tests decken das Verhalten ab, inklusive Fehlerfälle und Schreibzugriffe per API.
- [ ] Doku aktualisiert: Benutzer- und Entwickler-Doku, Screenshots bei UI-Änderungen.
- [ ] Alle Labels übersetzbar, Deutsch gepflegt.
- [ ] Bei UI: Tastaturbedienung, alle Themes hell/dunkel und axe geprüft.
- [ ] Bei Schreibzugriffen: DataHandler genutzt, Sudo-Mode und Rechte getestet.
- [ ] Changelog-Eintrag, Commit-Präfix wie im Core (`[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]`, `[SECURITY]`).
- [ ] Review durch eine zweite Person.

### 8.3 Projekt-Infrastruktur

- **Metadaten:**
  - nur `composer.json`: `description` nach dem Muster „Titel - Beschreibung“, Version in `extra.typo3/cms.version` (v14.2, #108345), `extra.typo3/cms.Package.providesPackages: {}`, kein `replace typo3-ter/*`,
  - `ext_emconf.php`, `ext_tables.php` und `ext_icon.png` entfallen, dafür `Resources/Public/Icons/Extension.svg`.
- **Plattform:** TYPO3 `^14.3` (LTS, Community-Support bis 06/2029), PHP 8.2–8.5.
- **Code:**
  - `declare(strict_types=1)`, `final readonly`-Klassen, Enums und Attribute (`#[AsEventListener]`, `#[AsCommand]`),
  - Dependency Injection über `Services.yaml`, PSR-3-Logging,
  - Translation Domains (v14).
- **Repository:**
  - README zweisprachig mit Badges (CI, Testabdeckung, TER, Packagist, Doku), `CHANGELOG.md` und `Documentation/Changelog`,
  - Doku zweisprachig: Englisch als Hauptfassung, Deutsch als Übersetzung. Ob `render-guides` übersetzte Handbücher unterstützt, ist in M0 zu prüfen. Sonst wird die deutsche Doku als eigener Bereich gerendert.
  - `CONTRIBUTING.md` und `CONTRIBUTING.de.md`, Code of Conduct (TYPO3), `SECURITY.md` mit Meldeweg über das TYPO3 Security Team,
  - Issue- und PR-Vorlagen, Renovate, geschützter `main`-Branch mit Pflichtprüfungen, signierte Tags.
- **Release:** Semantic Versioning, Tag → GitHub Action mit `typo3/tailor` 2.x ins TER, Packagist automatisch.
- **Lizenz:** `GPL-2.0-or-later` (Entscheidung E6), `LICENSE.txt` mit dem GPL-2.0-Text und ein Lizenzkopf in jeder PHP-Datei, gesetzt über `typo3/coding-standards` (`CODING_GUIDELINES.md` §3.1).

### 8.4 Verzeichnisstruktur (Ziel)

```
Build/                      # Scripts/runTests.sh (nach tea), php-cs-fixer/, phpstan/, phpunit/, tests/playwright/, Sources/ (TypeScript, SCSS)
Classes/
  Command/                  # begroups:audit, begroups:classify, begroups:split
  Controller/               # Backend-Modul „Rollen & Bausteine“ und AJAX-Endpunkte
  DataHandling/             # Regel-Engine (R1–R4) als DataHandler-Hook
  Domain/                   # Typen, Klassifizierung, Aufteilung (frameworkfrei, gut testbar)
  Event/                    # eigene PSR-14-Events (öffentliche API)
  EventListener/            # u. a. AfterTcaCompilationEvent
  Exception/                # eigene Exceptions (Basis: \TYPO3\CMS\Core\Exception)
  Upgrades/                 # Migration von 0.0.x und AOE 1.x
Configuration/
  Backend/Modules.php (inkl. AJAX-Routen)  Icons.php  JavaScriptModules.php  Services.yaml
  TCA/Overrides/be_groups.php  TCA/Overrides/be_users.php
Documentation/              # guides.xml, Kapitel siehe 8.1
Resources/Private/{Language,Templates,Layouts,Partials}  Resources/Public/{Icons,JavaScript,Css}
Tests/{Unit,Functional}     # E2E mit Playwright unter Build/tests/playwright/ wie im Core
```

### 8.5 Würdigung des Erfinders (verbindlich)

be_groups geht auf **Michael Klapper** zurück. Er hat die Idee 2012 bei morphodo/AOE entwickelt und umgesetzt (Typen, META-Rollen, Upgrade-Wizards). Der Relaunch erfolgt mit seiner ausdrücklichen Zustimmung. Das muss überall sichtbar sein, wo die Extension sich vorstellt:

- **Doku-Startseite:** ein kurzer Hinweis „Ursprünglich entwickelt von Michael Klapper (2012)“ mit Link auf die Credits-Seite.
- **Eigene Doku-Seite „Credits & Geschichte“:**
  - die Idee und ihr Erfinder,
  - die Zeitleiste: 2012 Entwicklung bei morphodo/AOE, Pflege durch AOE für TYPO3 6.2–8, 2022 Reaktivierung für TYPO3 11, 2026 Relaunch für TYPO3 14 mit Zustimmung des Erfinders,
  - die weiteren Beteiligten aus der Git-Historie: Tomas Norre Mikkelsen, Martin Tepper, Christian Zenker, Dragan Tomic, Stefan Rotsch und Jonathan Klauck,
  - der Hinweis, dass das Konzept von 2012 genau das ist, was die offizielle TYPO3-Doku heute empfiehlt.
- **README:** Abschnitt „Credits“ mit demselben Kern.
- **`composer.json`:** Michael Klapper unter `authors` mit Rolle „Original author“. Name, Link und E-Mail nur so, wie er es freigibt.
- **TER-Beschreibung:** ein Satz zur Herkunft.
- **Git-Historie bleibt erhalten:** Der Relaunch entsteht auf einem Branch im bestehenden Repository, ohne Squash und ohne Neuanlage. So bleiben seine Commits aus 2012 nachvollziehbar.

**Vorab mit ihm klären:** gewünschte Namensnennung (mit oder ohne Firma), Link, E-Mail-Adresse und ob er die Credits-Seite vor dem Release gegenlesen möchte.

---

## 9. Roadmap und Aufwand

Version 1.0.0 enthält alle Phasen (Entscheidung E2). Die Meilensteine erscheinen vorab als Vorabversionen auf Packagist, damit früh getestet werden kann.

Der Qualitätsstandard aus Abschnitt 8 erhöht den Aufwand gegenüber der ersten Schätzung um etwa 50 %. Der Mehraufwand steckt vor allem in der Infrastruktur, in Playwright und Barrierefreiheit, in Themes und dark mode, in der Doku und in der Release-Härtung.

| Meilenstein | Inhalt | Ergebnis | Aufwand (Schätzung) |
|---|---|---|---|
| M0 Fundament | Branch `relaunch`, Altlasten raus, komplette Qualitäts-Infrastruktur (8.1/8.3), Doku-Gerüst mit Credits-Seite (8.5) | alle Prüfungen grün auf leerem Gerüst | 2–3 PT |
| M1 Kern | Typen und TCA, gruppierte Rollen-Auswahl, Benutzerfilter, Regeln R1–R4, Upgrade-Wizard, Events | `1.0.0-alpha1` | 5–7 PT |
| M2 Übersicht | Modul „Rollen & Bausteine“ (TypeScript/Lit, barrierefrei, Themes), `begroups:audit`, Typ-Filter, Präfixe | `1.0.0-beta1` | 6–9 PT |
| M3 Umstieg | Klassifizierungs- und Aufteilungs-Assistent, Starter-Set | `1.0.0-beta2` | 4–6 PT |
| M4 Härtung | Security-Review, Performance-Benchmark, Prüfung der Barrierefreiheit, Doku und Screenshots final, Credits mit dem Erfinder abgestimmt, Übersetzungen | `1.0.0-rc1` → **1.0.0** im TER | 2–3 PT |

Gesamt etwa 19–28 Personentage.

---

## 10. Risiken und offene Prüfpunkte

- **Viele Bausteine:** Die Bedienbarkeit von `selectCheckBox` bei hunderten Bausteinen muss geprüft werden. Fallback ist `selectMultipleSideBySide`.
- **Sync-Tools:** Das Zusammenspiel mit Tools, die `be_groups` per SQL schreiben, abfangen über tolerante Hooks und die Konsistenzprüfung.
- **Core-Entwicklung:** Rechte-Vorlagen im Core (Gerrit 85577) sind noch nicht gelandet. Sie wären ergänzend, Typisierung decken sie nicht ab. Beobachten.
- **Sudo-Mode:** Den AJAX-Ablauf im Übersichtsmodul im Detail prüfen.
- **TER:** Upload ohne `ext_emconf.php` mit tailor 2.x prüfen.
- **Seitenrechte:** Ob sich die Eigentümergruppe im Permissions-Modul auf `page_group` filtern lässt, ist offen (Backlog).

---

## 11. Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| E1 | Extension-Key und Name | ✅ Key `be_groups` und Paket `cretection/be-groups` bleiben, mit sprechendem Titel (2026-10-06) |
| E2 | Umfang von 1.0 | ✅ alles, M0–M3 inklusive Übersichtsmodul und Assistenten, Vorabversionen pro Meilenstein (2026-10-06) |
| E3 | Typenliste | ✅ an der Doku ausgerichtet, 11 Typen inklusive `file_operations` (2026-10-06) |
| E4 | Typ „Klassisch“ | ✅ erlaubt und über die Extension-Konfiguration abschaltbar (Standard: an). Bei „aus“ gibt es keine neuen klassischen Gruppen, Benutzer bekommen nur Rollen angeboten und die Konsistenzprüfung meldet einen Fehler. Bestehende Gruppen wirken weiter, und es werden keine Rechte gelöscht. Doku und Schnellstart empfehlen, die Option nach der Umstellung abzuschalten (2026-10-07). |
| E5 | Version | Empfehlung: 1.0.0 |
| E6 | Lizenz | ✅ `GPL-2.0-or-later` (2026-10-07). Die TYPO3-Doku für `composer.json` schreibt vor: „Has to be `GPL-2.0-only` or `GPL-2.0-or-later`.“ Das Original von AOE stand unter GPL-2.0+. Der Fork war 2022 auf GPL-3.0+ gestellt worden. |
| E7 | Qualitätsstandard | ✅ höchster TYPO3-Standard, verbindlich laut Abschnitt 8 (2026-10-06) |
| E8 | Würdigung des Erfinders | ✅ Michael Klapper wird in Doku, README, composer.json und TER genannt, mit eigener Credits-Seite (Abschnitt 8.5, 2026-10-06). Er hat dem Relaunch zugestimmt (bestätigt 2026-10-07). |
| E9 | Coding-Leitlinien | ✅ verbindlich laut `CODING_GUIDELINES.md` (Englisch) und `CODING_GUIDELINES.de.md` (Deutsch) (2026-10-06) |
| E10 | Sprachen | ✅ Deutsch und Englisch für alles, was Menschen lesen: README, Leitlinien, `CONTRIBUTING`, Doku und Labels. Bei Widersprüchen gilt die englische Fassung, Code und Commits sind Englisch (2026-10-07). |

---

## 12. Offene Punkte (Stand 2026-10-07)

**Für den Start von M0 nötig:**
- **(a) Titel der Extension.** Vorschlag: „Backend Group Kinds - Roles and building blocks for TYPO3 backend permissions“.
- **(b) Review-Ablauf.** Vorschlag: Claude setzt um und prüft per `/code-review`, Jonathan gibt jeden Pull Request frei, und bei Meilensteinen kommt ein Review aus der Community.

**Später zu entscheiden:**
- (c) Übersetzungen über die offizielle TYPO3-Lokalisierung statt des eigenen Crowdin-Projekts (bis M1).
- (d) E5, Version 1.0.0 (bis M1).
- (e) Alte Branches und den Crowdin-PR #3 nach dem Relaunch archivieren bzw. schließen (M4).

**Mit Michael Klapper abzustimmen:** Namensnennung, Link und E-Mail-Adresse, ob er die Credits gegenliest, optional ein Blick auf dieses Konzept.

**Zugänge, die Jonathan einrichtet:**
- GitHub: Branch-Schutz und Pflichtprüfungen (Ende M0).
- TER: Token als GitHub-Secret (M4).
- docs.typo3.org: Webhook (M1/M2).
- Packagist: Auto-Update-Hook prüfen.

**Klärt sich bei der Umsetzung:** siehe Abschnitt 10. Zusätzlich:
- `render-guides` mit Übersetzungen (M0),
- TER-Upload ohne `ext_emconf.php` (M0),
- Feldname von permission-sets (M1),
- `isImporting` pro Regel (M1),
- Inhalt des Starter-Sets (M3).

**Nächster Schritt:** Sobald (a) und (b) beantwortet sind, startet M0 auf dem Branch `relaunch`. Der erste Commit enthält das Planungsmaterial (`RELAUNCH.md`, `CODING_GUIDELINES*.md`).

---

## Anhang: Nachweise aus der Machbarkeitsprüfung (TYPO3 14.3.7, PHP 8.5)

- **Die alte Extension unverändert:**
  - Wegen `TYPO3_MODE` endet die ganze Instanz in „Access denied.“.
  - Wegen `FlashMessage::INFO` lässt sich keine Gruppe speichern.
- **Datenverlust:** Benennt man eine META-Gruppe per DataHandler ohne `subgroup_*`-Felder um, wird `subgroup` geleert.
- **Vererbung:** Der Core-`GroupResolver` vererbt korrekt über `subgroup`. Ein Benutzer mit nur einer Rolle erhält alle Bausteine.
- **Spike:** `subgroup` mit `selectCheckBox`, `foreign_table_item_group` und `itemGroups` rendert Abschnitte pro Typ mit „Check all / Uncheck all / Toggle selection“. Mit einem Integer-Typfeld gibt es einen `TypeError`.
- **Auswertung:** Der Core wertet alle Rechtefelder unabhängig vom Typ aus (`fetchGroupData()`).
- **Sudo-Mode:** Die Prüfung läuft auf dem finalen Feldarray in `DataHandlerAuthenticationContext::processDatamap_postProcessFieldArray`.
