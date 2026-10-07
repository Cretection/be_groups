..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

Der vollständige Changelog wird in
`CHANGELOG.md <https://github.com/Cretection/be_groups/blob/main/CHANGELOG.md>`__
im Repository gepflegt (auf Englisch).

..  _changelog-1-0-0:

1.0.0 (noch nicht veröffentlicht)
=================================

Relaunch für TYPO3 14.3 LTS.

*   Neues Typenmodell nach der offiziellen TYPO3-Empfehlung: Rollen,
    Zugriffsrechte, Seitenrechte-Gruppen, Seitenbaum-Einstiegspunkte,
    Dateifreigaben, Dateioperationen, Kategorie-Freigaben, Sprachen,
    TSconfig, Workspace und Klassisch.
*   Rollen werden im Core-Feld :sql:`subgroup` zusammengesetzt. Die
    zusätzlichen Spalten :sql:`subgroup_*` früherer Versionen entfallen; der
    Upgrade-Wizard ``beGroups_kindMigration`` überträgt sie.
*   Die Regeln R1 – R4 werden bei jedem Speichern durchgesetzt, siehe
    :ref:`concept-rules`.
*   Die Konsistenzprüfung ``begroups:audit`` findet alles, was nicht dem
    Rollenmodell folgt, mit Exit-Codes für Deployments und Monitoring und
    dem Event ``AfterAuditFindingsCollectedEvent`` für Prüfungen anderer
    Extensions, siehe :ref:`usage-audit`.
*   Die Assistenten ``begroups:classify`` und ``begroups:split`` stellen
    klassische Gruppen auf Bausteine und Rollen um, ohne wirksame Rechte zu
    verändern, siehe :ref:`migration-vanilla`.
*   Nur noch TYPO3 14.3 LTS und PHP 8.2 – 8.5.
*   Die Lizenz ist jetzt GPL-2.0-or-later, wie beim TYPO3 Core.
*   :file:`ext_emconf.php` entfällt; alle Metadaten stehen in der
    :file:`composer.json`.

..  _changelog-history:

Frühere Versionen
=================

0.0.1 – 0.0.9 (2022)
    Wiederbelebung für TYPO3 11 durch Jonathan Starck.

1.x (2012 – 2017)
    Ursprüngliche Extension von Michael Klapper, von AOE gepflegt für
    TYPO3 4.x bis 8 LTS. Siehe :ref:`credits`.
