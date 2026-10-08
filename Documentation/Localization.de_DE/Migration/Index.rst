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
funktionieren unverändert. Zwei Assistenten stellen sie um, ohne wirksame
Rechte zu verändern. Beide zeigen ihren Plan zuerst mit ``--dry-run``:

..  code-block:: bash

    vendor/bin/typo3 begroups:classify --dry-run
    vendor/bin/typo3 begroups:classify
    vendor/bin/typo3 begroups:split --all --dry-run
    vendor/bin/typo3 begroups:split --all
    vendor/bin/typo3 begroups:audit

``begroups:classify`` schlägt für jede klassische Gruppe einen Typ vor und
ändert den Typ dort, wo sich sonst nichts ändert:

*   Eine Gruppe, deren Rechte alle zu einem Baustein-Typ gehören – zum
    Beispiel nur Seitenbaum-Einstiegspunkte –, wird zu diesem Baustein,
    sofern sie keinem Benutzer direkt zugewiesen ist.
*   Eine Gruppe, die selbst nichts gewährt, aber andere Gruppen
    zusammenfasst, wird zur Rolle.
*   Eine Gruppe, die nichts gewährt, aber Seiten besitzt, wird zur
    Seitenrechte-Gruppe.
*   Alle anderen Gruppen werden aufgelistet: Gruppen zum Aufteilen und
    Gruppen, über die Sie entscheiden müssen, jeweils mit Begründung – zum
    Beispiel eine leere Gruppe, ein Rechtefeld, das zu keinem Typ gehört,
    die Wurzel des Seitenbaums als Freigabe oder Untergruppen mit Duplikaten
    oder Einträgen, die TYPO3 ignoriert (etwa ``be_groups_5``).

``begroups:split`` teilt die per uid genannten Gruppen auf, oder mit
``--all`` jede Gruppe, für die ``begroups:classify`` das Aufteilen vorschlägt:

*   Die Rechte wandern in je einen Baustein pro Typ, benannt mit dem Präfix
    des Typs und dem Titel der Gruppe, zum Beispiel ``ACL_Editors`` und
    ``DBM_Editors``. Eine versteckte Gruppe bleibt versteckt; ihre Bausteine
    sind nur über sie erreichbar, sodass sie beim Wiedereinblenden die
    Rechte wie vorher gewährt.
*   **Die Gruppe behält ihre uid und wird zur Rolle.** Benutzer und andere
    Gruppen behalten ihre Zuweisungen. Die neuen Bausteine folgen auf die
    bisherigen Untergruppen, damit der Vorrang des TSconfig gleich bleibt.
*   Die Bausteine werden immer neu angelegt, auch wenn es einen gleichen
    gibt. Eine bestehende Gruppe kann anderswo referenziert sein – als
    Eigentümergruppe von Seiten, als Workspace-Mitglied oder in
    TSconfig-Bedingungen –, mehr Mitglieder könnten dadurch mehr Rechte
    erhalten. Führen Sie gleiche Bausteine selbst zusammen, wo das gewollt
    ist.

Beide Assistenten schreiben über den DataHandler: Jede Änderung steht im
Systemprotokoll und in der Historie des Datensatzes. Jede Gruppe wird in
einer Transaktion umgestellt. Vor dem Abschluss ermittelt die Extension,
was TYPO3 jeder Gruppen-Kombination eines Benutzers und der Gruppe selbst
(auch versteckt oder noch nicht zugewiesen) vorher und nachher gewährt: die
zusammengeführten Rechte, die Workspace-Rechte, das TSconfig in der
Reihenfolge, in der TYPO3 es anwendet, die Gruppenmitgliedschaften und die
Eigentümergruppe neuer Seiten. Unterscheidet sich etwas – zum Beispiel, weil
eine andere Extension beim Speichern Werte ändert –, wird die Transaktion
zurückgerollt und die Gruppe bleibt unverändert. Das gilt auch für
Datenbankfehler; das Ergebnis nennt den Fehler, da auch die Einträge im
Systemprotokoll zurückgerollt werden.

TYPO3 löst Gruppen über interne API auf, daher verwendet die Extension ein
Modell davon; Tests vergleichen das Modell mit TYPO3 selbst. Die Transaktion
umfasst die Datenbankverbindung von :sql:`be_groups`: Sind :sql:`sys_log`,
:sql:`sys_history` oder :sql:`sys_refindex` einer anderen Verbindung
zugeordnet, bleiben deren Einträge einer zurückgerollten Umstellung
erhalten.

Danach:

#.  Das Ergebnis mit ``begroups:audit`` (siehe :ref:`usage-audit`) und im
    Modul *Rollen & Bausteine* prüfen.
#.  Über die Gruppen entscheiden, die die Assistenten nicht umstellen
    konnten.
#.  Klassische Gruppen abschalten, siehe :ref:`configuration`.

..  attention::

    Wenn Sie den Typ einer Gruppe von Hand ändern, werden alle Felder
    geleert, die nicht zum neuen Typ gehören (Regel R1). Nutzen Sie lieber
    die Assistenten, oder prüfen Sie die Gruppe, bevor Sie ihren Typ ändern.

..  _migration-starter:

Mit den Standardgruppen von TYPO3 beginnen
==========================================

TYPO3 legt die beiden empfohlenen Gruppen „Editor“ und „Advanced Editor“ auf
der Kommandozeile an. Teilen Sie sie danach auf, um mit zwei Rollen zu
beginnen:

..  code-block:: bash

    vendor/bin/typo3 setup:begroups:default --groups=Both
    vendor/bin/typo3 begroups:split --all

Das Ergebnis sind die Rollen „Editor“ und „Advanced Editor“ mit jeweils
eigenen Bausteinen für Zugriffsrechte, Seitenbaum-Einstiegspunkt und
Dateifreigabe (zum Beispiel ``ACL_Editor``, ``DBM_Editor`` und
``FM_Editor``).
