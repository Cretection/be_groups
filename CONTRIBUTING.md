# Contributing to be_groups

🇬🇧 English · 🇩🇪 [Deutsche Fassung](CONTRIBUTING.de.md)

Thank you for helping to make backend permissions in TYPO3 manageable! This
guide explains how to set up the project, how we work and what a pull
request needs before it can be merged.

> Both language versions of this guide are equally valid and are updated in
> the same pull request. If they contradict each other, the English version
> applies.

## Ground rules

- All code follows the binding
  [coding guidelines](CODING_GUIDELINES.md)
  ([Deutsch](CODING_GUIDELINES.de.md)).
- Everything people read – documentation, labels, README, these guides – is
  maintained in **English and German**. Code, comments and commit messages
  are in English.
- Security issues are **never** reported in public issues. See
  [SECURITY.md](SECURITY.md).
- Be kind. This project follows the
  [TYPO3 Code of Conduct](CODE_OF_CONDUCT.md).

## Setting up

Requirements: Git, PHP 8.2–8.5, Composer and Docker (or Podman) for the
container-based test runner.

```bash
git clone git@github.com:Cretection/be_groups.git
cd be_groups
composer install
```

Dependencies are installed into `.Build/`.

## Running checks and tests

All checks run in containers via `Build/Scripts/runTests.sh`, exactly like in
CI. Show all suites and options:

```bash
Build/Scripts/runTests.sh -h
```

Common commands:

```bash
# Unit tests
Build/Scripts/runTests.sh -s unit

# Functional tests (SQLite by default, other DBMS with -d)
Build/Scripts/runTests.sh -s functional
Build/Scripts/runTests.sh -s functional -d mariadb
Build/Scripts/runTests.sh -s functional -d postgres

# Code style (dry run) and static analysis
Build/Scripts/runTests.sh -s cgl -n
Build/Scripts/runTests.sh -s phpstan

# Render the documentation (English and German)
Build/Scripts/runTests.sh -s docs

# End-to-end tests with axe (WCAG 2.2 AA) in the light and the dark theme
Build/Scripts/runTests.sh -s e2e

# Coverage of all tests, at least 90 % of the lines in Classes/
Build/Scripts/runTests.sh -s unit -m
Build/Scripts/runTests.sh -s functional -m
Build/Scripts/runTests.sh -s coverageMerge
Build/Scripts/runTests.sh -s coverageCheck

# Mutation tests of Classes/Domain and Classes/DataHandling, at least 80 % MSI
# (takes more than an hour; one file: -- RelationList.php)
Build/Scripts/runTests.sh -s mutation
```

The end-to-end tests set up their own TYPO3 instance in `.Build/e2e` with the
scenario of `Build/tests/playwright/scenario.php`. The mutation tests run
weekly in CI and on demand, all other suites on every push.

On GitHub Actions we use `-b docker`; locally Podman is the default.

The Composer scripts are available as well, e.g. `composer check:static`
and `composer fix`.

## Commit messages

```
[TYPE] Imperative summary of at most 72 characters

Explain *why* the change is necessary, not only what changed.
Wrap the body at 72 characters.

Resolves: #123
```

- `TYPE` is one of `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` or
  `[SECURITY]`.
- Prefix breaking changes with `[!!!]`, e.g. `[!!!][FEATURE] …`.
- `Resolves: #123` is optional and references a GitHub issue.
- The Gerrit lines of the TYPO3 core (`Change-Id`, `Releases:`) are not used.

## Pull requests

1. Create a branch from `main` and open a pull request against `main`.
2. Keep pull requests focused: one topic per pull request.
3. Every bug fix starts with a test that reproduces the bug.
4. The pipeline must be green and the pull request must be reviewed before it
   can be merged.

### Definition of Done

A pull request is done when:

- [ ] All quality checks of the coding guidelines (§8.1 of `RELAUNCH.md`) are
      green – without new baseline entries.
- [ ] Tests cover the behavior, including error cases and writes via the
      DataHandler API.
- [ ] The documentation is updated in English **and** German, with
      screenshots for UI changes.
- [ ] All labels are translatable and the German translation is maintained.
- [ ] UI changes are checked for keyboard operation, all backend themes in
      light and dark mode, and with axe.
- [ ] Writes use the DataHandler; sudo mode and permissions are tested.
- [ ] `CHANGELOG.md` has an entry, and the commit message follows the format
      above.
- [ ] A second person reviewed the pull request.

## Translations

Labels live in `Resources/Private/Language/` (XLIFF 1.2). English is the
source language; the German files (`de.*.xlf`) are maintained in this
repository. Please keep both in sync in the same pull request.

All other languages are translated in the
[Crowdin project of TYPO3](https://docs.typo3.org/permalink/t3coreapi:crowdin-extension-integration)
and reach TYPO3 installations as language packs (Admin Tools > Maintenance >
Manage Languages). The workflow `Crowdin` uploads the English labels after
every change on `main` (`.crowdin.yml`). German translations in this
repository have precedence over the language pack, as TYPO3 reads the file
next to the source first.

## Documentation

The manual is written in reStructuredText in `Documentation/` (English) and
`Documentation/Localization.de_DE/` (German). Keep the structure of both
manuals identical so readers can switch the language on every page.

## Releases

Versions follow semantic versioning. The version lives in `composer.json`
(`extra.typo3/cms.version`) and in the settings of both manuals; between
releases it carries the suffix `-dev` (for example `1.2.1-dev`), which TYPO3
reads as the stability of the extension.

1. Make sure that the last run of the job `mutation` is green; start it on
   demand in the Actions tab ("Run workflow"). The jobs `typo3-next` and `e2e`
   have to be green as well.

2. Prepare the release in a pull request:

   ```bash
   php Build/Scripts/setVersion.php 1.2.0
   ```

   Then turn `## [Unreleased]` in `CHANGELOG.md` into `## [1.2.0] - YYYY-MM-DD`
   and give the entry in both changelogs of the manual
   (`Documentation/Changelog/` and `Documentation/Localization.de_DE/Changelog/`)
   the heading `1.2.0 (YYYY-MM-DD)`. Check the result:

   ```bash
   php Build/Scripts/checkReleaseVersion.php 1.2.0
   ```

3. After the pull request is merged, tag the commit on `main` with an
   annotated tag and push it. The message of the tag becomes the upload
   comment in the TER:

   ```bash
   git tag -a 1.2.0 -m "Short summary of the release"
   git push origin 1.2.0
   ```

   The workflow `Publish` (`.github/workflows/publish.yml`) checks that the tag
   is on `main` and that every file carries its version, and uploads the
   archive of the tag to the TER with tailor. The archive contains the same
   files as the Composer package (`export-ignore` in `.gitattributes`).
   Packagist and docs.typo3.org update through their webhooks.

4. Start the next version, e.g. `php Build/Scripts/setVersion.php 1.2.1-dev`,
   and add a new section `## [Unreleased]` to `CHANGELOG.md`.

Tags of pre-releases such as `1.2.0-rc1` are not uploaded to the TER, which
only accepts versions like `1.2.0`; Packagist offers them anyway.

The upload needs a TER access token for the extension key `be_groups` as the
secret `TYPO3_API_TOKEN` of the GitHub environment `ter`. See the
[tailor documentation](https://docs.typo3.org/other/typo3/tailor/main/en-us/Index.html)
for how to create one.
