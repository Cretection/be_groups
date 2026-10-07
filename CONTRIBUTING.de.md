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
```

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

## Pull Requests

1. Einen Branch von `main` anlegen und einen Pull Request gegen `main`
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
- [ ] `CHANGELOG.md` einen Eintrag hat und die Commit-Nachricht dem Format
      oben folgt.
- [ ] Eine zweite Person den Pull Request geprüft hat.

## Übersetzungen

Labels liegen in `Resources/Private/Language/` (XLIFF 1.2). Englisch ist die
Quellsprache; die deutschen Dateien (`de.*.xlf`) werden in diesem Repository
gepflegt. Bitte beide im selben Pull Request aktuell halten.

## Dokumentation

Das Handbuch ist in reStructuredText geschrieben, in `Documentation/`
(Englisch) und `Documentation/Localization.de_DE/` (Deutsch). Die Struktur
beider Handbücher bleibt identisch, damit Leser auf jeder Seite die Sprache
wechseln können.
