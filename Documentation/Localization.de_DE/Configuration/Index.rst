..  include:: /Includes.rst.txt

..  _configuration:

=============
Konfiguration
=============

Die Extension wird unter :guilabel:`Admin Tools > Settings > Extension
Configuration > be_groups` konfiguriert.

..  _configuration-allowClassicGroups:

Klassische Gruppen erlauben
===========================

..  confval:: allowClassicGroups
    :name: be-groups-allowClassicGroups
    :type: boolean
    :default: true

    Erlaubt Backend-Gruppen vom Typ „Klassisch“.

    Aktiv (Standard)
        Klassische Gruppen können angelegt und Backend-Benutzern zugewiesen
        werden, zusätzlich zu Rollen. Das ist während der Umstellung einer
        bestehenden Installation nötig.

    Deaktiviert
        *   Es können keine neuen klassischen Gruppen angelegt werden, und
            keine Gruppe kann auf den Typ „Klassisch“ umgestellt werden.
        *   Backend-Benutzer können nur Rollen bekommen.
        *   Die Konsistenzprüfung (geplant, siehe :ref:`developer-planned`)
            meldet verbliebene klassische Gruppen als Fehler.
        *   Bestehende klassische Gruppen und ihre Zuweisungen wirken weiter.
            Es werden keine Rechte gelöscht.

    ..  tip::

        Schalten Sie die Option ab, sobald alle Gruppen in Bausteine und
        Rollen umgestellt sind. Ab dann ist das saubere Modell erzwungen.
