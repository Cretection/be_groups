..  include:: /Includes.rst.txt

..  _introduction:

==========
Einführung
==========

..  _introduction-what:

Was macht die Extension?
========================

Große TYPO3-Installationen haben schnell dutzende oder hunderte
Backend-Benutzergruppen. Jede Gruppe kann alles konfigurieren: Module,
Tabellen, Seitenbaum-Einstiegspunkte, Dateifreigaben, Sprachen, TSconfig.
Nach einer Weile weiß niemand mehr, welche Gruppe was vergibt und warum ein
Redakteur etwas darf.

Backend Group Kinds bringt Ordnung hinein:

*   Jede Gruppe bekommt einen **Typ**. Der Typ legt fest, welche Felder die
    Gruppe zeigt und welche Rechte sie vergeben darf. Eine Gruppe vom Typ
    „Dateifreigabe“ vergibt nur Dateifreigaben, eine Gruppe vom Typ
    „Sprachen“ nur Sprachen.
*   **Rollen** fassen Bausteine zusammen. Eine Rolle „Redaktion Marketing“
    besteht aus den Zugriffsrechten, Seitenbaum-Einstiegspunkten,
    Dateifreigaben und Sprachen, die eine Redakteurin im Marketing braucht.
*   **Backend-Benutzer bekommen nur Rollen.** Was jemand darf, ergibt sich
    aus den zugewiesenen Rollen – aus nichts anderem.
*   **Was nicht sichtbar ist, wirkt nicht.** Ein Baustein kann nur vergeben,
    was sein Typ anzeigt. Versteckte Reste früherer Konfigurationen werden
    entfernt.

Die Extension ergänzt die Core-Tabelle :sql:`be_groups` um genau ein Feld –
den Typ. Alle Rechte bleiben in den Core-Feldern, und TYPO3 wertet sie wie
gewohnt aus.

..  _introduction-why:

Warum?
======

Die offizielle TYPO3-Dokumentation empfiehlt genau diese Struktur:
System-Gruppen (Seitenbaum-Einstiegspunkte, Dateifreigaben,
Kategorie-Freigaben, Seitenrechte), Gruppen für Zugriffsrechte (ACL) und
Rollen-Gruppen, die nur andere Gruppen zusammenfassen – unterschieden durch
Präfixe wie ``R_``, ``DBM_``, ``FM_`` oder ``ACL_``.

Siehe `Setting up backend user groups
<https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Administration/PermissionsManagement/SettingUpBackendGroups/Index.html>`__
in TYPO3 Explained. Dieselbe Seite benennt auch die Lücke, die diese
Extension schließt:

    "TYPO3 currently lacks the feature to categorize backend user groups by
    context or purpose"

    (TYPO3 kann Backend-Benutzergruppen derzeit nicht nach Kontext oder Zweck
    einordnen.)

Backend Group Kinds setzt die Empfehlung als echte Gruppentypen um: Die
Oberfläche führt, und die Regeln werden durchgesetzt, statt sich auf
Namenskonventionen zu verlassen.

..  _introduction-requirements:

Voraussetzungen
===============

*   TYPO3 14.3 LTS
*   PHP 8.2 – 8.5

..  _introduction-not:

Was die Extension nicht macht
=============================

*   Sie wertet keine Rechte selbst aus. TYPO3 löst Gruppen und Untergruppen
    wie gewohnt auf.
*   Sie ist kein Werkzeug für „Rechte als Code“. Extensions wie
    `b13/permission-sets <https://github.com/b13/permission-sets>`__ decken
    das Ausrollen von Rechten ab und lassen sich mit dieser Extension
    kombinieren.
*   Sie verwaltet keine Seitenrechte (Besitzer, Gruppe, Alle). Dafür gibt es
    das Core-Modul :guilabel:`Permissions`.
