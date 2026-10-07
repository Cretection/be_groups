..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Composer-Modus
==============

..  code-block:: bash

    composer require cretection/be-groups

Danach die Extension einrichten und das Datenbankschema aktualisieren:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

..  _installation-classic:

Klassischer Modus
=================

Laden Sie die Extension aus dem
`TYPO3 Extension Repository (TER) <https://extensions.typo3.org/extension/be_groups>`__
herunter oder installieren Sie sie im Modul :guilabel:`System > Extensions`.
Führen Sie danach den Datenbank-Vergleich in
:guilabel:`Admin Tools > Maintenance` aus.

..  _installation-after:

Nach der Installation
=====================

*   Alle bestehenden Backend-Gruppen sind vom Typ „Klassisch“. Für Ihre
    Benutzer ändert sich nichts.
*   Falls Sie eine frühere Version dieser Extension genutzt haben (0.0.x oder
    die AOE-Version 1.x), führen Sie den Upgrade-Wizard aus, siehe
    :ref:`migration`.
*   Legen Sie die ersten Bausteine und Rollen an, siehe :ref:`concept`.

..  _installation-uninstall:

Deinstallation
==============

Entfernen Sie die Extension mit :bash:`composer remove cretection/be-groups`
(oder im Extension-Manager). Alle Rechte bleiben in den Core-Feldern und
wirken weiter: Rollen werden zu normalen Gruppen mit Untergruppen. Danach
bietet der Datenbank-Vergleich an, die Spalte
:sql:`be_groups.tx_begroups_kind` zu entfernen.
