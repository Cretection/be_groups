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
herunter oder installieren Sie sie im Modul :guilabel:`System > Extension`.
Aktualisieren Sie danach das Datenbankschema in
:guilabel:`System > Wartung > Analyze Database Structure`.

..  _installation-after:

Nach der Installation
=====================

*   Alle bestehenden Backend-Gruppen sind vom Typ
    „Klassisch (alle Einstellungen)“. Für Ihre Benutzer ändert sich nichts.
*   Stellen Sie die bestehenden Gruppen mit den Assistenten um, siehe
    :ref:`migration`, oder legen Sie die ersten Bausteine und Rollen selbst
    an, siehe :ref:`concept`.

..  note::

    Beim Aktualisieren der Sprachpakete (:bash:`language:update` oder
    :guilabel:`System > Wartung > Manage Language Packs`) protokolliert
    TYPO3 eine Warnung für be_groups, solange der Übersetzungsserver von
    TYPO3 keine Sprachpakete der Extension hat (Statuscode 404). Die Warnung
    schadet nicht: Die deutsche Übersetzung ist Teil der Extension, und
    andere Sprachen zeigen die englischen Texte, bis es Sprachpakete gibt.

..  _installation-update:

Aktualisieren
=============

Vorher den Changelog der neuen Version lesen, siehe :ref:`changelog`. Nach
dem Update alle Caches leeren und die Extension erneut einrichten:

..  code-block:: bash

    vendor/bin/typo3 cache:flush
    vendor/bin/typo3 extension:setup

TYPO3 hält die Konfiguration seiner Dienste, das TCA und die Labels in
Caches. Bis sie geleert sind, kann das Speichern von Gruppen und Benutzern
mit einem Fehler abbrechen, und die Präfixe der Typen können fehlen: TYPO3
zeigt dann stattdessen die Bezeichnung des Typs, zum Beispiel
``Rolle: Editors`` statt ``META: Editors``.

..  _installation-uninstall:

Deinstallation
==============

Entfernen Sie die Extension mit :bash:`composer remove cretection/be-groups`
(im klassischen Modus im Modul :guilabel:`System > Extension`). Alle Rechte
bleiben in den Core-Feldern und wirken weiter, solange die Listen der
Untergruppen in 255 Zeichen passen (siehe unten): Rollen werden zu normalen
Gruppen mit Untergruppen.

Lange Listen von Untergruppen
    Die Extension erweitert das Feld :sql:`be_groups.subgroup` auf 2048
    Zeichen, weil Rollen viele Bausteine bündeln. Ohne die Extension
    definiert TYPO3 das Feld wieder mit 255 Zeichen: Der Datenbank-Vergleich
    bietet an, es zurückzuändern, und :bash:`extension:setup` ändert es ohne
    Rückfrage. Längere Listen werden dann abgeschnitten (MySQL und MariaDB
    ohne Strict Mode), oder die Änderung schlägt fehl (im Strict Mode und
    unter PostgreSQL). Suchen Sie vor dem Entfernen nach solchen Listen:

    ..  code-block:: sql

        SELECT uid, title FROM be_groups WHERE LENGTH(subgroup) > 255;

    Findet die Abfrage Gruppen, behalten Sie die Länge in Ihrem Sitepackage
    bei:

    ..  code-block:: php
        :caption: EXT:my_sitepackage/Configuration/TCA/Overrides/be_groups.php

        <?php

        declare(strict_types=1);

        defined('TYPO3') or die();

        $GLOBALS['TCA']['be_groups']['columns']['subgroup']['config']['dbFieldLength'] = 2048;

Die Typen
    Nach dem Entfernen bietet der Datenbank-Vergleich an, die Spalte
    :sql:`be_groups.tx_begroups_kind` zu entfernen. Sie enthält die Typen der
    Gruppen. Behalten Sie sie, wenn Sie die Extension vielleicht wieder
    installieren: Jede Gruppe hat dann ihren Typ wie vorher. Entfernen Sie
    sie, sind nach einer erneuten Installation alle Gruppen vom Typ
    „Klassisch (alle Einstellungen)“, mit unveränderten Rechten. Der
    Datenbank-Vergleich benennt die Spalte zuerst in
    :sql:`zzz_deleted_tx_begroups_kind` um. Solange diese Spalte existiert,
    können Sie die Typen nach einer erneuten Installation wiederherstellen:

    ..  code-block:: sql

        UPDATE be_groups SET tx_begroups_kind = zzz_deleted_tx_begroups_kind
            WHERE zzz_deleted_tx_begroups_kind <> '';

    Die Anweisung schreibt die Typen am DataHandler und seinen Regeln
    vorbei. Stattdessen können Sie die klassischen Gruppen auch erneut mit
    den Assistenten umstellen, die jede Umstellung prüfen, siehe
    :ref:`migration`.

    Prüfen Sie die Gruppen nach jeder erneuten Installation – auch wenn Sie
    die Spalte behalten haben – mit ``begroups:audit``: Gruppen, die im
    Core-Formular bearbeitet wurden, während die Extension entfernt war,
    können Rechte tragen, die ihr Typ nicht anzeigt. Beheben Sie Fehler wie
    ``foreign-permissions``, bevor Sie diese Gruppen speichern, denn das
    Speichern einer Gruppe entfernt solche Rechte.
