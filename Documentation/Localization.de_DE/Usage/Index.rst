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
(*Administration > Protokoll*) hält jede Korrektur der Extension fest.

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
