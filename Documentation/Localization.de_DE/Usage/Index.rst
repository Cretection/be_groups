..  include:: /Includes.rst.txt

..  _usage:

=========
Benutzung
=========

..  _usage-groups:

Bausteine und Rollen anlegen
============================

Backend-Benutzergruppen werden wie gewohnt auf der Wurzelebene des
Seitenbaums angelegt. Das erste Feld einer Gruppe ist ihr **Typ**. Nach der
Auswahl zeigt das Formular nur die Einstellungen dieses Typs:

*   Ein Baustein deckt genau ein Thema ab, zum Beispiel die
    Seitenbaum-Einstiegspunkte einer Website (*Seitenbaum-Freigabe*) oder die
    Module und Tabellen, die ein Redakteur braucht (*Zugriffsrechte*).
*   Eine Rolle hat keine eigenen Einstellungen. Sie listet ihre Bausteine und
    wird Backend-Benutzern zugewiesen.

Beim Speichern einer Gruppe werden alle Rechte-Einstellungen entfernt, die
nicht zu ihrem Typ gehören, auch Standardwerte. Das Systemprotokoll
(*Administration > Protokoll*) hält das als Information fest, siehe
:ref:`concept-rules`.

..  _usage-role-form:

Eine Rolle zusammenstellen
--------------------------

Das Feld :guilabel:`Bausteine` einer Rolle ist die zweispaltige Auswahlliste
des Core. Die verfügbaren Gruppen sind nach Typ gruppiert; Rollen und
klassische Gruppen stehen in eigenen Gruppen und sind als nicht zulässig
gekennzeichnet. Wird eine davon hinzugefügt, wird das beim Speichern der
Rolle abgelehnt. Die Reihenfolge der gewählten Bausteine bleibt erhalten –
sie bestimmt den Vorrang ihres TSconfig.

Eine Rolle kann viele Bausteine kombinieren: Die Extension vergrößert das
Feld :sql:`subgroup` auf 2048 Zeichen.

..  _usage-user-form:

Rollen an Benutzer vergeben
---------------------------

Das Feld :guilabel:`Gruppe` eines Backend-Benutzers listet alle Gruppen,
gruppiert in Rollen, klassische Gruppen und Bausteine je Typ. Bausteine sind
mit „über eine Rolle zuweisen“ gekennzeichnet; eine direkte Zuweisung wird
beim Speichern des Benutzers abgelehnt.

Keine der beiden Listen ist gefiltert. Eine gefilterte Liste würde beim
Speichern jeden gespeicherten Wert verwerfen, der nicht in ihr enthalten ist –
mit der vollständigen Liste entfernt das Speichern eines Formulars nie eine
bestehende Zuweisung.

..  _usage-prefixes:

Der Typ als Präfix
==================

Der Titel einer Gruppe muss nicht sagen, was die Gruppe ist: Wo immer TYPO3
eine Gruppe anzeigt, steht ihr Typ automatisch vor dem Titel. Eine Rolle
„Redakteure“ erscheint als ``META: Redakteure``, ihr
Seitenbaum-Einstiegspunkt als ``DBM: Redakteure``.

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   - Präfix
        - Typ
    *   - ``META``
        - Rolle
    *   - ``ACL``
        - Zugriffsrechte
    *   - ``PG``
        - Seitenrechte-Gruppe
    *   - ``DBM``
        - Seitenbaum-Einstiegspunkte
    *   - ``FM``
        - Dateifreigaben
    *   - ``FO``
        - Dateioperationen
    *   - ``CM``
        - Kategorie-Freigaben
    *   - ``L``
        - Sprachen
    *   - ``TS``
        - TSconfig
    *   - ``WS``
        - Workspace

Klassische Gruppen und Gruppen mit unbekanntem Typ haben kein Präfix. Typen
anderer Extensions verwenden ihre Bezeichnung.

Das Präfix erscheint in den Gruppen- und Benutzerformularen, in den
Datensatzlisten, im Rechte-Modul, bei Eigentümern und Mitgliedern von
Workspaces, im Element-Browser, im Core-Modul :guilabel:`Benutzer`
(Benutzer- und Gruppenlisten, Gruppenfilter, Details und Vergleich) und im
Modul :ref:`Rollen & Bausteine <usage-module>` überall dort, wo der
Abschnitt den Typ nicht schon nennt.

..  note::

    Das Core-Modul :guilabel:`Benutzer` gibt die Titel selbst aus. Die
    Extension überschreibt daher diejenigen seiner Templates, die
    Gruppentitel ausgeben, mit Kopien der Templates von TYPO3 14.3, in denen
    sich nur die Titel unterscheiden. Die Kopien werden nur verwendet,
    solange die Templates des Core unverändert sind: Ändert ein TYPO3-Update
    eines davon, verwendet das Modul wieder die Templates des Core und zeigt
    die Titel ohne Präfix, bis eine neue Version dieser Extension die Kopien
    aktualisiert. TYPO3 prüft das beim Aufbau des Page-TSconfig; leeren Sie
    nach einem Patch an Templates des Core ohne neue TYPO3-Version die
    Caches.

Für andere Präfixe überschreiben Sie die Labels ``kind.prefix.<kind>`` aus
:file:`EXT:be_groups/Resources/Private/Language/db.xlf` per
``$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']``.

..  _usage-module:

Das Modul „Rollen & Bausteine“
==============================

Administratoren finden das Modul *Administration > Rollen & Bausteine* neben
dem Modul *Benutzer*. Es ändert nichts, sondern zeigt:

*   jede Rolle mit ihren Bausteinen, gruppiert nach Typ, und ihren Benutzern,
*   jeden Baustein mit den Rollen und anderen Gruppen, die ihn verwenden, und
    den Benutzern, denen er direkt zugewiesen ist,
*   die verbliebenen klassischen Gruppen,
*   Gruppen mit unbekanntem Typ, zum Beispiel dem Typ einer deinstallierten
    Extension, mit Warnung.

Rollen lassen sich nach Titel oder Anzahl der Benutzer sortieren, Bausteine
nach Titel oder Verwendung. Bausteine lassen sich nach Typ filtern; die
Auswahl bleibt pro Benutzer gespeichert.

Einträge, die nicht dem Rollenmodell folgen, sind markiert: Rollen mit
Gruppen, die keine Bausteine sind oder nicht mehr existieren, Bausteine mit
Untergruppen, Bausteine, die Benutzern direkt zugewiesen sind, und Gruppen
mit unbekanntem Typ. Die Anzahl dieser Einträge steht oben und hängt nicht
vom Typ-Filter ab. Bausteine, die keine Gruppe und kein Benutzer verwendet,
sind als unbenutzt markiert.

Ein Mitglied einer Rolle, das kein Baustein ist, ist mit „kein Baustein“
markiert und wird mit seinem Typ angezeigt, zum Beispiel ``META: Redakteure``
für eine Rolle in einer Rolle. TYPO3 gewährt seine Rechte weiterhin; ersetzen
Sie es durch Bausteine, die dasselbe gewähren. Solche Rollen bleiben nach dem
Umstellen mit den Assistenten häufig übrig, siehe :ref:`migration-vanilla`.

Typen anderer Extensions werden mit ihrer eigenen Bezeichnung und ihrem
eigenen Icon angezeigt.

Ein Klick auf einen Titel öffnet die Gruppe oder den Benutzer im gewohnten
Formular. Die Schaltflächen im Kopfbereich legen eine neue Rolle oder einen
neuen Baustein an.

Das Modul ist optional. Um es auszublenden, ergänzen Sie seine Kennung im
User-TSconfig der Administratoren:

..  code-block:: typoscript
    :caption: User-TSconfig

    options.hideModules := addToList(begroups_overview)

..  _usage-audit:

Gruppen und Benutzer prüfen
===========================

Die Regeln greifen bei jedem Speichervorgang. Daten, die am DataHandler
vorbei geschrieben wurden – per SQL oder durch Synchronisations-Werkzeuge –
und Zuordnungen von vor der Installation findet die
Konsistenzprüfung. Sie liest alle Gruppen und Benutzer und ändert nichts:

..  code-block:: bash

    vendor/bin/typo3 begroups:audit

..  list-table::
    :header-rows: 1
    :widths: 30 12 58

    *   - Prüfung
        - Schwere
        - Befund
    *   - ``unknown-kind``
        - Fehler
        - Der Typ der Gruppe ist nicht konfiguriert, zum Beispiel der Typ
          einer deinstallierten Extension. Wählen Sie einen Typ.
    *   - ``classic-group``
        - Warnung
        - Eine klassische Gruppe. Teilen Sie sie in Bausteine und eine Rolle
          auf.
    *   - ``classic-group-disabled``
        - Fehler
        - Eine klassische Gruppe, obwohl klassische Gruppen abgeschaltet
          sind, siehe :ref:`configuration`.
    *   - ``building-block-with-subgroups``
        - Fehler
        - Ein Baustein hat Untergruppen.
    *   - ``foreign-permissions``
        - Fehler
        - Eine Gruppe trägt Rechte, die ihr Typ nicht anzeigt. Sie wirken,
          bis die Gruppe erneut gespeichert wird.
    *   - ``role-missing-member``
        - Fehler
        - Eine Rolle enthält eine Gruppe, die es nicht (mehr) gibt.
    *   - ``role-invalid-member``
        - Fehler
        - Eine Rolle enthält eine Rolle, eine klassische Gruppe oder eine
          Gruppe mit unbekanntem Typ.
    *   - ``user-building-block``
        - Fehler
        - Ein Baustein ist einem Benutzer direkt zugewiesen.
    *   - ``user-permissions``
        - Warnung
        - Ein Benutzer-Datensatz trägt eigene Rechte, die TYPO3 zu denen der
          Rollen hinzufügt. Neue Benutzer erhalten standardmäßig alle
          Dateioperationen (Core-Standardwert von :sql:`file_permissions`);
          entfernen Sie sie im Benutzer-Datensatz.
    *   - ``unassigned-fields``
        - Warnung
        - Eine Rolle oder ein Baustein hat Werte in Feldern anderer
          Extensions, die zu keinem Typ gehören; sein Formular zeigt sie
          also nicht. Gewähren sie Rechte, ordnen Sie die Felder einem Typ
          zu, siehe :ref:`developer-fields`.
    *   - ``unclean-group-list``
        - Warnung
        - Die Untergruppen einer Gruppe oder die Gruppen eines Benutzers
          enthalten Duplikate oder Einträge, die TYPO3 und das
          Backend-Formular unterschiedlich lesen, etwa ``be_groups_5`` oder
          ``05``. Speichern im Backend kann Gruppen hinzufügen oder
          entfernen; prüfen Sie sie vorher.
    *   - ``user-ignores-group-mounts``
        - Warnung
        - Das Feld :sql:`options` eines Benutzers (im englischen Backend
          :guilabel:`Mount from groups`) umfasst nicht alle Seitenbaum- und
          Datei-Freigaben der Gruppen.

Versteckte Gruppen und deaktivierte Benutzer werden ebenfalls geprüft und
mit "(disabled)" markiert. Administratoren und gelöschte Datensätze werden
übersprungen. Extensions können eigene Prüfungen ergänzen, siehe
:ref:`developer-audit`.

Optionen:

``--format=json``
    Gibt das Ergebnis als JSON aus (``errors``, ``warnings`` und
    ``findings`` mit ``severity``, ``identifier``, ``table``, ``uid``,
    ``title`` und ``message``), für Monitoring und Weiterverarbeitung.

``--fail-on-warnings``
    Behandelt Warnungen beim Exit-Code wie Fehler.

Über den Exit-Code lässt sich die Prüfung in Deployments, CI-Pipelines und
Monitoring einsetzen:

..  list-table::
    :header-rows: 1
    :widths: 15 85

    *   - Code
        - Bedeutung
    *   - ``0``
        - Keine Fehler (Warnungen sind erlaubt, außer mit
          ``--fail-on-warnings``).
    *   - ``1``
        - Fehler gefunden, oder Warnungen mit ``--fail-on-warnings``.
    *   - ``2``
        - Ungültiger Wert für ``--format``. Unbekannte Optionen lehnt die
          Konsole mit Exit-Code ``1`` ab.

..  code-block:: bash
    :caption: Beispiel: Deployment bei Fehlern abbrechen

    vendor/bin/typo3 begroups:audit --format=json > begroups-audit.json
