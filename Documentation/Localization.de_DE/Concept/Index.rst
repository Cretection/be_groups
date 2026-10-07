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
    ein Formular anzeigt. Deshalb darf ein Baustein nur die Felder befüllt
    haben, die sein Typ anzeigt. Felder, die nicht zum Typ gehören, werden
    beim Speichern geleert.

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
        -   ``R_``
        -   Nur :sql:`subgroup`, und darin nur Bausteine.
    *   -   ``acl``
        -   Zugriffsrechte
        -   ``ACL_``
        -   Module (:sql:`groupMods`), Tabellenrechte
            (:sql:`tables_modify`, :sql:`tables_select`), erlaubte
            Ausschlussfelder (:sql:`non_exclude_fields`), ausdrücklich
            erlaubte Werte (:sql:`explicit_allowdeny`), Seitentypen
            (:sql:`pagetypes_select`), eigene Optionen (:sql:`custom_options`),
            MFA-Anbieter (:sql:`mfa_providers`), Dashboard-Widgets
            (:sql:`availableWidgets`, mit EXT:dashboard)
    *   -   ``page_group``
        -   Seitenrechte-Gruppe
        -   ``PG_``
        -   Keine eigenen Felder. Dient als Eigentümergruppe für Seitenrechte.
    *   -   ``db_mount``
        -   Seitenbaum-Einstiegspunkte
        -   ``DBM_``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   Dateifreigaben
        -   ``FM_``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   Dateioperationen
        -   ``FO_``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Kategorie-Freigaben
        -   ``CM_``
        -   :sql:`category_perms`
    *   -   ``language``
        -   Sprachen
        -   ``L_``
        -   :sql:`allowed_languages`
    *   -   ``tsconfig``
        -   TSconfig
        -   ``TS_``
        -   :sql:`TSconfig`, :sql:`tsconfig_includes`
    *   -   ``workspace``
        -   Workspace
        -   ``WS_``
        -   :sql:`workspace_perms` (nur mit EXT:workspaces)
    *   -   ``classic``
        -   Klassisch
        -   –
        -   Alle Felder im Core-Layout. So funktionieren TYPO3-Gruppen ohne
            diese Extension.

Alle Typen haben außerdem Titel, Beschreibung und das Feld „Deaktiviert“.

..  _concept-rules:

Regeln
======

Die Extension setzt vier Regeln durch, sobald eine Gruppe oder ein
Backend-Benutzer gespeichert wird – im Backend-Formular, über die
DataHandler-API, durch Import- oder Sync-Werkzeuge.

R1: Ein Baustein vergibt nur, was sein Typ anzeigt
    Felder, die nicht zum Typ gehören, werden geleert. Das gilt auch für
    Standardwerte: Eine neue Gruppe vom Typ „Seitenbaum-Einstiegspunkte“
    bekommt nicht die Standard-Dateirechte.

R2: Eine Rolle enthält nur Bausteine
    Rollen und klassische Gruppen können nicht Teil einer Rolle sein. Eine
    Rolle hat keine eigenen Rechtefelder (folgt aus R1).

R3: Bausteine haben keine Untergruppen
    Nur Rollen und klassische Gruppen dürfen Untergruppen haben.

R4: Backend-Benutzer bekommen nur Rollen
    Solange klassische Gruppen erlaubt sind (siehe :ref:`configuration`),
    dürfen Backend-Benutzer auch klassische Gruppen bekommen.

Die Regeln korrigieren die Daten und schreiben einen Eintrag ins
Systemprotokoll, statt das Speichern abzubrechen. Import- und
Synchronisierungs-Werkzeuge funktionieren deshalb weiter.

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
