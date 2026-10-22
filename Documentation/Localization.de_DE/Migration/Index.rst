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

Nach der Installation sind alle Gruppen vom Typ
„Klassisch (alle Einstellungen)“ und funktionieren unverändert. Zwei
Assistenten stellen sie um, ohne wirksame Rechte zu verändern. Beide zeigen
ihren Plan zuerst mit ``--dry-run``:

..  code-block:: bash

    vendor/bin/typo3 begroups:classify --dry-run
    vendor/bin/typo3 begroups:classify
    vendor/bin/typo3 begroups:split --all --dry-run
    vendor/bin/typo3 begroups:split --all
    vendor/bin/typo3 begroups:audit

``begroups:classify`` schlägt für jede klassische Gruppe einen Typ vor und
ändert den Typ dort, wo sich sonst nichts ändert:

*   Eine Gruppe, deren Rechte alle zu einem Baustein-Typ gehören, wird zu
    diesem Baustein, sofern sie keinem Benutzer direkt zugewiesen ist – zum
    Beispiel wird eine Gruppe, die nur Seitenbaum-Einstiegspunkte hat, zu
    einer „Seitenbaum-Freigabe“.
*   Eine Gruppe, die selbst nichts gewährt, aber andere Gruppen
    zusammenfasst, wird zur „Rolle“. Eine Gruppe, die nichts gewährt, nichts
    zusammenfasst und keine Seiten besitzt, aber Benutzern direkt zugewiesen
    ist, wird ebenfalls zur „Rolle“; sie behält ihre Benutzer und alle
    Verweise auf ihre uid, etwa als Workspace-Mitglied oder Verantwortliche
    einer Stufe.
*   Eine Gruppe, die nichts gewährt, aber Seiten besitzt, wird zur
    „Seitenrechte-Gruppe“, sofern sie keinem Benutzer direkt zugewiesen ist.
*   Alle anderen Gruppen werden aufgelistet: Gruppen zum Aufteilen und
    Gruppen, über die Sie entscheiden müssen, jeweils mit Begründung – zum
    Beispiel eine leere Gruppe, die keinem Benutzer direkt zugewiesen ist,
    ein Rechtefeld, das zu keinem Typ gehört, die Wurzel des Seitenbaums als
    Freigabe, Untergruppen mit Duplikaten oder Einträgen, die TYPO3 und das
    Backend-Formular unterschiedlich lesen (etwa ``be_groups_5`` oder
    ``05``), oder Seiten-, Datei- oder Kategoriefreigaben mit Einträgen, die
    der DataHandler nicht in einen Baustein übernehmen kann (etwa ``05``,
    ``pages_5`` oder leere Einträge).

``begroups:split`` teilt die per uid genannten Gruppen auf, oder mit
``--all`` jede Gruppe, für die ``begroups:classify`` das Aufteilen vorschlägt:

*   Die Rechte wandern in je einen Baustein pro Typ. Die Bausteine erhalten
    den Titel der Gruppe; TYPO3 zeigt ihren Typ als Präfix, zum Beispiel
    ``ACL: Editors`` und ``DBM: Editors`` (siehe :ref:`usage-prefixes`).
    Ihre Beschreibung nennt die Gruppe, zum Beispiel
    ``Created by begroups:split from group 17 "Editors".``, immer auf
    Englisch wie die Ausgaben der Befehle, weil sie mit dem Datensatz
    gespeichert wird. Eine versteckte Gruppe bleibt versteckt; ihre Bausteine
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

Beide Assistenten zeigen ihren Plan als Tabelle, geben das Ergebnis jeder
Gruppe aus, die sie umstellen – umgestellt oder fehlgeschlagen, mit
Begründung –, und schließen mit einer Zusammenfassung. ``begroups:split``
nennt außerdem jede per uid angegebene Gruppe, die es überspringt, mit
Begründung; für eine Gruppe, die nicht aufgeteilt werden muss, nennt es den
Typ, in den ``begroups:classify`` sie umstellt. Der Exit-Code ist ``1``,
wenn eine Umstellung fehlgeschlagen ist oder eine per uid angegebene Gruppe
sich nicht aufteilen lässt (auch mit ``--dry-run``), ``2`` bei ungültigen
Argumenten von ``begroups:split`` und sonst ``0``. Eine uid ist eine ganze
Zahl von 1 bis 2147483647, der größten uid, die jede Datenbank von TYPO3
speichern kann.

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

Andernfalls – zum Beispiel, weil eine andere Extension beim Speichern eine
Gruppe oder einen Benutzer ändert – wird die Transaktion zurückgerollt und
die Gruppe bleibt unverändert; das Ergebnis nennt den Benutzer oder die
Gruppe, die sich geändert hat, und die geänderten Felder. Das gilt auch für
Datenbankfehler; das Ergebnis nennt die ersten drei vom DataHandler
protokollierten Fehler und zählt die übrigen, da auch das Systemprotokoll
zurückgerollt wird. Unter PostgreSQL nennt das Ergebnis nur die
abgebrochene Transaktion. Bricht ein anderer Fehler eine Umstellung ab,
zum Beispiel eine Exception einer anderen Extension, wird auch sie
zurückgerollt: Der Befehl nennt die Gruppe als fehlgeschlagen, zählt die
nicht bearbeiteten Gruppen, zeigt den Fehler und endet mit einem Exit-Code
ungleich ``0``.

Cache-Befehle aus dem Page-TSconfig (``TCEMAIN.clearCacheCmd``) laufen erst
nach dem Abschluss der Transaktion: Das Leeren von Caches würde die
Transaktion unter MySQL und MariaDB vorzeitig beenden. Schlagen die
Cache-Befehle nach dem Abschluss fehl, bleibt die Gruppe umgestellt, und das
Ergebnis weist darauf hin; leeren Sie die Caches dann selbst.

Grenzen der Prüfung:

*   TYPO3 löst Gruppen über interne API auf, daher verwendet die Extension
    ein Modell davon; Tests vergleichen das Modell mit TYPO3 selbst.
*   Bedingungen, die die vollständige Gruppenliste eines Benutzers
    vergleichen (zum Beispiel ``backend.user.userGroupList``), sehen auch
    die neuen Bausteine. Gruppen, die Listener des Core-Events
    ``AfterGroupsResolvedEvent`` zur Laufzeit ergänzen (etwa Single
    Sign-on), kennt die Prüfung nicht.
*   Verglichen werden nur Gruppen und Benutzer. Was eine andere Extension
    beim Umstellen einer Gruppe in anderen Tabellen ändert – zum Beispiel
    einen neuen Baustein zum Workspace-Mitglied oder zur Eigentümergruppe von
    Seiten machen –, wird mit der Umstellung gespeichert, auch wenn es den
    Benutzern der Gruppe mehr gewährt.
*   Die Transaktion umfasst die Datenbankverbindung von :sql:`be_groups`:
    Sind :sql:`sys_log`, :sql:`sys_history` oder :sql:`sys_refindex` einer
    anderen Verbindung zugeordnet, bleiben deren Einträge einer
    zurückgerollten Umstellung erhalten.

Danach:

#.  Das Ergebnis mit ``begroups:audit`` (siehe :ref:`usage-audit`) und im
    Modul *Rollen & Bausteine* prüfen.
#.  Rollen auflösen, die Rollen enthalten (``role-invalid-member``). Sie
    entstehen, wenn eine zusammenfassende Gruppe gemischte Gruppen enthielt,
    die nach dem Aufteilen Rollen sind. Die Rechte sind unverändert, aber
    ``begroups:audit`` meldet jede dieser Rollen als Fehler und endet mit
    dem Exit-Code ``1``, bis sie aufgelöst sind; ein Deployment, das die
    Prüfung ausführt, bricht also dort ab. Die Assistenten lösen das nicht
    selbst auf, weil die innere Rolle Seiten besitzen, Workspace-Mitglied
    sein oder in TSconfig-Bedingungen verwendet werden kann. Prüfen Sie das
    zuerst: Ohne die innere Rolle verlieren die Benutzer der äußeren Rolle
    diesen Zugriff, und Bedingungen auf die innere Rolle treffen nicht mehr
    zu.

    Ersetzen Sie die innere Rolle durch ihre Bausteine, wo das passt, und
    zwar an der Stelle der inneren Rolle. Das Feld hängt ausgewählte
    Einträge hinten an. Schieben Sie die Bausteine deshalb vor dem Speichern
    mit :guilabel:`Ausgewählte Objekte nach oben verschieben` bis zur
    inneren Rolle – oder mit
    :guilabel:`Ausgewählte Objekte zum Anfang verschieben`, wenn die innere
    Rolle das erste Mitglied ist. Die erste Gruppe eines Benutzers wird
    Eigentümergruppe der Seiten, die er anlegt, und die Reihenfolge
    bestimmt den Vorrang des TSconfig. Prüfen Sie den Vorrang danach im
    Modul.

    Umgestellte Rollen behalten außerdem Verweise auf Gruppen, die schon
    vorher gelöscht waren (``role-missing-member``, ebenfalls ein Fehler).
    TYPO3 ignoriert sie; Speichern der Rolle im Backend-Formular entfernt
    sie.
#.  Über die Gruppen entscheiden, die die Assistenten nicht umstellen
    konnten. Wählen Sie für eine leere Gruppe, die keinem Benutzer direkt
    zugewiesen ist, einen Typ; löschen Sie sie nur, wenn keine Gruppe, kein
    Workspace und keine TSconfig-Bedingung auf sie verweist. Eine Gruppe mit
    der Wurzel des Seitenbaums als Freigabe
    (``0``) lässt sich nicht aufteilen, weil TYPO3 die Wurzel nicht als
    Freigabe eines neuen Bausteins speichert. Behalten Sie sie als
    klassische Gruppe, oder ersetzen Sie die Wurzel durch die Seiten auf der
    obersten Ebene des Seitenbaums und teilen Sie die Gruppe dann auf. Das
    ist nicht ganz dasselbe: Seiten, die später auf der obersten Ebene
    entstehen, sind nicht freigegeben. Das Backend-Formular zeigt die Wurzel
    nicht als Freigabe: Wer eine solche Gruppe oder einen Baustein vom Typ
    „Seitenbaum-Freigabe“ mit der Wurzel im Formular speichert, entfernt
    sie.
#.  Klassische Gruppen abschalten, siehe :ref:`configuration`, sobald
    ``begroups:audit`` keine klassische Gruppe (``classic-group``) mehr
    meldet. Verbliebene klassische Gruppen wirken weiter, werden ab dann
    aber als Fehler gemeldet (``classic-group-disabled``).

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
eigenen Bausteinen der Typen „Seitenrechte-Gruppe“ (die Eigentümergruppe
neuer Seiten), „Zugriffsrechte“, „Seitenbaum-Freigabe“ und „Dateifreigabe“:
``PG: Editor``, ``ACL: Editor``, ``DBM: Editor`` und ``FM: Editor``.

Die Vorlagen von TYPO3 erlauben die Live-Arbeitsumgebung nicht. Mit
EXT:workspaces ergänzen Sie beide Rollen um einen Baustein vom Typ
„Workspace“, der sie erlaubt (:guilabel:`Live bearbeiten (Online)`); sonst
sehen ihre Benutzer kein Modul, siehe :ref:`concept-combination`.

..  attention::

    Teilen Sie die beiden Gruppen auf, bevor Sie sie bearbeiten. TYPO3
    speichert in ihrem Feld :sql:`file_permissions` keinen Wert, und das
    gewährt keine Dateioperationen. Das Backend-Formular zeigt stattdessen
    den Standardwert des Felds, alle Dateioperationen. Wer eine der Gruppen
    im Formular speichert – auch ohne Änderung –, gewährt ihren Benutzern
    damit alle Dateioperationen. Die Bausteine aus dem Aufteilen speichern
    keine Dateioperationen.
