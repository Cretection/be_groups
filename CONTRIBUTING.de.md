# Mitwirken an be_groups

🇬🇧 [English version](CONTRIBUTING.md) · 🇩🇪 Deutsch

Danke, dass du hilfst, Backend-Rechte in TYPO3 beherrschbar zu machen! Diese
Anleitung erklärt, wie du das Projekt einrichtest, wie wir arbeiten und was
ein Pull Request braucht, bevor er gemergt werden kann.

> Beide Sprachfassungen dieser Anleitung sind gleichwertig und werden im
> selben Pull Request aktualisiert. Bei Widersprüchen gilt die englische
> Fassung.

## Grundregeln

- Aller Code folgt den verbindlichen
  [Coding-Leitlinien](CODING_GUIDELINES.de.md)
  ([English](CODING_GUIDELINES.md)).
- Alles, was Menschen lesen – Dokumentation, Labels, README, diese
  Anleitungen –, wird auf **Englisch und Deutsch** gepflegt. Code,
  Kommentare und Commit-Nachrichten sind Englisch.
- Sicherheitslücken werden **nie** in öffentlichen Issues gemeldet. Siehe
  [SECURITY.md](SECURITY.md).
- Sei freundlich. Für dieses Projekt gilt der
  [TYPO3 Code of Conduct](CODE_OF_CONDUCT.md).

## Einrichten

Voraussetzungen: Git, PHP 8.2–8.5, Composer und Docker (oder Podman) für den
Test-Runner in Containern.

```bash
git clone git@github.com:Cretection/be_groups.git
cd be_groups
composer install
```

Die Abhängigkeiten landen in `.Build/`.

## Prüfungen und Tests ausführen

Alle Prüfungen laufen in Containern über `Build/Scripts/runTests.sh`, genau
wie in CI. Alle Suiten und Optionen anzeigen:

```bash
Build/Scripts/runTests.sh -h
```

Häufige Befehle:

```bash
# Unit-Tests
Build/Scripts/runTests.sh -s unit

# Functional Tests (standardmäßig SQLite, andere Datenbanken mit -d)
Build/Scripts/runTests.sh -s functional
Build/Scripts/runTests.sh -s functional -d mariadb
Build/Scripts/runTests.sh -s functional -d postgres

# Codestil (Probelauf) und statische Analyse
Build/Scripts/runTests.sh -s cgl -n
Build/Scripts/runTests.sh -s phpstan

# Dokumentation rendern (Englisch und Deutsch)
Build/Scripts/runTests.sh -s docs

# End-to-End-Tests mit axe (WCAG 2.2 AA) im hellen und im dunklen Theme
Build/Scripts/runTests.sh -s e2e

# Abdeckung aller Tests, mindestens 90 % der Zeilen in Classes/
Build/Scripts/runTests.sh -s unit -m
Build/Scripts/runTests.sh -s functional -m
Build/Scripts/runTests.sh -s coverageMerge
Build/Scripts/runTests.sh -s coverageCheck

# Mutationstests von Classes/Domain und Classes/DataHandling, mindestens 80 % MSI
# (dauert über eine Stunde; eine Datei: -- RelationList.php)
Build/Scripts/runTests.sh -s mutation
```

Die End-to-End-Tests richten eine eigene TYPO3-Instanz in `.Build/e2e` ein, mit
dem Szenario aus `Build/tests/playwright/scenario.php`. Die Mutationstests
laufen in der CI wöchentlich und auf Abruf, alle anderen Suiten bei jedem Push.

In GitHub Actions verwenden wir `-b docker`; lokal ist Podman der Standard.

Die Composer-Skripte stehen ebenfalls zur Verfügung, z. B.
`composer check:static` und `composer fix`.

## Commit-Nachrichten

```
[TYPE] Imperative summary of at most 72 characters

Explain *why* the change is necessary, not only what changed.
Wrap the body at 72 characters.

Resolves: #123
```

- `TYPE` ist `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]` oder `[SECURITY]`.
- Breaking Changes bekommen das Präfix `[!!!]`, z. B. `[!!!][FEATURE] …`.
- `Resolves: #123` ist optional und verweist auf ein GitHub-Issue.
- Die Gerrit-Zeilen des TYPO3 Core (`Change-Id`, `Releases:`) werden nicht
  verwendet.
- Commit-Nachrichten sind Englisch.

### Das Changelog entsteht aus den Commits

`CHANGELOG.md` wird nicht von Hand gepflegt. [git-cliff](https://git-cliff.org/)
erzeugt den Eintrag eines Releases aus den Commit-Nachrichten (`cliff.toml`),
**die Betreffzeile eines Commits ist also eine Zeile des Changelogs**. Sie wird
für die Leser des Changelogs geschrieben („Show the TSconfig precedence of
roles in the module“), nicht für den Reviewer. Das Changelog nennt, was für
Anwender zählt, eine Zeile pro Commit:

| Präfix | Abschnitt im Changelog |
|---|---|
| `[!!!]` | Breaking changes |
| `[SECURITY]` | Security |
| `[FEATURE]` | Added |
| `[BUGFIX]` | Fixed |
| `[TASK]`, `[DOCS]` | nicht aufgeführt (Werkzeuge, Tests, Dokumentation, Abhängigkeiten) |

Commits ohne eines dieser Präfixe werden ebenfalls nicht aufgeführt. Eine
letzte Zeile im Text des Commits überschreibt den Abschnitt:

```
Changelog: changed
```

Die Werte sind `added`, `changed`, `deprecated`, `removed`, `fixed` und
`security`, zum Beispiel für ein `[TASK]`, das das Verhalten ändert.
`Changelog: skip` blendet einen Commit aus, zum Beispiel die Korrektur eines
Features, das noch nicht veröffentlicht ist.

## Branches und Pull Requests

`dev` ist der Standard-Branch. Alle Änderungen kommen dorthin, und auch
Dependabot schlägt seine Updates dagegen vor. `main` enthält nur veröffentlichte
Versionen: Bei einem Release wird es auf den geprüften Stand von `dev`
vorgezogen, und niemand committet darauf.

1. Einen Branch von `dev` anlegen und einen Pull Request gegen `dev`
   öffnen.
2. Pull Requests fokussiert halten: ein Thema pro Pull Request.
3. Jede Fehlerbehebung beginnt mit einem Test, der den Fehler nachstellt.
4. Die Pipeline muss grün sein und der Pull Request ein Review haben, bevor
   er gemergt werden kann.

### Definition of Done

Ein Pull Request ist fertig, wenn:

- [ ] Alle Qualitätsprüfungen der Coding-Leitlinien (§8.1 in `RELAUNCH.md`)
      grün sind – ohne neue Baseline-Einträge.
- [ ] Tests das Verhalten abdecken, einschließlich Fehlerfällen und
      Schreibzugriffen über die DataHandler-API.
- [ ] Die Dokumentation auf Englisch **und** Deutsch aktualisiert ist, mit
      Screenshots bei Änderungen an der Oberfläche.
- [ ] Alle Labels übersetzbar sind und die deutsche Übersetzung gepflegt ist.
- [ ] Änderungen an der Oberfläche auf Tastaturbedienung, alle
      Backend-Themes in hell und dunkel und mit axe geprüft sind.
- [ ] Schreibzugriffe den DataHandler nutzen; Sudo-Mode und Rechte sind
      getestet.
- [ ] Die Commit-Nachrichten dem Format oben folgen; ihre Betreffzeilen sind
      die Einträge des Changelogs.
- [ ] Eine zweite Person den Pull Request geprüft hat.

## Übersetzungen

Labels liegen in `Resources/Private/Language/` (XLIFF 1.2). Englisch ist die
Quellsprache; die deutschen Dateien (`de.*.xlf`) werden in diesem Repository
gepflegt. Bitte beide im selben Pull Request aktuell halten.

Alle anderen Sprachen werden im
[Crowdin-Projekt von TYPO3](https://docs.typo3.org/permalink/t3coreapi:crowdin-extension-integration)
übersetzt und kommen als Sprachpakete in TYPO3-Installationen (Admin Tools >
Maintenance > Manage Languages). Der Workflow `Crowdin` lädt die englischen
Labels nach jeder Änderung auf `dev` hoch (`.crowdin.yml`). Die deutschen
Übersetzungen in diesem Repository haben Vorrang vor dem Sprachpaket, weil
TYPO3 zuerst die Datei neben der Quelle liest.

## Dokumentation

Das Handbuch ist in reStructuredText geschrieben, in `Documentation/`
(Englisch) und `Documentation/Localization.de_DE/` (Deutsch). Die Struktur
beider Handbücher bleibt identisch, damit Leser auf jeder Seite die Sprache
wechseln können.

## Releases

Versionen folgen der semantischen Versionierung. Die Version steht in
`composer.json` (`extra.typo3/cms.version`) und in den Einstellungen beider
Handbücher; zwischen zwei Releases trägt sie das Suffix `-dev` (zum Beispiel
`1.2.1-dev`), das TYPO3 als Stabilität der Extension liest.

1. Sicherstellen, dass der letzte Lauf des Jobs `mutation` grün ist; auf
   Abruf startet er im Reiter „Actions“ („Run workflow“). Auch die Jobs
   `typo3-next` und `e2e` müssen grün sein.

2. Das Release in einem Pull Request gegen `dev` vorbereiten:

   ```bash
   php Build/Scripts/setVersion.php 1.2.0
   # Vorschau, dann den Eintrag oben in CHANGELOG.md schreiben
   Build/Scripts/runTests.sh -s changelog -- --unreleased --tag 1.2.0
   Build/Scripts/runTests.sh -s changelog -- --unreleased --tag 1.2.0 --prepend CHANGELOG.md
   ```

   Den erzeugten Eintrag lesen und die Formulierungen bei Bedarf verbessern.
   Dem Eintrag in beiden Changelogs des Handbuchs (`Documentation/Changelog/`
   und `Documentation/Localization.de_DE/Changelog/`) die Überschrift
   `1.2.0 (JJJJ-MM-TT)` und eine kurze Zusammenfassung dessen geben, was für
   Anwender zählt. Das Ergebnis prüfen:

   ```bash
   php Build/Scripts/checkReleaseVersion.php 1.2.0
   ```

3. Nach dem Merge des Pull Requests und einer grünen Pipeline auf `dev` `main`
   auf diesen Stand vorziehen. Das Ruleset von `main` erlaubt nur einen
   Fast-Forward und akzeptiert den Push, weil die Prüfungen auf genau diesem
   Commit bestanden haben. Dann den Commit mit einem annotierten Tag versehen
   und den Tag pushen. Die Nachricht des Tags wird zum Upload-Kommentar im TER:

   ```bash
   git fetch origin
   git push origin origin/dev:main
   git tag -a 1.2.0 -m "Kurze Zusammenfassung des Releases" origin/dev
   git push origin 1.2.0
   ```

   Der Workflow `Publish` (`.github/workflows/publish.yml`) prüft, dass der Tag
   auf `main` liegt und jede Datei seine Version trägt, und lädt das Archiv des
   Tags mit tailor ins TER hoch. Das Archiv enthält dieselben Dateien wie das
   Composer-Paket (`export-ignore` in `.gitattributes`). Packagist und
   docs.typo3.org aktualisieren sich über ihre Webhooks.

4. Die nächste Version mit einem Pull Request gegen `dev` beginnen, z. B. mit
   `php Build/Scripts/setVersion.php 1.2.1-dev`. In `CHANGELOG.md` muss kein
   Abschnitt angelegt werden: Der nächste Eintrag entsteht aus den Commits.

Tags von Vorabversionen wie `1.2.0-rc1` werden nicht ins TER hochgeladen, das
nur Versionen wie `1.2.0` annimmt; Packagist bietet sie trotzdem an.

Für den Upload braucht es ein TER-Zugriffstoken für den Extension-Key
`be_groups` als Secret `TYPO3_API_TOKEN` der GitHub-Umgebung `ter`. Wie man
eines anlegt, beschreibt die
[Dokumentation von tailor](https://docs.typo3.org/other/typo3/tailor/main/en-us/Index.html).
