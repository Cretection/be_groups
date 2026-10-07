..  include:: /Includes.rst.txt

..  _developer:

==============
Für Entwickler
==============

..  _developer-kinds:

Gruppentypen sind TCA-Typen
===========================

Der Typ ist das Typ-Feld von :sql:`be_groups`
(``ctrl.type = tx_begroups_kind``). Jeder Typ ist ein normaler Eintrag in
``$GLOBALS['TCA']['be_groups']['types']``. Es gibt keine eigene Registry –
Sie verwenden die TCA-API, die Sie schon kennen.

..  _developer-fields:

Eigene Felder einem Typ zuordnen
================================

Wenn Ihre Extension :sql:`be_groups` um ein Rechtefeld ergänzt, ordnen Sie es
dem Typ zu, zu dem es gehört. Sonst ist das Feld nur im Typ „Klassisch“
sichtbar, und Regel R1 leert es bei allen anderen Typen.

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_groups.php

    <?php

    declare(strict_types=1);

    use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

    defined('TYPO3') or die();

    ExtensionManagementUtility::addToAllTCAtypes(
        'be_groups',
        'tx_myextension_permissions',
        'acl',
        'after:custom_options',
    );

Legen Sie eine Abhängigkeit auf ``cretection/be-groups`` an (``require``
oder ``suggest`` in der :file:`composer.json`), damit Ihr TCA-Override nach
dieser Extension geladen wird.

..  _developer-own-kind:

Einen eigenen Typ ergänzen
==========================

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_groups.php

    <?php

    declare(strict_types=1);

    use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

    defined('TYPO3') or die();

    ExtensionManagementUtility::addTcaSelectItem(
        'be_groups',
        'tx_begroups_kind',
        [
            'label' => 'my_extension.db:be_groups.kind.news',
            'value' => 'my_news',
            'icon' => 'content-news',
        ],
    );

    $GLOBALS['TCA']['be_groups']['ctrl']['typeicon_classes']['my_news'] = 'content-news';
    $GLOBALS['TCA']['be_groups']['types']['my_news'] = [
        'title' => 'my_extension.db:be_groups.kind.news',
        'showitem' => '
            --div--;core.form.tabs:general,
                title, tx_begroups_kind, tx_myextension_news_permissions,
            --div--;core.form.tabs:access,
                hidden,
            --div--;core.form.tabs:notes,
                description,
            --div--;core.form.tabs:extended,
        ',
    ];

Verwenden Sie eine Kennung mit Präfix (``my_news``, nicht ``news``), damit
sie nicht mit künftigen Typen dieser Extension kollidiert.

..  _developer-planned:

Geplant
=======

Die folgenden Teile des Relaunch sind für Version 1.0.0 geplant und noch
nicht verfügbar:

*   PSR-14-Events an den Erweiterungspunkten (Typ-Zuordnung,
    Klassifizierung, Ergebnisse der Konsistenzprüfung).
*   Bearbeiten im Modul „Rollen & Bausteine“ (eine Matrix aus Rollen und
    Bausteinen). Die Übersicht ohne Bearbeitung ist bereits verfügbar, siehe
    :ref:`usage-module`.
*   Eine Konsistenzprüfung auf der Kommandozeile (``begroups:audit``).
*   Assistenten, die Typen vorschlagen (``begroups:classify``) und gemischte
    klassische Gruppen in Bausteine und eine Rolle aufteilen
    (``begroups:split``).

..  _developer-contributing:

Mitwirken
=========

Siehe
`CONTRIBUTING.de.md <https://github.com/Cretection/be_groups/blob/main/CONTRIBUTING.de.md>`__
und die
`Coding-Leitlinien <https://github.com/Cretection/be_groups/blob/main/CODING_GUIDELINES.de.md>`__
im Repository.
