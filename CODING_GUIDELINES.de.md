# Coding-Leitlinien für be_groups

🇬🇧 [English version](CODING_GUIDELINES.md) · 🇩🇪 Deutsch

Verbindlich für allen Code ab dem Relaunch (TYPO3 14.3 LTS). Stand: 2026-10-07.

> Beide Sprachfassungen sind gleichwertig und werden im selben Pull Request aktualisiert. Bei Widersprüchen gilt die englische Fassung, weil sie für alle Mitwirkenden lesbar ist.

## 0. Geltung und Quellen

Die Leitlinie stützt sich auf drei Quellen:

| Kürzel | Quelle |
|---|---|
| **[CGL]** | Offizielle TYPO3 Coding Guidelines und Extension Best Practices aus *TYPO3 Explained* (main, entspricht v14), `typo3/coding-standards` v0.9.0 |
| **[Core]** | Muster, die der Code von TYPO3 v14.3.7 tatsächlich verwendet (gezählt per grep über `vendor/typo3/*`) |
| **[tea]** | `TYPO3BestPractices/tea`, die offizielle Best-Practice-Beispiel-Extension (Tooling, CI) |
| **[Projekt]** | Projektentscheidung, siehe `RELAUNCH.md` |

**MUSS** / **DARF NICHT** sind verbindlich und werden, wo möglich, in CI geprüft. **SOLL** / **SOLL NICHT** gelten, außer es gibt einen begründeten Ausnahmefall; die Begründung gehört in den Pull Request.

**Rangfolge bei Konflikten:**
1. Was ein Werkzeug automatisch erzwingt (php-cs-fixer, PHPStan …),
2. dann [CGL],
3. dann [Core],
4. dann [Projekt].

Wo die offizielle Doku veraltet ist (z. B. „PER-CS 1.0/2.0“ statt `@PER-CS3x0`), gilt der Stand von Core und Werkzeug.

---

## 1. Grundsätze

1. **Wir schreiben Code, der aussieht wie der Core von TYPO3 14.** Wer den Core kennt, findet sich sofort zurecht. [Projekt]
2. **MUSS:**
   - **Nur öffentliche Core-API.** Alles, was im Core mit `@internal` markiert ist, ist tabu (z. B. `GroupResolver`, `TcaSchemaFactory::rebuild()`). PHPStan prüft das mit `featureToggles.internalTag: true`. [Projekt]
   - **0 Deprecations:** Tests schlagen bei jeder Deprecation fehl, auch bei veralteten Labels mit `x-unused-since`. [Core]
   - **Keine API, die absehbar wegfällt.** Erlaubt ist nur, was in TYPO3 14.3 **und** im aktuellen Entwicklungsstand der nächsten Hauptversion (Core `main`) weder `@deprecated` noch `@internal` ist. Vor der ersten Nutzung einer Core-API werden beide Stände und der Changelog von `main` geprüft. [Projekt]
   - **PHP 8.2 bis 8.5:** Der Code läuft auf der Mindestversion von TYPO3 14 und von TYPO3 15 und nutzt nichts, was PHP 8.5 als deprecated markiert. [Projekt]
   - **Rechte werden nur über den DataHandler geschrieben.** Niemals per SQL in `be_groups` oder `be_users` schreiben, nur so greifen Rechte, Sudo-Mode, Historie und Log. [Projekt]
3. **Klein und explizit:** lieber ein weiterer kleiner Service als eine Klasse, die alles kann. Keine Magie, keine globalen Zustände. [CGL]

---

## 2. Allgemeines zu Dateien

- **MUSS:** UTF-8 ohne BOM, LF-Zeilenenden, Leerzeile am Dateiende, keine Leerzeichen am Zeilenende. [CGL]
- **MUSS:** `.editorconfig` vom Core übernehmen: PHP mit 4 Leerzeichen; XLIFF, YAML, JSON, TypeScript, SCSS und Fluid mit 2 Leerzeichen. [CGL][Core]
- **MUSS:** Web-öffentlich ist nur `Resources/Public/`. [CGL]
- **DARF NICHT ausgeliefert werden:** `ext_emconf.php` (deprecated seit 14.2, wird in v15 nicht mehr gelesen), `ext_tables.php` (deprecated seit 14.3), `ext_icon.*`. Das Extension-Icon liegt unter `Resources/Public/Icons/Extension.svg`. [CGL]

---

## 3. PHP

### 3.1 Dateiaufbau (MUSS) [CGL][Core]

Die Reihenfolge ist immer:
1. `<?php`
2. `declare(strict_types=1);`
3. Lizenzkopf
4. `namespace`
5. `use`-Block
6. Klassen-Docblock
7. Attribute
8. Klasse

Pro Datei gibt es genau eine Klasse und keinen schließenden `?>`-Tag.

```php
<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Cretection\BeGroups\Domain\Kind;

use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Resolves which be_groups fields belong to a group kind.
 *
 * @internal
 */
final readonly class KindFieldResolver
{
    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}
}
```

- Der Kopf wird über `CsFixerConfig::setHeader('This file is part of the TYPO3 CMS extension "be_groups".')` gesetzt und von php-cs-fixer geprüft. Den Kopftext des Core („part of the TYPO3 CMS project“) übernehmen wir **nicht**. [CGL]
- Konfigurationsdateien (`Modules.php`, `JavaScriptModules.php`, `Icons.php`, Basis-TCA) haben keinen Lizenzkopf. `declare(strict_types=1)` tragen nur `ext_localconf.php`, `Services.php` und `TCA/Overrides/*`. [Core]
- `ext_localconf.php`: kein Namespace, kein `return`. `defined('TYPO3') or die();` steht nach den `use`-Anweisungen. Icons werden hier **nicht** registriert. [CGL]

### 3.2 Formatierung (MUSS, erzwungen durch php-cs-fixer) [CGL][Core]

Wir verwenden `typo3/coding-standards` **v0.9.0**. Das Regelwerk ist byte-identisch mit Core 14.3 (`@PER-CS3x0` plus TYPO3-Regeln). Die Konfiguration erzeugt `CsFixerConfig::create()`, eigene Regeln werden nicht ergänzt. Daraus folgt unter anderem:

- **Casts und Typen:** Casts ohne Leerzeichen (`(int)$value`), nullbare Typen als `?Type`.
- **Strings und Arrays:** einfache Anführungszeichen, kurze Array-Syntax, nachgestelltes Komma in mehrzeiligen Arrays, Parameterlisten und Argumentlisten.
- **Kontrollstrukturen:** keine Yoda-Bedingungen, `elseif` statt `else if`.
- **`use`-Anweisungen:** alphabetisch, nicht gruppiert, kein `use function`.
- **Globale Namen:**
  - Globale Klassen werden mit `\` referenziert und nicht importiert (`\RuntimeException`).
  - Native Funktionen ohne führenden Backslash (`sprintf(`).
- **Kommentare:** `//` statt `#`.

### 3.3 Benennung [CGL]

| Element | Regel |
|---|---|
| Klassen, Interfaces, Enums | UpperCamelCase; Interfaces enden auf `Interface`, Events auf `Event`, Listener liegen in `EventListener/` |
| Methoden, Variablen, Properties | lowerCamelCase, keine Unterstriche, keine Abkürzungen wie `BE`/`FE` in Bezeichnern |
| Konstanten | UPPER_SNAKE_CASE |
| Eigene Spalten an `be_groups` | `tx_begroups_<name>` (also `tx_begroups_kind`) |
| Upgrade-Wizard-ID | `beGroups_<wizardName>`, wird nach dem Release **nie** umbenannt (steht in `sys_registry`) |
| Listener-ID | `cretection/be-groups/<zweck>` |
| CLI-Befehle | `begroups:<aktion>` |
| Web-Komponenten | Präfix `be-groups-` (nicht `typo3-`) |

### 3.4 Typen und phpDoc [CGL][Core]

- **MUSS:**
  - Parameter, Rückgaben und Properties sind vollständig nativ typisiert.
  - Jeder Codepfad gibt einen Wert zurück.
- **phpDoc nur, wo native Typen nicht reichen:**
  - Array-Shapes und Listen (`list<string>`, `array<string, GroupKind>`),
  - Generics,
  - `@throws`, wenn der Aufrufer reagieren soll.
- Kein phpDoc, der nur die Signatur wiederholt. [CGL]
- Debug-Code (`var_dump`, `DebuggerUtility`, `print_r`) kommt nie in einen Commit. [CGL]

### 3.5 Klassen und Sprachmittel

- **Services** sind zustandslos und `final readonly class`, mit Promoted Constructor Properties (`private`). Der leere Rumpf ist `) {}`. [CGL][Core]
- **Value Objects** sind `final readonly`, werden mit `new` erzeugt, gern mit benannten Argumenten.
- **Enums** sind string-backed, für feste Zustände.
- `match` und First-Class-Callables (`$this->foo(...)`) sind erwünscht. [Core]
- **DARF NICHT:**
  - `SingletonInterface`, statischer Zustand,
  - Traits ohne zugehöriges Interface,
  - Utility-Klassen mit Zustand. Utility-Klassen sind nur statisch, liegen in `Utility/` und heißen `*Utility`. Möglichst keine. [CGL]
- **SOLL NICHT:** benannte Argumente beim Aufruf von **Core**-API. Die Parameternamen des Core sind nicht abwärtskompatibel geschützt. [CGL]
- **SOLL NICHT:** `GeneralUtility::makeInstance()` für eigene Services, stattdessen Konstruktor-Injection. Ausnahmen stehen in 4.2.
- **Kein Zugriff auf `$GLOBALS['TCA']` zur Laufzeit.** TCA wird über `TcaSchemaFactory->get()` gelesen, Fähigkeiten über `hasCapability(TcaSchemaCapability::…)`. [Core]

### 3.6 Öffentliche API von be_groups [Core][Projekt]

- Alles ist `@internal`, außer was bewusst öffentlich ist: eigene Events, dokumentierte Erweiterungspunkte und die Typ-Kennungen.
- `@api` verwenden wir nicht (der Core nutzt es 0×). Öffentliche API ist, was **nicht** `@internal` ist.
- Semantic Versioning gilt **nur** für die öffentliche API.

### 3.7 Exceptions [CGL][Core]

- **MUSS:**
  - Jede geworfene Exception hat als zweites Argument einen **10-stelligen Unix-Timestamp** (`date +%s` beim Schreiben) als Code.
  - Codes sind eindeutig und werden nie geändert. Ein aus dem Core übernommener Integritäts-Checker prüft das.
- `\RuntimeException` / `\InvalidArgumentException` für Programmierfehler.
- Eigene, leere Exception-Klassen in `Classes/Exception/` für Fälle, die Aufrufer gezielt behandeln sollen. Die Basis-Exception erweitert `\TYPO3\CMS\Core\Exception`.
- **In DataHandler-Hooks wird nicht geworfen:** Dort wird über `$dataHandler->BE_USER->writelog()` ins Systemprotokoll geschrieben (öffentliche API; `DataHandler::log()` ist `@internal`) und das Feld bzw. der Datensatz verworfen (siehe 6).

### 3.8 Eigene Deprecations [CGL]

Das passiert erst nach 1.0. Dann gilt immer beides:
- `@deprecated since 1.x, will be removed in 2.0`,
- `trigger_error('…', E_USER_DEPRECATED)`.

Ein Changelog-Eintrag ist Pflicht.

---

## 4. Architektur und Dependency Injection

### 4.1 `Configuration/Services.yaml` [Core]

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Cretection\BeGroups\:
    resource: '../Classes/*'
```

Listener, Commands, Controller und Upgrade-Wizards werden **nur per Attribut** registriert, nicht in YAML. [Core]

### 4.2 Sonderfälle [CGL][Core]

- **Per `makeInstance()` erzeugte Klassen** (DataHandler-Hooks, `itemsProcFunc`, `label_userFunc`, FormEngine-Nodes) bekommen `#[Autoconfigure(public: true)]`. Dann funktioniert Konstruktor-Injection auch für sie.
- **DARF NICHT:** den `DataHandler` injizieren. Er wird für jeden Vorgang neu per `GeneralUtility::makeInstance(DataHandler::class)` erzeugt.
- **Zustandsbehaftete Services** bekommen `shared: false`. Manuelle Konstruktorargumente und DI werden nie gemischt.
- **Logging:** `Psr\Log\LoggerInterface` per Konstruktor injizieren.
- **Datenbank:**
  - `ConnectionPool` injizieren und pro Abfrage einen eigenen QueryBuilder verwenden.
  - **Jeder** Wert läuft über `createNamedParameter($value, Connection::PARAM_INT|PARAM_STR|…)`, jeder Bezeichner aus Variablen über `quoteIdentifier()`.
  - SQL lebt in Repositories.

---

## 5. Events und Listener

### 5.1 Eigene Events [Core]

- Sie liegen in `Classes/Event/` und heißen `Before…Event`, `After…Event` oder `Modify…Event`. 147 von 188 Core-Events folgen diesem Schema.
- Unveränderliche Events sind `final readonly class`. Events mit Settern (`Modify…`) sind `final class`.
- Events sind öffentliche API, also **nicht** `@internal`. Jedes Event ist dokumentiert und getestet.
- Dispatch über ein injiziertes `Psr\EventDispatcher\EventDispatcherInterface`.

### 5.2 Listener [CGL][Core]

```php
#[AsEventListener('cretection/be-groups/derive-role-composition-tca')]
final readonly class DeriveRoleCompositionTca
{
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        // …
    }
}
```

- Sie liegen in `Classes/EventListener/` und behandeln je ein Event per `__invoke()`.
- **`AfterTcaCompilationEvent`** feuert *nach* der TCA-Migration:
  - Was dort ergänzt wird, MUSS bereits im finalen v14-Format sein.
  - Der Listener darf `TcaSchemaFactory` nicht verwenden, denn das Schema wird erst danach gebaut.
  - Was statisch geht, gehört in `TCA/Overrides`. [Core]

---

## 6. DataHandler-Hooks

- **Registrierung** in `ext_localconf.php` mit benanntem Schlüssel:
  ```php
  $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['be_groups']
      = \Cretection\BeGroups\DataHandling\GroupKindRules::class;
  ```
  Für das Speichern gibt es in v14 weiterhin keine PSR-14-Alternative. [Core]
- **Klassenform:** `final readonly class` mit `#[Autoconfigure(public: true)]`, wie in `DataHandlerAuthenticationContext`. Ein Hook, der während eines DataHandler-Laufs Zustand sammelt (z. B. Meldungen, die in `processDatamap_afterAllOperations` ausgegeben werden), ist eine `final class` mit `#[Autoconfigure(public: true, shared: false)]`, damit jeder Lauf eine eigene Instanz bekommt. Methoden sind voll typisiert. [Core][Projekt]
- Den aktuellen Benutzer über `$dataHandler->BE_USER` holen. `DataHandler::$admin` und `$userid` gibt es in v14 nicht mehr. [Core]
- **Tolerant:**
  - Regeln korrigieren und protokollieren (`$dataHandler->BE_USER->writelog()`), statt Exceptions zu werfen. So brechen Import- und Sync-Werkzeuge nicht.
  - Korrekturen werden in `processDatamap_afterAllOperations` gemeldet, damit ein abgebrochenes Speichern (z. B. abgebrochener Sudo-Mode) keinen Protokolleintrag hinterlässt.
  - Ob eine Regel bei `$dataHandler->isImporting` greift, wird je Regel entschieden, dokumentiert und getestet. [Projekt]
- **Sudo-Mode:** Der Core prüft geschützte Felder im finalen Feld-Array (`processDatamap_postProcessFieldArray`). Unsere Hooks laufen davor, und was sie setzen oder leeren, ist damit automatisch geschützt. Das ist gewollt. [Core]

---

## 7. TCA

- **MUSS:** Wir ändern nur fremde Tabellen, also alles in `Configuration/TCA/Overrides/be_groups.php` bzw. `be_users.php`, beginnend mit `defined('TYPO3') or die();`. [CGL]
- **Items** mit Schlüsseln (`'label' => …, 'value' => …, 'icon' => …, 'group' => …`), nie indexbasiert. [Core]
- **Labels** über Translation Domains: eigene als `be_groups.db:…`, Tabs und Paletten aus dem Core (`core.form.tabs:…`). Jedes Feld hat eine `description`. [Core]
- **Spalten** entstehen aus dem TCA. `ext_tables.sql` enthält nur, was TCA nicht erzeugen kann, z. B. den Index auf `tx_begroups_kind`. [CGL][Core]
- **Rechte-relevante Felder** bekommen wie im Core `'authenticationContext' => ['group' => 'be.userManagement']` (Sudo-Mode). Das gilt auch für `tx_begroups_kind`. [Core]
- **Typ-spezifischer Titel** über `types[<kind>]['title']` (v14). Kein `searchFields`; durchsuchbar wird ein Feld per `searchable` am Feld. [Core]
- **Ein Formular pro Typ:** Kein Formular blendet Rechte-Felder aus, die trotzdem wirken (Regel R1 in `RELAUNCH.md`).

---

## 8. Backend-Modul, Routen und Fluid

**Registrierung (`Configuration/Backend/Modules.php`):**
- `parent => 'admin'` (neben „Users“), `access`, `iconIdentifier`.
- `labels` als Domain, deren Datei `title`, `short_description` und `description` enthält.
- Zusätzliche `routes` für zustandsändernde Aktionen mit `'methods' => ['POST']`. AJAX-Routen ebenfalls hier mit `'ajax' => true`; `AjaxRoutes.php` brauchen wir nicht. [Core]

**Controller:**
- `#[AsController] final readonly class`, eine Aktion pro Route.
- Der Ablauf ist `ModuleTemplateFactory->create($request)` → `assignMultiple()` → `renderResponse('Roles/Index')`.
- Doc-Header-Buttons nur über `ComponentFactory` (`ButtonBar::make*` ist deprecated). `setShortcutContext()` wird gesetzt. [Core]

**Sicherheit:**
- Route-Tokens prüft der Core selbst.
- Eigene POST-Formulare nutzen zusätzlich FormProtection (`generateToken`/`validateToken`).
- Jede Aktion prüft die Rechte selbst.
- Schreibzugriffe laufen über den DataHandler, also mit Sudo-Mode. [CGL][Core]

**Fluid:**
- Templates heißen `*.fluid.html` (Fluid 5), nutzen `<f:layout name="Module"/>` und die `f:be.*`-ViewHelper.
- **DARF NICHT:** `f:format.raw` mit Benutzereingaben. [CGL][Core]

**Gute Vorlagen im Core:**
- `cms-reactions/Classes/Controller/ManagementController.php`
- `cms-recycler/Classes/Controller/RecyclerAjaxController.php`
- `cms-backend/Classes/Controller/SiteSettingsController.php` (Form-Token)

---

## 9. CLI-Befehle

- `#[AsCommand('begroups:audit', 'Checks …')]`, Ausgabe über `SymfonyStyle`, Rückgabe `Command::SUCCESS` / `Command::FAILURE`. [Core]
- **MUSS:** Jeder ändernde Befehl hat `--dry-run` und funktioniert nicht-interaktiv (`--no-interaction`).
- Vor der Nutzung des DataHandler wird `Bootstrap::initializeBackendAuthentication()` aufgerufen. [Core]
- **Vorlagen:**
  - `cms-backend/Classes/Command/CreateBackendUserCommand.php`
  - `cms-form/Classes/Command/CleanupFormUploadsCommand.php`

---

## 10. Upgrade-Wizards

- Neue Core-Namespaces: Attribut `TYPO3\CMS\Core\Attribute\UpgradeWizard`, Interfaces aus `TYPO3\CMS\Core\Upgrades\*`. `Install\Updates` ist deprecated. [Core]
- `final readonly class`, ID `beGroups_<name>`, Voraussetzung `DatabaseUpdatedPrerequisite`.
- **MUSS:** idempotent.
- `updateNecessary()` und `executeUpdate()` teilen sich eine Methode `migrate(bool $dryRun)`. Ausgaben laufen über `ChattyInterface`. [Core]
- **Vorlagen:**
  - `cms-core/Classes/Upgrades/UserPermissionsForRenamedModulesMigration.php` (arbeitet auf `be_groups`)
  - `cms-core/Classes/Upgrades/PageDoktypeLinkMigration.php`

---

## 11. Lokalisierung

- **Dateien:**
  - `Resources/Private/Language/db.xlf` (TCA), `messages.xlf` (allgemein), `Modules/roles.xlf` (Modul).
  - Deutsch als `de.db.xlf` usw.
  - Keine neuen `locallang*`-Namen. [CGL][Core]
- **Format:**
  - XLIFF 1.2, wie bei 279 von 291 Core-Dateien und kompatibel mit Crowdin.
  - 2 Leerzeichen Einrückung, `source-language="en"`.
  - Eindeutige IDs, Pflichtattribute laut Core-Integritätsprüfung. [Core]
- **Domains überall:**
  - PHP: `$languageService->translate('id', 'be_groups.messages')`
  - Fluid: `domain="be_groups.messages"`
  - TCA: `be_groups.db:…`
  - JS: `import labels from '~labels/be_groups.module'`
  - `TYPO3.lang` und `addInlineLanguageDomain()` sind tabu. [Core]
- **Texte:**
  - Nummerierte Platzhalter (`%1$s`), Sätze nie auf mehrere Labels aufteilen.
  - Keine fest eingebauten Texte, auch nicht in Exceptions für Benutzer. [CGL]

---

## 12. Frontend (TypeScript, Lit, SCSS)

**Komponenten:**
- TypeScript mit dem `tsconfig` des Core als Basis, **strenger** als der Core: `strict: true`. Die `.d.ts` der Core-Module werden dafür generiert. [Projekt]
- Lit-3-Web-Komponenten mit dem Präfix `be-groups-`. Komplexe Komponenten rendern ins Light-DOM und nutzen Backend-CSS-Klassen. Kleine Primitive nutzen Shadow-DOM mit `--typo3-*`-Variablen. [Core]
- **MUSS:**
  - Nur Core-Module für AJAX (`@typo3/core/ajax/ajax-request`), Benachrichtigungen und Modals.
  - `lit` und Bootstrap **nie** mitbündeln; sie kommen aus der Import-Map des Core. [Core]

**CSP und Darstellung:**
- **MUSS:** kein Inline-JS und kein Inline-CSS (CSP).
- ES-Module über `Configuration/JavaScriptModules.php` (`@cretection/be-groups/` → `EXT:be_groups/Resources/Public/JavaScript/`). [Core]
- **MUSS:** Nur CSS-Variablen und Klassen des Backends. Die Oberfläche funktioniert in den Themes Fresh, Modern und Classic, jeweils hell und dunkel, und ist RTL-tauglich. [Projekt]
- **MUSS:** WCAG 2.2 AA. Alles ist per Tastatur bedienbar, mit sichtbarem Fokus, ARIA-Rollen, ohne reine Farbsignale. [Projekt]

**Build:**
- `tsc` und rollup ohne Bündelung, nach dem Muster des Core. Das Build-Ergebnis wird committet; CI prüft mit `git diff --exit-code`, ob es aktuell ist. [Core][Projekt]
- **Linting:** ESLint mit der Konfiguration des Core, Stylelint mit der Konfiguration von tea (Stylelint 17). [Core][tea]

---

## 13. Tests

- PHPUnit 11.5 über `typo3/testing-framework` 9.7. [Core]
- **MUSS:**
  - Testklassen sind `final`.
  - Attribute `#[Test]`, `#[DataProvider]` und `#[CoversClass]`.
  - **Keine** Annotationen und kein `test`-Präfix.
- `failOnAllIssues`: Deprecations, Notices, Warnings und riskante Tests lassen den Lauf scheitern. `requireCoverageMetadata` ist aktiv. [Core][tea]
- **Functional Tests:**
  - CSV-Fixtures (`importCSVDataSet`/`assertCSVDataSet`), DataHandler-Szenarien über `ActionService`.
  - Sie laufen gegen SQLite, MariaDB, MySQL und PostgreSQL. [Core]
- Jeder Fehler, der behoben wird, bekommt zuerst einen Test, der ihn reproduziert. Die Szenarien aus der Machbarkeitsprüfung sind Pflicht-Tests. [Projekt]
- **E2E:**
  - Playwright unter `Build/tests/playwright/` mit Setup-Login, Page-Objects aus dem Core (`backend-page`, `modal`, `doc-header`).
  - axe-Prüfung gegen `wcag2a`, `wcag2aa`, `wcag21aa` und `wcag22aa`. [Core][Projekt]
- **JS-Unit-Tests** mit web-test-runner. [Core]
- **Abdeckung:** ≥ 90 % Zeilen in `Classes/`. Infection (MSI ≥ 80 %) für `Domain/` und `DataHandling/`. [Projekt]

---

## 14. Werkzeuge und CI

| Werkzeug | Konfiguration | Vorlage |
|---|---|---|
| `Build/Scripts/runTests.sh` (Container, `-d sqlite\|mariadb\|mysql\|postgres`, `-p 8.2…8.5`) | Image-Tags gepinnt wie im Core | [tea], angepasst |
| php-cs-fixer | `typo3/coding-standards` v0.9.0, nur `setHeader()` | [CGL] |
| PHPStan 2.x | **Level max, ohne Baseline**, strict-rules, deprecation-rules, `phpstan-typo3` 3.x, `featureToggles.internalTag: true` | [tea], verschärft |
| Rector | `ssch/typo3-rector`, `Typo3LevelSetList::UP_TO_TYPO3_14`, nur Prüfmodus | [tea] |
| Composer | `composer normalize`, Dependency-Analyse | [tea] |
| Integritätsprüfungen | Eindeutigkeit der Exception-Codes, finale Testklassen, keine Annotationen, XLIFF-Integrität und -Normalisierung | aus dem [Core] kopiert, Pfade angepasst |
| Frontend | ESLint (Core), Stylelint 17 (tea), `tsc`, prüft ob der Build aktuell ist | [Core][tea] |
| Doku | `render-guides` (gepinnt) mit `--fail-on-log` | [CGL] |

**CI-Matrix in GitHub Actions** (`runTests.sh -b docker`, denn Podman ist auf GitHub-Runnern unzuverlässig):
- **Unit:** PHP 8.2–8.5 mit den niedrigsten und höchsten Abhängigkeiten.
- **Functional:** SQLite, MariaDB 10.11 und 11.8, MySQL 8.0 und 8.4, PostgreSQL 14 und 18.
- **Weitere Jobs:** E2E auf SQLite, Doku, zusammengeführte Abdeckung.
- **Nächste TYPO3-Version:** Der Job `typo3-next` installiert TYPO3 v15-dev (`runTests.sh -p 8.5 -s composerUpdateDev`) und führt die Unit- und Functional-Tests aus. Er blockiert keinen Merge, weil sich die Entwicklungsversion des Core täglich ändert, **muss aber vor jedem Release grün sein** (`RELAUNCH.md` §8.6). [Projekt]

---

## 15. Sicherheit (Checkliste pro Release) [CGL][Projekt]

- [ ] Jeder Schreibzugriff auf Rechte läuft über den DataHandler. Sudo-Mode ist getestet.
- [ ] Jede Modulaktion und jede AJAX-Route prüft die Rechte. POST-Routen haben einen Token, eigene Formulare FormProtection.
- [ ] Jede SQL-Abfrage nutzt Named Parameters. Kein `f:format.raw` mit Eingaben, keine HTML-Erzeugung per String in PHP.
- [ ] Keine Rechteausweitung über Umwege, z. B. Felder, die ein Typ nicht anzeigt. Regel R1 ist getestet.
- [ ] `SECURITY.md` ist aktuell: Meldungen gehen an das TYPO3 Security Team (security@typo3.org), nicht in öffentliche Issues.

---

## 16. Dokumentation [CGL]

- `Documentation/` mit `guides.xml` (statt `Settings.cfg`) und `Index.rst`. Jede `.rst`-Datei bindet `.. include:: /Includes.rst.txt` ein.
- Gerendert wird mit `render-guides`. Warnungen lassen CI scheitern.
- Screenshots haben Alt-Text und werden pro Theme aktualisiert, wenn sich die Oberfläche ändert.
- **Pflichtinhalte:**
  - Konzept (mit Bezug zur offiziellen Empfehlung), Schnellstart, Migration, Konfiguration,
  - Erweitern (eigene Typen, Events), Changelog,
  - **Credits & Geschichte mit dem Erfinder Michael Klapper** (`RELAUNCH.md` §8.5).

---

## 17. Git, Commits und Releases

- **Commit-Betreff:**
  - `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` oder `[SECURITY]`, vorangestellt `[!!!]` bei Breaking Changes.
  - Im Imperativ, höchstens 72 Zeichen, Englisch.
  - Dazu ein Rumpf mit dem *Warum*.
  - Die Gerrit-Pflichtzeilen des Core (`Change-Id`, `Releases:`) gelten für uns **nicht**. [CGL][Projekt]
- **Branches:** `main` ist geschützt, Änderungen kommen nur über Pull Requests mit grüner Pipeline und Review (Definition of Done in `RELAUNCH.md` §8.2).
- **Historie:** Sie bleibt erhalten, also kein Squash der Historie von 2012 bis 2022.
- **Releases:**
  - Semantic Versioning, die Version steht in `composer.json` unter `extra.typo3/cms.version` und muss zum Tag passen.
  - Ein Tag löst die GitHub Action aus (tailor → TER), Packagist aktualisiert sich automatisch.
  - `CHANGELOG.md` und `Documentation/Changelog` werden gepflegt.
- **Lizenz:** `GPL-2.0-or-later` in `composer.json` und im Lizenzkopf, wie vom TYPO3 Core vorgegeben (Entscheidung E6 in `RELAUNCH.md`).

---

## 18. Was wir bewusst nicht vom Core übernehmen

- **Gerrit- und GitLab-spezifisches:** Change-Id, `Resolves:`/`Releases:`-Pflicht, Prüfsuiten nur für den letzten Commit, gestückelte Functional Tests.
- **Grunt und das Bündeln von Fremdbibliotheken.**
- **Core-Einstellungen:** PHPStan auf Level 5 mit Baseline, die `.stylelintrc` des Core (Stylelint 14), der Kopftext „TYPO3 CMS project“, das Präfix `typo3-` für Elemente.
- **Prüfungen, die nur das Core-Repository betreffen:** ISO-, Charset-, Rechte- und Submodule-Prüfungen, Changelog-RST, Set-Labels, Extension-Scanner-RST.
- **Interne Test-Helfer des Core** (z. B. `AbstractCommandTestCase`, `SiteBasedTestTrait`).

---

## 19. Kurzliste: Verboten

- `TYPO3_MODE`, `$GLOBALS['TYPO3_DB']`, `GeneralUtility::_GP()`, `ObjectManager`, `SingletonInterface`.
- `ext_emconf.php`, `ext_tables.php`, `ext_icon.*`.
- `$GLOBALS['TCA']` zur Laufzeit (außer in `TCA/Overrides`).
- Core-API mit `@internal`, benannte Argumente beim Aufruf von Core-API.
- Direktes SQL-Schreiben in `be_groups`/`be_users`, einen injizierten `DataHandler`.
- Inline-JS/CSS, `TYPO3.lang`, `f:format.raw` mit Eingaben, mitgebündeltes `lit`.
- Annotationen in Tests, Exceptions ohne Timestamp-Code, fest eingebaute Texte.
- PHPStan-Baseline, unterdrückte Deprecations.

---

## 20. Quellen

Abgerufen am 2026-10-06. Wenn sich der Core in v14.x oder v15 ändert, wird diese Leitlinie überarbeitet.

- **Coding Guidelines:**
  - Übersicht: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/CodingGuidelines/Index.html
  - PHP: …/CodingGuidelines/CglPhp/GeneralRequirementsForPhpFiles.html, …/FileStructure.html, …/PhpSyntaxFormatting.html, …/UsingPhpdoc.html
- **PHP-Architektur:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/PhpArchitecture/Index.html (Services, Exceptions, Readonly, Named Arguments)
- **Extension-Architektur:**
  - https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/FileStructure/Index.html
  - …/FileStructure/ComposerJson.html
  - …/BestPractices/NamingConventions.html
- **APIs:** Dependency Injection, Events, Deprecation, Localization, FormProtection, QueryBuilder, Backend-Module, Sudo-Mode, alle unter https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/
- **Sicherheit:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/GuidelinesExtensionDevelopment/Index.html
- **Testen:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/ExtensionTesting.html
- **Werkzeuge:**
  - `typo3/coding-standards` v0.9.0: https://github.com/TYPO3/coding-standards
  - Core 14.3.7: https://github.com/TYPO3/typo3/tree/14.3 (`Build/`, `typo3/sysext/*`)
  - tea: https://github.com/TYPO3BestPractices/tea
- **Changelog v14:** #108345 (`ext_emconf.php`), #109438 (`ext_tables.php`), #108310 und #108304 (`composer.json`), #107628 (Modulnamen), #109365 (Access Gates), #108008 (Doc-Header), #108166 (`*.fluid.html`), #93334 (Translation Domains), #108941 (Labels in JS)
