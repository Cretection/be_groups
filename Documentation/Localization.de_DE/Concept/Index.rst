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
    als Typ „Klassisch (alle Einstellungen)“. Assistenten und Prüfungen
    führen Schritt für Schritt zum sauberen Modell.

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
        -   Keine eigenen Felder. Dient als Eigentümergruppe von Seiten;
            stellen Sie sie in einer Rolle an den Anfang, siehe
            :ref:`concept-combination`.
    *   -   ``db_mount``
        -   Seitenbaum-Freigabe
        -   ``DBM``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   Dateifreigabe
        -   ``FM``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   Dateioperationen
        -   ``FO``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Kategorie-Freigabe
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
        -   Klassisch (alle Einstellungen)
        -   –
        -   Alle Felder im Core-Layout. So funktionieren TYPO3-Gruppen ohne
            diese Extension.

Alle Typen haben außerdem einen Titel, den Schalter
:guilabel:`Die Gruppe ist aktiviert` (Reiter :guilabel:`Zugriff`) und eine
Beschreibung (Reiter :guilabel:`Hinweise`). Der Titel muss den Typ nicht
enthalten: TYPO3 zeigt das Präfix des Typs davor an, siehe
:ref:`usage-prefixes`.

Gültig sind nur die konfigurierten Typen: die Einträge des Felds
:sql:`tx_begroups_kind`, die ein eigenes Formular haben. Ein gespeicherter
Wert, der kein solcher Eintrag ist – zum Beispiel der Typ einer
deinstallierten Extension oder ein per SQL geschriebener Wert –, ist weder
Rolle noch Baustein. Solche Gruppen
werden nicht verändert, können keiner Rolle hinzugefügt werden und werden im
Modul gesondert aufgeführt.

..  _concept-combination:

Wie TYPO3 Bausteine zusammenführt
=================================

TYPO3 führt die Rechte aller Gruppen eines Benutzers – der Bausteine aller
seiner Rollen – und des Benutzer-Datensatzes zusammen. Bei den meisten Typen
fügt jeder Baustein Rechte hinzu. Vier Dinge funktionieren anders:

Sprachen und Kategorie-Freigaben schränken ein
    Ein Benutzer ohne Sprache darf alle Sprachen bearbeiten, und ein Benutzer
    ohne Kategorie-Freigabe sieht alle Kategorien. Ein Baustein vom Typ
    „Sprachen“ oder „Kategorie-Freigabe“ vergibt deshalb seine Sprachen oder
    Kategorien und beschränkt den Benutzer zugleich auf die Sprachen oder
    Kategorien all seiner Gruppen – in allen seinen Rollen. Eine Rolle ohne
    einen solchen Baustein erlaubt alle Sprachen und Kategorien, sofern keine
    andere Rolle des Benutzers sie einschränkt, und wer den letzten solchen
    Baustein eines Benutzers entfernt oder versteckt, gibt dem Benutzer alle
    Sprachen oder Kategorien. Geben Sie jeder Rolle, deren Benutzer
    eingeschränkt sein sollen, einen eigenen solchen Baustein.

Die Live-Arbeitsumgebung braucht einen Baustein vom Typ „Workspace“
    Mit EXT:workspaces darf ein Benutzer nur dann in der Live-Arbeitsumgebung
    arbeiten, wenn eine seiner Gruppen oder sein Benutzer-Datensatz es erlaubt
    (:sql:`workspace_perms`, :guilabel:`Live bearbeiten (Online)`). TYPO3
    erlaubt es weder neuen Gruppen noch neuen Benutzern. Da
    Benutzer-Datensätze frei von Rechten bleiben, geben Sie jeder Rolle, deren
    Benutzer live arbeiten, einen Baustein vom Typ „Workspace“, der es
    erlaubt; ein gemeinsamer Baustein genügt. Benutzer ohne Live-Zugriff und
    ohne Mitgliedschaft in einer eigenen Arbeitsumgebung sehen gar kein Modul
    (*Kein Workspace-Zugriff*). Eigene Arbeitsumgebungen
    werden im Datensatz der Arbeitsumgebung vergeben, in dem Rollen und
    Bausteine Mitglieder oder Besitzer sein können.

Der erste Baustein besitzt die neuen Seiten
    TYPO3 macht die erste Gruppe, die es für einen Benutzer auflöst, zur
    Eigentümergruppe der Seiten, die der Benutzer anlegt. Es löst die
    Untergruppen einer Gruppe vor der Gruppe selbst auf; das ist also der
    erste nicht versteckte Baustein der ersten Rolle des Benutzers.
    Mitglieder der Eigentümergruppe dürfen diese Seiten standardmäßig
    anzeigen und bearbeiten, Unterseiten anlegen und Inhalte bearbeiten
    (``$GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPermissions']['group']``).
    Jede andere Rolle, die diesen Baustein enthält, bekommt diesen Zugriff
    auf die neuen Seiten ebenfalls. Stellen Sie eine Seitenrechte-Gruppe der
    Rolle an den Anfang, wie es die Assistenten tun (siehe
    :ref:`migration`), oder legen Sie die Eigentümergruppe neuer Seiten per
    ``TCEMAIN.permissions.groupid`` im Page-TSconfig fest.

Seitenbaum-Freigaben brauchen Seitenrechte
    Eine Seitenbaum-Freigabe zeigt nur die Seiten, die der Benutzer lesen
    darf. Geben Sie den Seiten im Core-Modul :guilabel:`Berechtigungen` eine
    Seitenrechte-Gruppe der Rolle als Gruppe, oder erlauben Sie dort allen
    den Zugriff.

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
    für Standardwerte: Eine neue Gruppe vom Typ „Seitenbaum-Freigabe“ bekommt
    nicht die Standard-Dateirechte.

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
    nimmt nie Zugriff weg. Das Modul markiert solche Einträge. Als
    gespeichert zählt eine Verknüpfung nur, wenn TYPO3 sie liest: Ein
    gespeichertes ``be_groups_12`` ist für TYPO3 keine Verknüpfung, das
    Hinzufügen von Gruppe 12 wird also geprüft.
*   **Die Reihenfolge bleibt erhalten.** Die Reihenfolge der Mitglieder einer
    Rolle und der Gruppen eines Benutzers ist für die TSconfig-Vererbung
    wichtig; abgelehnte Einträge werden entfernt, die übrigen behalten ihre
    Position.
*   **Jede Schreibweise wird verstanden.** Verknüpfungen werden geprüft, ob
    sie als uid, als ``uid|Bezeichnung``, URL-kodiert, mit Tabellenpräfix
    (``be_groups_12``) oder als ``NEW…``-Platzhalter einer Gruppe angegeben
    sind, die im selben DataHandler-Aufruf angelegt wird. Ein Platzhalter,
    der auch für einen Datensatz einer anderen Tabelle steht, wird
    abgelehnt: Er könnte beim Speichern der Verknüpfung einen anderen
    Datensatz meinen.
*   **Standardwerte werden geprüft.** Neue Benutzer und Rollen, die ihre
    Gruppen nicht angeben, bekommen den Standardwert aus TCA und
    ``TCAdefaults`` (auch typspezifisch). Die Regeln prüfen diesen
    Standardwert wie jeden anderen Wert und speichern das Ergebnis
    ausdrücklich.
*   **Neue Datensätze ohne Typ** bekommen den Typ, den der DataHandler setzen
    würde: den Standardwert aus TCA, überschrieben durch ``TCAdefaults`` im
    User-TSconfig, überschrieben durch ``TCAdefaults`` im Page-TSconfig.
    Gruppen liegen auf der Wurzelebene, deshalb gilt für sie nur das globale
    Page-TSconfig, zum Beispiel :file:`Configuration/page.tsconfig` eines
    Sitepackages. Das TSconfig einer Seite, auch einer Wurzelseite, wirkt
    nicht auf Gruppen. Die Regeln werden für genau diesen Typ geprüft, und
    genau dieser Typ wird gespeichert.

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/page.tsconfig

        TCAdefaults.be_groups.tx_begroups_kind = role
*   **Unbekannte Typen werden ignoriert.** Ein Wert, der kein konfigurierter
    Typ ist, wird nicht gespeichert; die Gruppe behält ihren Typ (oder eine
    neue Gruppe bekommt den Standardtyp).

Die Regeln korrigieren die Daten, statt das Speichern abzubrechen; nur eine
klassische Gruppe, die bei abgeschalteten klassischen Gruppen angelegt würde,
wird gar nicht angelegt. Import- und Synchronisierungs-Werkzeuge
funktionieren deshalb weiter. Jeder Eingriff wird ins Systemprotokoll
(:guilabel:`Administration > Protokoll`) geschrieben und im Backend als
Meldung angezeigt: geleerte Felder eines früheren Typs als Information (das
ist der Zweck eines Typwechsels), abgelehnte Werte und Datensätze als
Bedienfehler. Felder, die der Aufrufer selbst leert, werden nicht gemeldet,
ebenso wenig Standardwerte, die eine neue Gruppe nie bekommt. Korrekturen
werden gemeldet, nachdem das Speichern
abgeschlossen ist: Wird das Speichern abgebrochen, zum Beispiel weil die
Passwortabfrage des Sudo-Modus abgebrochen wird, wird keine Korrektur
protokolliert. Ein abgelehnter Datensatz oder eine abgelehnte Umstellung in
eine klassische Gruppe wird sofort gemeldet.

..  _concept-classic:

Klassische Gruppen
==================

Eine Gruppe vom Typ „Klassisch (alle Einstellungen)“ ist eine Backend-Gruppe,
wie TYPO3 sie ohne diese Extension kennt: Sie zeigt alle Rechtefelder, darf
Untergruppen haben und wird von den Regeln R1 und R3 nicht eingeschränkt.
Nach der Installation sind alle bestehenden Gruppen von diesem Typ, für Ihre
Benutzer ändert sich also nichts.

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
    über Rollen. Die Konsistenzprüfung meldet Rechte an
    Benutzer-Datensätzen, siehe :ref:`usage-audit`.

    Neue Benutzer erhalten standardmäßig alle Dateioperationen
    (Core-Standardwert). Um Benutzer ohne sie anzulegen, setzen Sie im
    globalen Page-TSconfig einen leeren Standardwert; auch Backend-Benutzer
    liegen auf der Wurzelebene:

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/page.tsconfig

        TCAdefaults.be_users.file_permissions =

    Das Formular eines neuen Benutzers beginnt dann ohne Dateioperationen,
    und Benutzer, die über den DataHandler angelegt werden, zum Beispiel mit
    ``vendor/bin/typo3 backend:user:create`` oder durch einen Import,
    bekommen ebenfalls keine. Bestehende Benutzer behalten ihre; entfernen
    Sie sie im Benutzer-Datensatz.

Rechtefelder anderer Extensions
    Ein Rechtefeld, das eine andere Extension nur über das Core-Formular zu
    :sql:`be_groups` hinzufügt – weil die Extension vor dieser geladen wird
    und das Feld keinem Typ zuordnet –, wird nur bei klassischen Gruppen
    angezeigt und von Regel R1 nicht durchgesetzt. Ordnen Sie solche Felder
    einem Typ zu, siehe :ref:`developer-fields`. Die Konsistenzprüfung
    meldet Werte in solchen Feldern bei Gruppen anderer Typen, und die
    Assistenten stellen Gruppen mit Werten darin nicht um.

Abgelehnte Datensätze bei Importen
    Lehnt eine Regel einen Datensatz oder eine Verknüpfung ab, zum Beispiel
    eine klassische Gruppe bei abgeschalteten klassischen Gruppen, speichert
    der DataHandler den Rest und meldet keinen Fehler. Die Ablehnung steht
    weder in der Fehlerliste des DataHandlers, die Import-Werkzeuge
    üblicherweise auswerten, noch in den gespeicherten Daten; auch die
    Konsistenzprüfung kann sie deshalb nicht finden. Prüfen Sie nach einem
    Import das Systemprotokoll (:guilabel:`Verwaltung > Protokoll`): Jede
    Ablehnung ist ein Eintrag zur Tabelle :sql:`be_groups` oder
    :sql:`be_users`. Skripte finden diese Einträge in :sql:`sys_log` mit
    ``type`` 4 und ``error`` 1. Die Konsistenzprüfung (siehe
    :ref:`usage-audit`) findet Daten, die am DataHandler vorbei geschrieben
    wurden, zum Beispiel per SQL.
