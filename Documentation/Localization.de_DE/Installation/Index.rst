..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-requirements:

Voraussetzungen und Kompatibilität
==================================

*   TYPO3 14.3 LTS und PHP 8.2 bis 8.5.
*   TYPO3 15 wird von derselben Hauptversion 1.x unterstützt, sobald es
    erschienen ist. Die Extension wird laufend gegen die Entwicklungsversion
    von TYPO3 15 getestet und nutzt nur APIs, die in TYPO3 14.3 und TYPO3 15
    weder als deprecated noch als intern markiert sind.
*   Der Support für eine TYPO3-Version endet zusammen mit dem offiziellen Ende
    ihres kostenlosen Community-Supports (siehe https://get.typo3.org); für
    TYPO3 14 ist das der 30.06.2029. Der kostenpflichtige Extended Long Term
    Support (ELTS) verlängert diesen Zeitraum nicht.
*   Jede Hauptversion der Extension unterstützt zwei aufeinanderfolgende
    TYPO3-Hauptversionen. TYPO3 lässt sich so aktualisieren, ohne gleichzeitig
    auf eine neue Hauptversion der Extension wechseln zu müssen.

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
*   Stellen Sie die bestehenden Gruppen mit den Assistenten um, siehe
    :ref:`migration`, oder legen Sie die ersten Bausteine und Rollen selbst
    an, siehe :ref:`concept`.

..  _installation-uninstall:

Deinstallation
==============

Entfernen Sie die Extension mit :bash:`composer remove cretection/be-groups`
(oder im Extension-Manager). Alle Rechte bleiben in den Core-Feldern und
wirken weiter: Rollen werden zu normalen Gruppen mit Untergruppen. Danach
bietet der Datenbank-Vergleich an, die Spalte
:sql:`be_groups.tx_begroups_kind` zu entfernen.
