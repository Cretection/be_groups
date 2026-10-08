..  include:: /Includes.rst.txt

..  _concept:

=======
Konzept
=======

..  _concept-principles:

Prinzipien
==========

Core-nativ
    Rechte liegen ausschließlich in den Core-Feldern von :sql:`be_groups`.
    Die Zusammensetzung einer Rolle ist das Core-Feld :sql:`subgroup`. Die
    Extension speichert genau ein zusätzliches Feld: den Typ
    (:sql:`tx_begroups_kind`). TYPO3 löst Gruppen und Rechte wie gewohnt
    auf.

Was nicht sichtbar ist, wirkt nicht
    TYPO3 wertet *alle* Rechtefelder einer Gruppe aus, unabhängig davon, was
    ein Formular anzeigt. Deshalb darf ein Baustein nur in den Rechtefeldern
    Werte haben, die sein Typ anzeigt. Rechtefelder, die nicht zum Typ
    gehören, werden beim Speichern geleert. Andere Daten in
    :sql:`be_groups`, zum Beispiel Kennungen von Synchronisierungs-Werkzeugen,
    werden nie angefasst.

Führen statt brechen
    Nach der Installation funktionieren alle bestehenden Gruppen unverändert
    als Typ „Klassisch“. Assistenten und Prüfungen führen Schritt für
    Schritt zum sauberen Modell.

..  _concept-kinds:

Gruppentypen
============

Die Typen folgen der offiziellen TYPO3-Empfehlung
`Setting up backend user groups
<https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Administration/PermissionsManagement/SettingUpBackendGroups/Index.html>`__,
ergänzt um „TSconfig“ und „Workspace“.

..  list-table::
    :header-rows: 1
    :widths: 18 20 10 52

    *   -   Kennung
        -   Name
        -   Präfix
        -   Felder des Typs
    *   -   ``role``
        -   Rolle
        -   ``META``
        -   Nur :sql:`subgroup`, und darin nur Bausteine.
    *   -   ``acl``
        -   Zugriffsrechte
        -   ``ACL``
        -   Module (:sql:`groupMods`), Tabellenrechte
            (:sql:`tables_modify`, :sql:`tables_select`), erlaubte
            Ausschlussfelder (:sql:`non_exclude_fields`), ausdrücklich
            erlaubte Werte (:sql:`explicit_allowdeny`), Seitentypen
            (:sql:`pagetypes_select`), eigene Optionen (:sql:`custom_options`),
            MFA-Anbieter (:sql:`mfa_providers`), Dashboard-Widgets
            (:sql:`availableWidgets`, mit EXT:dashboard)
    *   -   ``page_group``
        -   Seitenrechte-Gruppe
        -   ``PG``
        -   Keine eigenen Felder. Dient als Eigentümergruppe für Seitenrechte.
    *   -   ``db_mount``
        -   Seitenbaum-Einstiegspunkte
        -   ``DBM``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   Dateifreigaben
        -   ``FM``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   Dateioperationen
        -   ``FO``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Kategorie-Freigaben
        -   ``CM``
        -   :sql:`category_perms`
    *   -   ``language``
        -   Sprachen
        -   ``L``
        -   :sql:`allowed_languages`
    *   -   ``tsconfig``
        -   TSconfig
        -   ``TS``
        -   :sql:`TSconfig`, :sql:`tsconfig_includes`
    *   -   ``workspace``
        -   Workspace
        -   ``WS``
        -   :sql:`workspace_perms` (nur mit EXT:workspaces)
    *   -   ``classic``
        -   Klassisch
        -   –
        -   Alle Felder im Core-Layout. So funktionieren TYPO3-Gruppen ohne
            diese Extension.

Alle Typen haben außerdem Titel, Beschreibung und das Feld „Deaktiviert“.
Der Titel muss den Typ nicht enthalten: TYPO3 zeigt das Präfix des Typs davor
an, siehe :ref:`usage-prefixes`.

Gültig sind nur die konfigurierten Typen: die Einträge des Felds
:sql:`tx_begroups_kind`, die ein eigenes Formular haben. Ein gespeicherter
Wert, der kein solcher Eintrag ist – zum Beispiel der Typ einer
deinstallierten Extension oder ein per SQL geschriebener Wert –, ist weder
Rolle noch Baustein. Solche Gruppen
werden nicht verändert, können keiner Rolle hinzugefügt werden und werden im
Modul gesondert aufgeführt.

..  _concept-rules:

Regeln
======

Die Extension setzt vier Regeln durch, sobald eine Gruppe oder ein
Backend-Benutzer gespeichert wird – im Backend-Formular, über die
DataHandler-API, durch Import- oder Sync-Werkzeuge. Auch gelöschte
Datensätze folgen den Regeln, damit ein wiederhergestellter Datensatz nie
eine verbotene Konfiguration zurückbringt.

R1: Ein Baustein vergibt nur, was sein Typ anzeigt
    Rechtefelder, die nicht zum Typ gehören, werden geleert. Verwaltet werden
    die Rechtefelder von :sql:`be_groups` im Core und jedes Feld, das im
    Formular einer Rolle oder eines Bausteins angezeigt wird. Das gilt auch
    für Standardwerte: Eine neue Gruppe vom Typ „Seitenbaum-Einstiegspunkte“
    bekommt nicht die Standard-Dateirechte.

R2: Einer Rolle werden nur Bausteine hinzugefügt
    Rollen, klassische Gruppen, Gruppen mit unbekanntem Typ, gelöschte oder
    nicht existierende Gruppen und die Rolle selbst können keiner Rolle
    hinzugefügt werden. Eine Rolle hat keine eigenen Rechtefelder (folgt aus
    R1).

R3: Bausteine haben keine Untergruppen
    Nur Rollen und klassische Gruppen dürfen Untergruppen haben.

R4: Backend-Benutzern werden nur Rollen zugewiesen
    Solange klassische Gruppen erlaubt sind (siehe :ref:`configuration`),
    dürfen Backend-Benutzer auch klassische Gruppen bekommen.

Einzelheiten der Regeln:

*   **Gespeicherte Verknüpfungen bleiben erhalten.** R2 und R4 lehnen nur
    Verknüpfungen ab, die *hinzukommen*. Eine Rolle, die bereits eine
    klassische Gruppe enthält, oder ein Benutzer, der bereits einen Baustein
    hat, behält ihn – das Abschalten klassischer Gruppen oder die Migration
    nimmt nie Zugriff weg. Das Modul markiert solche Einträge.
*   **Die Reihenfolge bleibt erhalten.** Die Reihenfolge der Mitglieder einer
    Rolle und der Gruppen eines Benutzers ist für die TSconfig-Vererbung
    wichtig; abgelehnte Einträge werden entfernt, die übrigen behalten ihre
    Position.
*   **Jede Schreibweise wird verstanden.** Verknüpfungen werden geprüft, ob
    sie als uid, als ``uid|Bezeichnung``, URL-kodiert, mit Tabellenpräfix
    (``be_groups_12``) oder als ``NEW…``-Platzhalter einer Gruppe angegeben
    sind, die im selben DataHandler-Aufruf angelegt wird.
*   **Neue Datensätze ohne Typ** bekommen den Typ, den der DataHandler setzen
    würde: den Standardwert aus TCA, überschrieben durch ``TCAdefaults`` im
    User-TSconfig, überschrieben durch ``TCAdefaults`` im Page-TSconfig. Die
    Regeln werden für genau diesen Typ geprüft, und genau dieser Typ wird
    gespeichert.
*   **Unbekannte Typen werden ignoriert.** Ein Wert, der kein konfigurierter
    Typ ist, wird nicht gespeichert; die Gruppe behält ihren Typ (oder eine
    neue Gruppe bekommt den Standardtyp).

Die Regeln korrigieren die Daten, statt das Speichern abzubrechen; nur eine
Gruppe, die bei abgeschalteten klassischen Gruppen als „Klassisch“ angelegt
würde, wird gar nicht angelegt. Import- und Synchronisierungs-Werkzeuge
funktionieren deshalb weiter. Jeder Eingriff wird ins Systemprotokoll
(:guilabel:`Administration > Protokoll`) geschrieben und im Backend als
Meldung angezeigt: geleerte Felder eines früheren Typs als Information (das
ist der Zweck eines Typwechsels), abgelehnte Werte und Datensätze als
Bedienfehler. Felder, die der Aufrufer selbst leert, werden nicht gemeldet.
Korrekturen werden gemeldet, nachdem das Speichern
abgeschlossen ist: Wird das Speichern abgebrochen, zum Beispiel weil die
Passwortabfrage des Sudo-Modus abgebrochen wird, wird keine Korrektur
protokolliert. Ein abgelehnter Datensatz oder eine abgelehnte Umstellung auf
„Klassisch“ wird sofort gemeldet.

..  _concept-classic:

Der Typ „Klassisch“
===================

„Klassisch“ ist eine Backend-Gruppe, wie TYPO3 sie ohne diese Extension
kennt: Sie zeigt alle Rechtefelder, darf Untergruppen haben und wird von den
Regeln R1 und R3 nicht eingeschränkt. Nach der Installation sind alle
bestehenden Gruppen vom Typ „Klassisch“, für Ihre Benutzer ändert sich also
nichts.

Klassische Gruppen sind die Brücke zum sauberen Modell: Stellen Sie sie
Schritt für Schritt um und schalten Sie klassische Gruppen am Ende in der
Extension-Konfiguration ab.

..  _concept-limitations:

Bekannte Einschränkungen
========================

Rechte an Backend-Benutzer-Datensätzen
    Backend-Benutzer-Datensätze haben eigene Rechtefelder, zum Beispiel
    Dateirechte, Module, TSconfig und Freigaben. TYPO3 führt sie mit den
    Rechten der Gruppen zusammen. Das Rollenmodell kann sie nicht steuern.
    Halten Sie Benutzer-Datensätze frei von Rechten und vergeben Sie alles
    über Rollen. Neue Benutzer erhalten standardmäßig alle Dateioperationen
    (Core-Standardwert). Die Konsistenzprüfung meldet Rechte an
    Benutzer-Datensätzen, siehe :ref:`usage-audit`.

Rechtefelder anderer Extensions
    Ein Rechtefeld, das eine andere Extension nur über das Core-Formular zu
    :sql:`be_groups` hinzufügt – weil die Extension vor dieser geladen wird
    und das Feld keinem Typ zuordnet –, wird nur bei klassischen Gruppen
    angezeigt und von Regel R1 nicht durchgesetzt. Ordnen Sie solche Felder
    einem Typ zu, siehe :ref:`developer-fields`.

Abgelehnte Datensätze bei Importen
    Lehnt eine Regel einen Datensatz ab, zum Beispiel eine klassische Gruppe
    bei abgeschalteten klassischen Gruppen, ist das im Systemprotokoll
    sichtbar, aber nicht in der Fehlerliste des DataHandlers, die
    Import-Werkzeuge üblicherweise auswerten. Führen Sie nach Importen die
    Konsistenzprüfung aus, siehe :ref:`usage-audit`.
