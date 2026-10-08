..  include:: /Includes.rst.txt

..  _migration:

============================
Bestehende Gruppen umstellen
============================

..  note::

    Daten früherer Versionen dieser Extension (0.0.x für TYPO3 11, die
    AOE-Version 1.x) werden nicht übernommen. Installieren Sie die Extension
    in einer TYPO3-14-Installation ohne sie.

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
    oder Einträgen, die TYPO3 und das Backend-Formular unterschiedlich lesen
    (etwa ``be_groups_5`` oder ``05``).

``begroups:split`` teilt die per uid genannten Gruppen auf, oder mit
``--all`` jede Gruppe, für die ``begroups:classify`` das Aufteilen vorschlägt:

*   Die Rechte wandern in je einen Baustein pro Typ. Die Bausteine erhalten
    den Titel der Gruppe; TYPO3 zeigt ihren Typ als Präfix, zum Beispiel
    ``ACL: Editors`` und ``DBM: Editors`` (siehe :ref:`usage-prefixes`).
    Eine versteckte Gruppe bleibt versteckt; ihre Bausteine
    sind nur über sie erreichbar, sodass sie beim Wiedereinblenden die
    Rechte wie vorher gewährt.
*   **Die Gruppe behält ihre uid und wird zur Rolle.** Benutzer und andere
    Gruppen behalten ihre Zuweisungen. Die neuen Bausteine folgen auf die
    bisherigen Untergruppen, damit der Vorrang des TSconfig gleich bleibt.
*   TYPO3 macht die erste Gruppe eines Benutzers zur Eigentümergruppe der
    Seiten, die er anlegt. Ist das die Gruppe selbst – sie hat keine aktiven
    Untergruppen –, wird eine neue Seitenrechte-Gruppe ohne Rechte (zum
    Beispiel ``PG: Editors``) ihr erstes Mitglied und übernimmt diese
    Aufgabe. Sie gehört nur zur Rolle, die Eigentümergruppe hat also genau
    dieselben Mitglieder wie vorher.
*   Die Bausteine werden immer neu angelegt, auch wenn es einen gleichen
    gibt. Eine bestehende Gruppe kann anderswo referenziert sein – als
    Eigentümergruppe von Seiten, als Workspace-Mitglied oder in
    TSconfig-Bedingungen –, mehr Mitglieder könnten dadurch mehr Rechte
    erhalten. Führen Sie gleiche Bausteine selbst zusammen, wo das gewollt
    ist.

Beide Assistenten schreiben über den DataHandler: Jede Änderung steht im
Systemprotokoll und in der Historie des Datensatzes. Jede Gruppe wird in
einer Transaktion umgestellt, die nur abgeschlossen wird, wenn

*   alle anderen Gruppen und alle Benutzer unverändert sind und sich an der
    Gruppe selbst nur Typ, Untergruppen und die in Bausteine verschobenen
    Rechte geändert haben, und
*   TYPO3 jeder Gruppen-Kombination eines Benutzers und der Gruppe selbst
    (auch versteckt oder noch nicht zugewiesen) dasselbe gewährt wie vorher:
    die zusammengeführten Rechte, die Workspace-Rechte, das TSconfig in der
    Reihenfolge, in der TYPO3 es anwendet, die Gruppenmitgliedschaften bis
    auf die neuen Bausteine und die Eigentümergruppe neuer Seiten bis auf die
    neue Seitenrechte-Gruppe.

Andernfalls – zum Beispiel, weil eine andere Extension beim Speichern Daten
ändert – wird die Transaktion zurückgerollt und die Gruppe bleibt
unverändert. Das gilt auch für Datenbankfehler; das Ergebnis nennt die vom
DataHandler protokollierten Fehler, da auch das Systemprotokoll
zurückgerollt wird. Unter PostgreSQL nennt das Ergebnis nur die
abgebrochene Transaktion.

Grenzen der Prüfung:

*   TYPO3 löst Gruppen über interne API auf, daher verwendet die Extension
    ein Modell davon; Tests vergleichen das Modell mit TYPO3 selbst.
*   Bedingungen, die die vollständige Gruppenliste eines Benutzers
    vergleichen (zum Beispiel ``backend.user.userGroupList``), sehen auch
    die neuen Bausteine. Gruppen, die Listener des Core-Events
    ``AfterGroupsResolvedEvent`` zur Laufzeit ergänzen (etwa Single
    Sign-on), kennt die Prüfung nicht.
*   Die Transaktion umfasst die Datenbankverbindung von :sql:`be_groups`:
    Sind :sql:`sys_log`, :sql:`sys_history` oder :sql:`sys_refindex` einer
    anderen Verbindung zugeordnet, bleiben deren Einträge einer
    zurückgerollten Umstellung erhalten.

Danach:

#.  Das Ergebnis mit ``begroups:audit`` (siehe :ref:`usage-audit`) und im
    Modul *Rollen & Bausteine* prüfen.
#.  Rollen auflösen, die Rollen enthalten (``role-invalid-member``). Sie
    entstehen, wenn eine zusammenfassende Gruppe gemischte Gruppen enthielt,
    die nach dem Aufteilen Rollen sind. Die Rechte sind unverändert; die
    Assistenten lösen das nicht selbst auf, weil die innere Rolle Seiten
    besitzen oder Workspace-Mitglied sein kann. Ersetzen Sie die innere
    Rolle durch ihre Bausteine, wo das passt.
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
eigenen Bausteinen: einer Seitenrechte-Gruppe als Eigentümerin neuer
Seiten, Zugriffsrechten, Seitenbaum-Einstiegspunkt und Dateifreigabe
(``PG: Editor``, ``ACL: Editor``, ``DBM: Editor`` und ``FM: Editor``).
