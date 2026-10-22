..  include:: /Includes.rst.txt

..  _configuration:

=============
Konfiguration
=============

Systemverantwortliche konfigurieren die Extension unter
:guilabel:`System > Einstellungen > Extension Configuration > be_groups`;
TYPO3 fragt dabei zuerst nach ihrem Passwort.

..  _configuration-allowClassicGroups:

Klassische Gruppen erlauben
===========================

..  confval:: allowClassicGroups
    :name: be-groups-allowClassicGroups
    :type: boolean
    :default: true

    Erlaubt klassische Gruppen: Backend-Gruppen vom Typ
    „Klassisch (alle Einstellungen)“.

    Aktiv (Standard)
        Klassische Gruppen können angelegt und Backend-Benutzern zugewiesen
        werden, zusätzlich zu Rollen. Das ist während der Umstellung einer
        bestehenden Installation nötig.

    Deaktiviert
        *   Das Formular bietet den Typ „Klassisch (alle Einstellungen)“
            nicht mehr an, außer für Gruppen, die bereits klassisch sind.
            Neue Gruppen beginnen als „Rolle“.
        *   Es können keine neuen klassischen Gruppen angelegt werden, und
            keine Gruppe kann klassisch werden – weder im Formular noch über
            die DataHandler-API. Eine über die API ohne Typ angelegte Gruppe
            wird abgelehnt, weil sie standardmäßig klassisch wäre.
        *   Backend-Benutzern können nur Rollen zugewiesen werden.
        *   Die Konsistenzprüfung ``begroups:audit`` meldet verbliebene
            klassische Gruppen als Fehler ``classic-group-disabled``, siehe
            :ref:`usage-audit`.
        *   Bestehende klassische Gruppen und ihre Zuweisungen wirken weiter.
            Es werden keine Rechte gelöscht.

    Die Option wirkt sofort; Caches müssen nicht geleert werden.

    ..  tip::

        Schalten Sie die Option ab, sobald alle Gruppen in Bausteine und
        Rollen umgestellt sind. Ab dann ist das saubere Modell erzwungen.
