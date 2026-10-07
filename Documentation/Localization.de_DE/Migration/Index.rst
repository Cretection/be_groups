..  include:: /Includes.rst.txt

..  _migration:

=========
Migration
=========

..  _migration-legacy:

Von be_groups 0.0.x oder der AOE-Version 1.x
============================================

Frühere Versionen speicherten den Typ als Zahl und die Zusammensetzung von
META-Gruppen in zusätzlichen Spalten (:sql:`subgroup_r`, :sql:`subgroup_pm`,
…). Der Upgrade-Wizard :guilabel:`Migrate be_groups kinds`
(Kennung ``beGroups_kindMigration``) überträgt diese Daten:

#.  Zuerst die Datenbankstruktur aktualisieren: alle Änderungen unter
    :guilabel:`Admin Tools > Maintenance > Analyze Database Structure`
    übernehmen, insbesondere die Umstellung von :sql:`tx_begroups_kind` von
    einer Zahl auf eine Textspalte. Die alten Spalten :sql:`subgroup_*` dabei
    **noch nicht** entfernen – der Wizard liest sie noch für seinen Bericht.
    Ist die Spalte noch numerisch, verweigert der Wizard die Ausführung und
    erklärt den Grund: Die neuen Typen in einer numerischen Spalte zu
    speichern, würde bei jeder Gruppe ``0`` ablegen.
#.  Den Wizard unter :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` oder
    auf der Kommandozeile ausführen:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run beGroups_kindMigration

    Lesen Sie die Ausgabe – sie nennt jede Gruppe, die Ihre Aufmerksamkeit
    braucht.
#.  Den Referenzindex aktualisieren:

    ..  code-block:: bash

        vendor/bin/typo3 referenceindex:update

#.  Den Datenbank-Vergleich erneut ausführen und die alten Spalten
    :sql:`subgroup_*` entfernen.

Was der Wizard macht:

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Alter Typ
        -   Neuer Typ
    *   -   0 „Default all“
        -   ``classic``
    *   -   1 „Rights“
        -   ``acl``
    *   -   2 „Language“
        -   ``language``
    *   -   3 „Meta“
        -   ``role``
    *   -   4 „Page access group“
        -   ``page_group``
    *   -   5 „File mount“
        -   ``file_mount``
    *   -   6 „Page mount“
        -   ``db_mount``
    *   -   7 „TSconfig“
        -   ``tsconfig``
    *   -   8 „Workspace“
        -   ``workspace``
    *   -   9 „Category“
        -   ``category_mount``

Der Wizard **ändert nie wirksame Rechte**:

*   **Rollen werden nicht verändert.** Das frühere Formular zeigte die
    Mitglieder von META-Gruppen in den Feldern :sql:`subgroup_*`, wirksam war
    aber nur :sql:`subgroup`, und frühere Versionen konnten es leeren. Der
    Wizard fügt die Mitglieder, die nur in den alten Feldern stehen, nicht
    hinzu – das würde Rechte vergeben, die die Benutzer heute nicht haben.
    Er meldet sie stattdessen; ergänzen Sie sie im Rollen-Formular, wenn sie
    gewollt sind.
*   **Dateirechte bekommen einen eigenen Baustein.** Gruppen mit Dateirechten,
    die ihr neuer Typ nicht anzeigt (in früheren Versionen oft die
    Standard-Dateirechte), erhalten einen neuen Baustein vom Typ
    ``file_operations`` mit genau diesen Rechten. Pro unterschiedlicher Rechte-Kombination und pro Zustand
    „deaktiviert“ entsteht ein Baustein – deaktivierte Gruppen bekommen einen
    deaktivierten Baustein, damit nichts Unwirksames wirksam wird. Der
    Baustein wird überall dort direkt hinter der ursprünglichen Gruppe
    eingefügt, wo diese verwendet wird: in Rollen, in anderen Gruppen und bei
    Backend-Benutzern. Eine frühere META-Gruppe bekommt den Baustein als
    eigenes Mitglied.
*   **Listen ohne Platz behalten den alten Stand.** Kann eine Liste, die eine
    solche Gruppe referenziert, keinen weiteren Eintrag aufnehmen, behält die
    Gruppe ihre Dateirechte und wird ``classic``. Der Wizard meldet das.
*   **Gruppen mit anderen Einstellungen werden klassisch.** Eine Gruppe mit
    Einstellungen, die ihr neuer Typ nicht anzeigt (zum Beispiel TSconfig an
    einer früheren „Rights“-Gruppe), wird ``classic`` und zur Prüfung
    gemeldet.
*   **Auch gelöschte Datensätze werden migriert**, damit eine später
    wiederhergestellte Gruppe oder ein wiederhergestellter Benutzer nicht den
    alten Stand zurückbringt.

Der Wizard ist wiederholbar: Er bearbeitet nur Gruppen, die noch einen
numerischen Typ haben, und läuft erneut, wenn solche Gruppen wieder
auftauchen, zum Beispiel nach dem Wiederherstellen gelöschter Datensätze aus
einer Sicherung.

..  note::

    Die frühere Extension-Einstellung ``onlyShowMetaGroup`` wird nicht
    übernommen. Ihr Nachfolger ist
    :ref:`allowClassicGroups <configuration-allowClassicGroups>`; der Wizard
    gibt einen Hinweis aus, wenn die alte Einstellung noch aktiv ist.
    Deaktivieren Sie „Klassische Gruppen erlauben“, damit Backend-Benutzer
    weiterhin nur Rollen bekommen.

..  _migration-vanilla:

Von einer TYPO3-Installation ohne diese Extension
=================================================

Nach der Installation sind alle Gruppen vom Typ „Klassisch“ und
funktionieren unverändert. Stellen Sie sie Schritt für Schritt um:

#.  Bausteine für Seitenbaum-Einstiegspunkte, Dateifreigaben, Sprachen und
    Zugriffsrechte anlegen – oder den Typ bestehender Gruppen ändern, die
    schon genau eine Aufgabe haben.
#.  Rollen anlegen, die diese Bausteine zusammenfassen.
#.  Die Rollen statt der klassischen Gruppen den Backend-Benutzern zuweisen.
#.  Klassische Gruppen abschalten, siehe :ref:`configuration`.

..  attention::

    Wenn Sie den Typ einer bestehenden Gruppe ändern, werden alle Felder
    geleert, die nicht zum neuen Typ gehören (Regel R1). Prüfen Sie die
    Gruppe, bevor Sie ihren Typ ändern.

Assistenten, die Typen vorschlagen und gemischte Gruppen automatisch
aufteilen, sind geplant, siehe :ref:`developer-planned`.
