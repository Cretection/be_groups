# Security Policy

🇬🇧 English · 🇩🇪 [Deutsch](#sicherheitsrichtlinie)

be_groups manages backend permissions. Security issues in this extension can
directly lead to privilege escalation, so we take them very seriously.

## Supported versions

| Version | TYPO3                                   | PHP     | Supported until                     |
|---------|-----------------------------------------|---------|-------------------------------------|
| 1.x     | 14 LTS, and 15 as soon as it is released | 8.2–8.5 | 2029-06-30 (end of support TYPO3 14) |
| 0.0.x   | 11.5                                    | 7.4–8.2 | :x: not supported                   |

be_groups supports every TYPO3 version until the official end of its free
community support as published on https://get.typo3.org. Extended Long Term
Support (ELTS) does not extend this period. Each major version of be_groups
supports two consecutive major versions of TYPO3; when a TYPO3 version is
dropped in a new major version, the previous major version of be_groups keeps
receiving bug and security fixes until that TYPO3 version reaches its end of
support.

## Reporting a vulnerability

**Do not report security issues in public GitHub issues, pull requests or
discussions.**

Report vulnerabilities privately to the **TYPO3 Security Team** at
<security@typo3.org>, as required by the TYPO3 security policy for
extensions in the TYPO3 Extension Repository. Please include:

- the affected version of be_groups and of TYPO3,
- a description of the issue and its impact,
- steps to reproduce or a proof of concept,
- your name for the credits (optional).

The TYPO3 Security Team coordinates the fix with the maintainer and publishes
a security advisory. See the
[TYPO3 Security Team](https://typo3.org/community/teams/security) page for
details on the process.

You may additionally inform the maintainer at <info@cretection.it>.

---

## Sicherheitsrichtlinie

be_groups verwaltet Backend-Rechte. Sicherheitslücken in dieser Extension
können direkt zu einer Rechteausweitung führen. Wir nehmen sie deshalb sehr
ernst.

### Unterstützte Versionen

| Version | TYPO3                                    | PHP     | Unterstützt bis                          |
|---------|------------------------------------------|---------|------------------------------------------|
| 1.x     | 14 LTS, und 15, sobald es erschienen ist | 8.2–8.5 | 30.06.2029 (Support-Ende TYPO3 14)       |
| 0.0.x   | 11.5                                     | 7.4–8.2 | :x: nicht unterstützt                    |

be_groups unterstützt jede TYPO3-Version bis zum offiziellen Ende ihres
kostenlosen Community-Supports laut https://get.typo3.org. Der
kostenpflichtige Extended Long Term Support (ELTS) verlängert diesen Zeitraum
nicht. Jede Hauptversion von be_groups unterstützt zwei aufeinanderfolgende
TYPO3-Hauptversionen; fällt eine TYPO3-Version mit einer neuen Hauptversion
weg, erhält die vorherige Hauptversion von be_groups bis zum Support-Ende
dieser TYPO3-Version weiter Fehler- und Sicherheitskorrekturen.

### Eine Sicherheitslücke melden

**Melden Sie Sicherheitslücken nicht in öffentlichen GitHub-Issues, Pull
Requests oder Diskussionen.**

Melden Sie Sicherheitslücken vertraulich an das **TYPO3 Security Team** unter
<security@typo3.org>, wie es die TYPO3-Sicherheitsrichtlinie für Extensions
im TYPO3 Extension Repository vorsieht. Bitte geben Sie an:

- die betroffene Version von be_groups und von TYPO3,
- eine Beschreibung der Lücke und ihrer Auswirkung,
- Schritte zum Nachstellen oder einen Proof of Concept,
- Ihren Namen für die Danksagung (optional).

Das TYPO3 Security Team koordiniert die Behebung mit dem Maintainer und
veröffentlicht ein Security Advisory. Details zum Ablauf stehen auf der Seite
des [TYPO3 Security Team](https://typo3.org/community/teams/security).

Zusätzlich können Sie den Maintainer unter <info@cretection.it> informieren.
