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

#.  Zuerst das Datenbankschema aktualisieren (:guilabel:`Admin Tools >
    Maintenance > Analyze Database Structure`). Die alten Spalten dabei
    **noch nicht** entfernen – der Wizard liest sie noch.
#.  Den Wizard unter :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` oder
    auf der Kommandozeile ausführen:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run beGroups_kindMigration

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

*   **Rollen werden repariert:** Das Feld :sql:`subgroup` früherer
    META-Gruppen wird zur Vereinigung aus :sql:`subgroup` und allen alten
    :sql:`subgroup_*`-Spalten. Frühere Versionen konnten :sql:`subgroup` in
    manchen Situationen leeren; der Wizard stellt es wieder her und meldet
    die Abweichungen.
*   **Dateirechte bekommen einen eigenen Baustein:** Frühere Gruppen vom Typ
    „Rights“ und „File mount“ mit Dateirechten erhalten einen neuen Baustein
    vom Typ ``file_operations``. Er wird automatisch in jede Rolle
    eingehängt, die die ursprüngliche Gruppe enthielt, damit niemand Rechte
    verliert.

Der Wizard kann mehrfach laufen; er ändert nur, was noch nicht migriert ist.

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
