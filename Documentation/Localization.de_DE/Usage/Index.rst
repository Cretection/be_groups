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
*   Eine Rolle hat keine eigenen Einstellungen. Sie listet ihre Bausteine,
    gruppiert nach Typ, und wird Backend-Benutzern zugewiesen.

Beim Speichern einer Gruppe werden alle Einstellungen entfernt, die nicht zu
ihrem Typ gehören, auch Standardwerte. Das Systemprotokoll
(*Administration > Protokoll*) hält jede Korrektur der Extension fest.

..  _usage-module:

Das Modul „Rollen & Bausteine“
==============================

Administratoren finden das Modul *Administration > Rollen & Bausteine* neben
dem Modul *Benutzer*. Es ändert nichts, sondern zeigt:

*   jede Rolle mit ihren Bausteinen, gruppiert nach Typ, und ihren Benutzern,
*   jeden Baustein mit den Rollen und klassischen Gruppen, die ihn verwenden,
    und den Benutzern, denen er direkt zugewiesen ist,
*   die verbliebenen klassischen Gruppen.

Rollen lassen sich nach Titel oder Anzahl der Benutzer sortieren, Bausteine
nach Titel oder Verwendung. Bausteine lassen sich nach Typ filtern; die
Auswahl bleibt pro Benutzer gespeichert.

Einträge, die nicht dem Rollenmodell folgen, sind markiert: Rollen mit
Gruppen, die keine Bausteine sind oder nicht mehr existieren, und Bausteine,
die Benutzern direkt zugewiesen sind. Unbenutzte Bausteine sind ebenfalls
markiert.

Ein Klick auf einen Titel öffnet die Gruppe oder den Benutzer im gewohnten
Formular. Die Schaltflächen im Kopfbereich legen eine neue Rolle oder einen
neuen Baustein an.

Das Modul ist optional. Um es auszublenden, ergänzen Sie seine Kennung im
User-TSconfig der Administratoren:

..  code-block:: typoscript
    :caption: User-TSconfig

    options.hideModules := addToList(begroups_overview)
