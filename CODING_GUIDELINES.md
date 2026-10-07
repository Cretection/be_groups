# Coding Guidelines for be_groups

🇬🇧 English · 🇩🇪 [Deutsche Fassung](CODING_GUIDELINES.de.md)

Binding for all code written since the relaunch (TYPO3 14.3 LTS). As of 2026-10-07.

> Both language versions are equally valid and are updated in the same pull request. If they contradict each other, the English version applies, because every contributor can read it.

## 0. Scope and sources

These guidelines are based on three sources:

| Tag | Source |
|---|---|
| **[CGL]** | Official TYPO3 Coding Guidelines and extension best practices from *TYPO3 Explained* (main, i.e. v14), `typo3/coding-standards` v0.9.0 |
| **[Core]** | Patterns actually used in the TYPO3 v14.3.7 code base (counted via grep over `vendor/typo3/*`) |
| **[tea]** | `TYPO3BestPractices/tea`, the official best-practice example extension (tooling, CI) |
| **[Project]** | Project decision, see `RELAUNCH.md` |

**MUST** / **MUST NOT** are binding and are checked in CI wherever possible. **SHOULD** / **SHOULD NOT** apply unless there is a justified exception; the justification belongs in the pull request.

**Precedence in case of conflict:**
1. Whatever a tool enforces automatically (php-cs-fixer, PHPStan …),
2. then [CGL],
3. then [Core],
4. then [Project].

Where the official documentation is outdated (e.g. "PER-CS 1.0/2.0" instead of `@PER-CS3x0`), the state of Core and tooling applies.

---

## 1. Principles

1. **We write code that looks like TYPO3 14 Core code.** Anyone who knows the Core feels at home immediately. [Project]
2. **MUST:**
   - **Public Core API only.** Anything the Core marks `@internal` is off-limits (e.g. `GroupResolver`, `TcaSchemaFactory::rebuild()`). PHPStan checks this via `featureToggles.internalTag: true`. [Project]
   - **Zero deprecations:** tests fail on any deprecation, including deprecated labels marked `x-unused-since`. [Core]
   - **No API that is going away.** Only use what is neither `@deprecated` nor `@internal` in TYPO3 14.3 **and** in the current development version of the next major version (core `main`). Check both and the changelog of `main` before using a core API for the first time. [Project]
   - **PHP 8.2 to 8.5:** the code runs on the minimum PHP version of TYPO3 14 and of TYPO3 15 and uses nothing PHP 8.5 deprecates. [Project]
   - **Permissions are written through the DataHandler only.** Never write to `be_groups` or `be_users` via SQL; only then do permissions, sudo mode, history and log apply. [Project]
3. **Small and explicit:** prefer one more small service over a class that does everything. No magic, no global state. [CGL]

---

## 2. Files in general

- **MUST:** UTF-8 without BOM, LF line endings, a newline at the end of the file, no trailing whitespace. [CGL]
- **MUST:** Adopt the Core `.editorconfig`: 4 spaces for PHP; 2 spaces for XLIFF, YAML, JSON, TypeScript, SCSS and Fluid. [CGL][Core]
- **MUST:** Only `Resources/Public/` is web-accessible. [CGL]
- **MUST NOT be shipped:** `ext_emconf.php` (deprecated since 14.2, no longer read in v15), `ext_tables.php` (deprecated since 14.3), `ext_icon.*`. The extension icon lives at `Resources/Public/Icons/Extension.svg`. [CGL]

---

## 3. PHP

### 3.1 File anatomy (MUST) [CGL][Core]

The order is always:
1. `<?php`
2. `declare(strict_types=1);`
3. license header
4. `namespace`
5. `use` block
6. class docblock
7. attributes
8. class

Exactly one class per file, no closing `?>` tag.

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

- The header is set via `CsFixerConfig::setHeader('This file is part of the TYPO3 CMS extension "be_groups".')` and checked by php-cs-fixer. We do **not** copy the Core header text ("part of the TYPO3 CMS project"). [CGL]
- Configuration files (`Modules.php`, `JavaScriptModules.php`, `Icons.php`, base TCA) carry no license header. Only `ext_localconf.php`, `Services.php` and `TCA/Overrides/*` carry `declare(strict_types=1)`. [Core]
- `ext_localconf.php`: no namespace, no `return`. `defined('TYPO3') or die();` follows the `use` statements. Icons are **not** registered here. [CGL]

### 3.2 Formatting (MUST, enforced by php-cs-fixer) [CGL][Core]

We use `typo3/coding-standards` **v0.9.0**. Its rule set is byte-identical to Core 14.3 (`@PER-CS3x0` plus TYPO3 rules). The configuration comes from `CsFixerConfig::create()`, and no project-specific rules are added. Among other things, this means:

- **Casts and types:** casts without a space (`(int)$value`), nullable types as `?Type`.
- **Strings and arrays:** single quotes, short array syntax, trailing commas in multi-line arrays, parameter lists and argument lists.
- **Control structures:** no Yoda conditions, `elseif` instead of `else if`.
- **`use` statements:** alphabetical, not grouped, no `use function`.
- **Global names:**
  - Global classes are referenced with a leading `\` and not imported (`\RuntimeException`).
  - Native functions are called without a leading backslash (`sprintf(`).
- **Comments:** `//` instead of `#`.

### 3.3 Naming [CGL]

| Element | Rule |
|---|---|
| Classes, interfaces, enums | UpperCamelCase; interfaces end in `Interface`, events in `Event`, listeners live in `EventListener/` |
| Methods, variables, properties | lowerCamelCase, no underscores, no abbreviations like `BE`/`FE` in identifiers |
| Constants | UPPER_SNAKE_CASE |
| Own columns on `be_groups` | `tx_begroups_<name>` (hence `tx_begroups_kind`) |
| Upgrade wizard ID | `beGroups_<wizardName>`, **never** renamed after release (stored in `sys_registry`) |
| Listener ID | `cretection/be-groups/<purpose>` |
| CLI commands | `begroups:<action>` |
| Web components | prefix `be-groups-` (not `typo3-`) |

### 3.4 Types and phpDoc [CGL][Core]

- **MUST:**
  - Parameters, return values and properties are fully typed natively.
  - Every code path returns a value.
- **phpDoc only where native types are not enough:**
  - array shapes and lists (`list<string>`, `array<string, GroupKind>`),
  - generics,
  - `@throws` when the caller is expected to react.
- No phpDoc that merely repeats the signature. [CGL]
- Debug code (`var_dump`, `DebuggerUtility`, `print_r`) is never committed. [CGL]

### 3.5 Classes and language features

- **Services** are stateless `final readonly class` with promoted constructor properties (`private`). An empty body is written as `) {}`. [CGL][Core]
- **Value objects** are `final readonly`, created with `new`, named arguments welcome.
- **Enums** are string-backed and used for fixed states.
- `match` and first-class callables (`$this->foo(...)`) are encouraged. [Core]
- **MUST NOT:**
  - `SingletonInterface`, static state,
  - traits without a matching interface,
  - utility classes with state. Utility classes are static only, live in `Utility/` and are named `*Utility`. Avoid them where possible. [CGL]
- **SHOULD NOT:** named arguments when calling **Core** API. Core parameter names are not covered by backwards compatibility. [CGL]
- **SHOULD NOT:** `GeneralUtility::makeInstance()` for our own services; use constructor injection instead. Exceptions are listed in 4.2.
- **No access to `$GLOBALS['TCA']` at runtime.** TCA is read via `TcaSchemaFactory->get()`, capabilities via `hasCapability(TcaSchemaCapability::…)`. [Core]

### 3.6 Public API of be_groups [Core][Project]

- Everything is `@internal` except what is deliberately public: our own events, documented extension points and the kind identifiers.
- We do not use `@api` (the Core uses it 0 times). Public API is whatever is **not** `@internal`.
- Semantic versioning applies **only** to the public API.

### 3.7 Exceptions [CGL][Core]

- **MUST:**
  - Every thrown exception has a **10-digit Unix timestamp** (`date +%s` at the time of writing) as its code, passed as the second argument.
  - Codes are unique and never changed. An integrity checker copied from the Core enforces this.
- `\RuntimeException` / `\InvalidArgumentException` for programming errors.
- Own, empty exception classes in `Classes/Exception/` for cases that callers should handle specifically. The base exception extends `\TYPO3\CMS\Core\Exception`.
- **DataHandler hooks do not throw:** they write to the system log via `$dataHandler->BE_USER->writelog()` (public API; `DataHandler::log()` is `@internal`) and discard the field or record instead (see 6).

### 3.8 Own deprecations [CGL]

This only becomes relevant after 1.0. Then both of the following always apply:
- `@deprecated since 1.x, will be removed in 2.0`,
- `trigger_error('…', E_USER_DEPRECATED)`.

A changelog entry is mandatory.

---

## 4. Architecture and dependency injection

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

Listeners, commands, controllers and upgrade wizards are registered **by attribute only**, not in YAML. [Core]

### 4.2 Special cases [CGL][Core]

- **Classes created via `makeInstance()`** (DataHandler hooks, `itemsProcFunc`, `label_userFunc`, FormEngine nodes) get `#[Autoconfigure(public: true)]`. This makes constructor injection work for them too.
- **MUST NOT:** inject the `DataHandler`. Create a fresh instance via `GeneralUtility::makeInstance(DataHandler::class)` for every operation.
- **Stateful services** get `shared: false`. Never mix manual constructor arguments with DI.
- **Logging:** inject `Psr\Log\LoggerInterface` via the constructor.
- **Database:**
  - Inject `ConnectionPool` and use a separate QueryBuilder per query.
  - **Every** value goes through `createNamedParameter($value, Connection::PARAM_INT|PARAM_STR|…)`, every identifier taken from a variable through `quoteIdentifier()`.
  - SQL lives in repositories.

---

## 5. Events and listeners

### 5.1 Own events [Core]

- They live in `Classes/Event/` and are named `Before…Event`, `After…Event` or `Modify…Event`. 147 of 188 Core events follow this scheme.
- Immutable events are `final readonly class`. Events with setters (`Modify…`) are `final class`.
- Events are public API and therefore **not** `@internal`. Every event is documented and tested.
- Dispatch through an injected `Psr\EventDispatcher\EventDispatcherInterface`.

### 5.2 Listeners [CGL][Core]

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

- They live in `Classes/EventListener/` and handle exactly one event each via `__invoke()`.
- **`AfterTcaCompilationEvent`** fires *after* the TCA migration:
  - Anything added there MUST already be in the final v14 format.
  - The listener must not use `TcaSchemaFactory`, because the schema is only built afterwards.
  - Anything that can be done statically belongs in `TCA/Overrides`. [Core]

---

## 6. DataHandler hooks

- **Registration** in `ext_localconf.php` with a named key:
  ```php
  $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['be_groups']
      = \Cretection\BeGroups\DataHandling\GroupKindRules::class;
  ```
  v14 still offers no PSR-14 alternative for saving records. [Core]
- **Class shape:** `final readonly class` with `#[Autoconfigure(public: true)]`, as in `DataHandlerAuthenticationContext`. A hook that collects state during a DataHandler run (e.g. reports emitted in `processDatamap_afterAllOperations`) is a `final class` with `#[Autoconfigure(public: true, shared: false)]`, so every run gets its own instance. Methods are fully typed. [Core][Project]
- Get the current user via `$dataHandler->BE_USER`. `DataHandler::$admin` and `$userid` no longer exist in v14. [Core]
- **Tolerant:**
  - Rules correct and log (`$dataHandler->BE_USER->writelog()`) instead of throwing exceptions, so import and sync tools don't break.
  - Corrections are reported in `processDatamap_afterAllOperations`, so an aborted save (e.g. cancelled sudo mode) leaves no log entry.
  - Whether a rule applies when `$dataHandler->isImporting` is set is decided per rule, documented and tested. [Project]
- **Sudo mode:** the Core checks protected fields in the final field array (`processDatamap_postProcessFieldArray`). Our hooks run earlier, so anything they set or clear is automatically protected. This is intended. [Core]

---

## 7. TCA

- **MUST:** We only change tables we don't own, so everything goes into `Configuration/TCA/Overrides/be_groups.php` or `be_users.php`, starting with `defined('TYPO3') or die();`. [CGL]
- **Items** use keys (`'label' => …, 'value' => …, 'icon' => …, 'group' => …`), never numeric indexes. [Core]
- **Labels** via translation domains: our own as `be_groups.db:…`, tabs and palettes from the Core (`core.form.tabs:…`). Every field has a `description`. [Core]
- **Columns** are generated from TCA. `ext_tables.sql` only contains what TCA cannot generate, e.g. the index on `tx_begroups_kind`. [CGL][Core]
- **Permission-relevant fields** get `'authenticationContext' => ['group' => 'be.userManagement']` (sudo mode), as in the Core. This also applies to `tx_begroups_kind`. [Core]
- **Type-specific title** via `types[<kind>]['title']` (v14). No `searchFields`; a field becomes searchable via `searchable` on the field itself. [Core]
- **One form per kind:** no form may hide permission fields that still take effect (rule R1 in `RELAUNCH.md`).

---

## 8. Backend module, routes and Fluid

**Registration (`Configuration/Backend/Modules.php`):**
- `parent => 'admin'` (next to "Users"), `access`, `iconIdentifier`.
- `labels` as a domain whose file contains `title`, `short_description` and `description`.
- Additional `routes` for state-changing actions with `'methods' => ['POST']`. AJAX routes also go here with `'ajax' => true`; we don't need `AjaxRoutes.php`. [Core]

**Controller:**
- `#[AsController] final readonly class`, one action per route.
- The flow is `ModuleTemplateFactory->create($request)` → `assignMultiple()` → `renderResponse('Roles/Index')`.
- Doc header buttons only via `ComponentFactory` (`ButtonBar::make*` is deprecated). `setShortcutContext()` is set. [Core]

**Security:**
- The Core checks route tokens itself.
- Our own POST forms additionally use FormProtection (`generateToken`/`validateToken`).
- Every action checks permissions itself.
- Writes go through the DataHandler, i.e. with sudo mode. [CGL][Core]

**Fluid:**
- Templates are named `*.fluid.html` (Fluid 5) and use `<f:layout name="Module"/>` and the `f:be.*` view helpers.
- **MUST NOT:** `f:format.raw` with user input. [CGL][Core]

**Good Core references:**
- `cms-reactions/Classes/Controller/ManagementController.php`
- `cms-recycler/Classes/Controller/RecyclerAjaxController.php`
- `cms-backend/Classes/Controller/SiteSettingsController.php` (form token)

---

## 9. CLI commands

- `#[AsCommand('begroups:audit', 'Checks …')]`, output via `SymfonyStyle`, return `Command::SUCCESS` / `Command::FAILURE`. [Core]
- **MUST:** Every command that changes data has `--dry-run` and works non-interactively (`--no-interaction`).
- Call `Bootstrap::initializeBackendAuthentication()` before using the DataHandler. [Core]
- **References:**
  - `cms-backend/Classes/Command/CreateBackendUserCommand.php`
  - `cms-form/Classes/Command/CleanupFormUploadsCommand.php`

---

## 10. Upgrade wizards

- New Core namespaces: attribute `TYPO3\CMS\Core\Attribute\UpgradeWizard`, interfaces from `TYPO3\CMS\Core\Upgrades\*`. `Install\Updates` is deprecated. [Core]
- `final readonly class`, ID `beGroups_<name>`, prerequisite `DatabaseUpdatedPrerequisite`.
- **MUST:** idempotent.
- `updateNecessary()` and `executeUpdate()` share one `migrate(bool $dryRun)` method. Output goes through `ChattyInterface`. [Core]
- **References:**
  - `cms-core/Classes/Upgrades/UserPermissionsForRenamedModulesMigration.php` (works on `be_groups`)
  - `cms-core/Classes/Upgrades/PageDoktypeLinkMigration.php`

---

## 11. Localization

- **Files:**
  - `Resources/Private/Language/db.xlf` (TCA), `messages.xlf` (general), `Modules/roles.xlf` (module).
  - German as `de.db.xlf` etc.
  - No new `locallang*` names. [CGL][Core]
- **Format:**
  - XLIFF 1.2, as used by 279 of 291 Core files and compatible with Crowdin.
  - 2-space indentation, `source-language="en"`.
  - Unique IDs, mandatory attributes as required by the Core integrity check. [Core]
- **Domains everywhere:**
  - PHP: `$languageService->translate('id', 'be_groups.messages')`
  - Fluid: `domain="be_groups.messages"`
  - TCA: `be_groups.db:…`
  - JS: `import labels from '~labels/be_groups.module'`
  - `TYPO3.lang` and `addInlineLanguageDomain()` are off-limits. [Core]
- **Texts:**
  - Numbered placeholders (`%1$s`); never split a sentence across several labels.
  - No hard-coded texts, including exceptions shown to users. [CGL]

---

## 12. Frontend (TypeScript, Lit, SCSS)

**Components:**
- TypeScript based on the Core `tsconfig`, but **stricter** than the Core: `strict: true`. Type declarations (`.d.ts`) for the Core modules are generated for this. [Project]
- Lit 3 web components with the prefix `be-groups-`. Complex components render into the light DOM and use backend CSS classes. Small primitives use shadow DOM with `--typo3-*` variables. [Core]
- **MUST:**
  - Use only Core modules for AJAX (`@typo3/core/ajax/ajax-request`), notifications and modals.
  - **Never** bundle `lit` or Bootstrap; they come from the Core import map. [Core]

**CSP and appearance:**
- **MUST NOT:** inline JS or inline CSS (CSP).
- ES modules via `Configuration/JavaScriptModules.php` (`@cretection/be-groups/` → `EXT:be_groups/Resources/Public/JavaScript/`). [Core]
- **MUST:** Use only backend CSS variables and classes. The UI works in the Fresh, Modern and Classic themes, each in light and dark mode, and is RTL-ready. [Project]
- **MUST:** WCAG 2.2 AA. Everything is keyboard-operable, with visible focus, ARIA roles and no colour-only signals. [Project]

**Build:**
- `tsc` plus rollup without bundling, following the Core pattern. The build output is committed; CI checks with `git diff --exit-code` that it is up to date. [Core][Project]
- **Linting:** ESLint with the Core configuration, Stylelint with the tea configuration (Stylelint 17). [Core][tea]

---

## 13. Tests

- PHPUnit 11.5 via `typo3/testing-framework` 9.7. [Core]
- **MUST:**
  - Test classes are `final`.
  - Attributes `#[Test]`, `#[DataProvider]` and `#[CoversClass]`.
  - **No** annotations and no `test` prefix.
- `failOnAllIssues`: deprecations, notices, warnings and risky tests make the run fail. `requireCoverageMetadata` is enabled. [Core][tea]
- **Functional tests:**
  - CSV fixtures (`importCSVDataSet`/`assertCSVDataSet`), DataHandler scenarios via `ActionService`.
  - They run against SQLite, MariaDB, MySQL and PostgreSQL. [Core]
- Every bug fix starts with a test that reproduces the bug. The scenarios from the feasibility study are mandatory tests. [Project]
- **E2E:**
  - Playwright in `Build/tests/playwright/` with a setup login and page objects from the Core (`backend-page`, `modal`, `doc-header`).
  - axe checks against `wcag2a`, `wcag2aa`, `wcag21aa` and `wcag22aa`. [Core][Project]
- **JS unit tests** with web-test-runner. [Core]
- **Coverage:** ≥ 90 % lines in `Classes/`. Infection (MSI ≥ 80 %) for `Domain/` and `DataHandling/`. [Project]

---

## 14. Tooling and CI

| Tool | Configuration | Reference |
|---|---|---|
| `Build/Scripts/runTests.sh` (containers, `-d sqlite\|mariadb\|mysql\|postgres`, `-p 8.2…8.5`) | image tags pinned as in the Core | [tea], adapted |
| php-cs-fixer | `typo3/coding-standards` v0.9.0, only `setHeader()` | [CGL] |
| PHPStan 2.x | **level max, no baseline**, strict rules, deprecation rules, `phpstan-typo3` 3.x, `featureToggles.internalTag: true` | [tea], tightened |
| Rector | `ssch/typo3-rector`, `Typo3LevelSetList::UP_TO_TYPO3_14`, check mode only | [tea] |
| Composer | `composer normalize`, dependency analysis | [tea] |
| Integrity checks | unique exception codes, final test classes, no annotations, XLIFF integrity and normalization | copied from the [Core], paths adapted |
| Frontend | ESLint (Core), Stylelint 17 (tea), `tsc`, build up-to-date check | [Core][tea] |
| Docs | `render-guides` (pinned) with `--fail-on-log` | [CGL] |

**CI matrix in GitHub Actions** (`runTests.sh -b docker`, because podman is unreliable on GitHub runners):
- **Unit:** PHP 8.2–8.5 with lowest and highest dependencies.
- **Functional:** SQLite, MariaDB 10.11 and 11.8, MySQL 8.0 and 8.4, PostgreSQL 14 and 18.
- **Further jobs:** E2E on SQLite, docs, merged coverage.
- **Next TYPO3 version:** the job `typo3-next` installs TYPO3 v15-dev (`runTests.sh -p 8.5 -s composerUpdateDev`) and runs the unit and functional tests. It does not block merging, because the core development version changes daily, but it **must be green before every release** (`RELAUNCH.md` §8.6). [Project]

---

## 15. Security (checklist per release) [CGL][Project]

- [ ] Every write to permissions goes through the DataHandler. Sudo mode is tested.
- [ ] Every module action and every AJAX route checks permissions. POST routes have a token, our own forms use FormProtection.
- [ ] Every SQL query uses named parameters. No `f:format.raw` with input, no HTML built from strings in PHP.
- [ ] No privilege escalation through side channels, e.g. fields a kind does not display. Rule R1 is tested.
- [ ] `SECURITY.md` is up to date: reports go to the TYPO3 Security Team (security@typo3.org), not to public issues.

---

## 16. Documentation [CGL]

- `Documentation/` with `guides.xml` (instead of `Settings.cfg`) and `Index.rst`. Every `.rst` file includes `.. include:: /Includes.rst.txt`.
- Rendering uses `render-guides`. Warnings make CI fail.
- Screenshots have alt text and are updated for each theme whenever the UI changes.
- **Mandatory content:**
  - concept (with reference to the official recommendation), quick start, migration, configuration,
  - extending (own kinds, events), changelog,
  - **Credits & History naming the inventor, Michael Klapper** (`RELAUNCH.md` §8.5).

---

## 17. Git, commits and releases

- **Commit subject:**
  - `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` or `[SECURITY]`, prefixed with `[!!!]` for breaking changes.
  - Imperative mood, at most 72 characters, in English.
  - Plus a body explaining the *why*.
  - The Core's Gerrit-specific mandatory lines (`Change-Id`, `Releases:`) do **not** apply to us. [CGL][Project]
- **Branches:** `main` is protected; changes only arrive through pull requests with a green pipeline and a review (Definition of Done in `RELAUNCH.md` §8.2).
- **History:** It is preserved, so no squashing of the 2012–2022 history.
- **Releases:**
  - Semantic versioning; the version lives in `composer.json` under `extra.typo3/cms.version` and must match the tag.
  - A tag triggers the GitHub Action (tailor → TER); Packagist updates automatically.
  - `CHANGELOG.md` and `Documentation/Changelog` are maintained.
- **License:** `GPL-2.0-or-later` in `composer.json` and in the license header, as required by the TYPO3 Core (decision E6 in `RELAUNCH.md`).

---

## 18. What we deliberately do not copy from the Core

- **Gerrit and GitLab specifics:** Change-Id, mandatory `Resolves:`/`Releases:`, check suites for the last commit only, chunked functional tests.
- **Grunt and bundling third-party libraries.**
- **Core settings:** PHPStan level 5 with a baseline, the Core `.stylelintrc` (Stylelint 14), the "TYPO3 CMS project" header text, the `typo3-` element prefix.
- **Checks that only concern the Core repository:** ISO, charset, permission and submodule checks, changelog RST, set labels, extension scanner RST.
- **Internal Core test helpers** (e.g. `AbstractCommandTestCase`, `SiteBasedTestTrait`).

---

## 19. Quick list: forbidden

- `TYPO3_MODE`, `$GLOBALS['TYPO3_DB']`, `GeneralUtility::_GP()`, `ObjectManager`, `SingletonInterface`.
- `ext_emconf.php`, `ext_tables.php`, `ext_icon.*`.
- `$GLOBALS['TCA']` at runtime (except in `TCA/Overrides`).
- Core API marked `@internal`, named arguments when calling Core API.
- Writing to `be_groups`/`be_users` directly via SQL, an injected `DataHandler`.
- Inline JS/CSS, `TYPO3.lang`, `f:format.raw` with input, bundled `lit`.
- Annotations in tests, exceptions without a timestamp code, hard-coded texts.
- A PHPStan baseline, suppressed deprecations.

---

## 20. Sources

Retrieved on 2026-10-06. These guidelines are revised whenever the Core changes in v14.x or v15.

- **Coding Guidelines:**
  - Overview: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/CodingGuidelines/Index.html
  - PHP: …/CodingGuidelines/CglPhp/GeneralRequirementsForPhpFiles.html, …/FileStructure.html, …/PhpSyntaxFormatting.html, …/UsingPhpdoc.html
- **PHP architecture:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/PhpArchitecture/Index.html (services, exceptions, readonly, named arguments)
- **Extension architecture:**
  - https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/FileStructure/Index.html
  - …/FileStructure/ComposerJson.html
  - …/BestPractices/NamingConventions.html
- **APIs:** dependency injection, events, deprecation, localization, FormProtection, QueryBuilder, backend modules, sudo mode, all under https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/
- **Security:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/GuidelinesExtensionDevelopment/Index.html
- **Testing:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/ExtensionTesting.html
- **Tools:**
  - `typo3/coding-standards` v0.9.0: https://github.com/TYPO3/coding-standards
  - Core 14.3.7: https://github.com/TYPO3/typo3/tree/14.3 (`Build/`, `typo3/sysext/*`)
  - tea: https://github.com/TYPO3BestPractices/tea
- **Changelog v14:** #108345 (`ext_emconf.php`), #109438 (`ext_tables.php`), #108310 and #108304 (`composer.json`), #107628 (module names), #109365 (access gates), #108008 (doc header), #108166 (`*.fluid.html`), #93334 (translation domains), #108941 (labels in JS)
