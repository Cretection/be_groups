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

Ein Typ ist gültig, wenn er ein Eintrag des Felds :sql:`tx_begroups_kind`
ist und einen eigenen Typ hat. Alles außer ``role`` und ``classic`` ist ein
Baustein. Werte, die kein Eintrag sind – Typen einer deinstallierten
Extension, per SQL geschriebene Werte –, sind weder Rolle noch Baustein.

..  _developer-fields:

Eigene Felder einem Typ zuordnen
================================

Wenn Ihre Extension :sql:`be_groups` um ein Rechtefeld ergänzt, ordnen Sie es
dem Typ zu, zu dem es gehört. Sobald ein Feld im Formular einer Rolle oder
eines Bausteins angezeigt wird, verwaltet Regel R1 es: Es bleibt bei diesem
Typ erhalten und wird bei allen anderen geleert. Ein Feld, das keinem Typ
zugeordnet ist (und kein Rechtefeld des Core ist), wird nur bei klassischen
Gruppen angezeigt und nicht durchgesetzt – siehe
:ref:`concept-limitations`. Felder, die keine Rechte vergeben, etwa Kennungen
eines Synchronisierungs-Werkzeugs, werden nie angefasst.

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

Verwenden Sie eine nicht numerische Kennung mit Präfix (``my_news``, nicht
``news``), damit sie nicht mit künftigen Typen dieser Extension kollidiert.

Die Bezeichnung Ihres Typs erscheint als Präfix vor den Titeln seiner
Gruppen, siehe :ref:`usage-prefixes`.

Optional geben Sie Ihrem Typ eine Bezeichnung in den nach Typ gruppierten
Listen der Rollen- und Benutzerformulare (sonst wird die Kennung angezeigt):

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_groups.php

    $GLOBALS['TCA']['be_groups']['types']['role']['columnsOverrides']['subgroup']['config']['itemGroups']['my_news']
        = 'my_extension.db:be_groups.kind.news';

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_users.php

    $GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['itemGroups']['my_news']
        = 'my_extension.db:be_users.usergroup.group.news';

..  _developer-audit:

Eigene Prüfungen für die Konsistenzprüfung
==========================================

Das Event :php:`\Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent`
wird nach den eingebauten Prüfungen von ``begroups:audit`` ausgelöst.
Listener lesen die Befunde mit :php:`getFindings()` und ergänzen eigene mit
:php:`addFinding()`. Der Befehl sortiert und zählt alle Befunde; Befunde von
Listenern wirken sich also wie alle anderen auf den Exit-Code aus.

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/CheckNewsEditors.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use Cretection\BeGroups\Domain\Audit\AuditFinding;
    use Cretection\BeGroups\Domain\Audit\AuditSeverity;
    use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final readonly class CheckNewsEditors
    {
        #[AsEventListener('my-extension/check-news-editors')]
        public function __invoke(AfterAuditFindingsCollectedEvent $event): void
        {
            // Ihre Prüfung, zum Beispiel anhand der Typen Ihrer Extension
            $event->addFinding(new AuditFinding(
                AuditSeverity::Warning,
                'my-news-editor-without-storage',
                'be_groups',
                12,
                'News editors',
                'The role grants the news module, but no storage folder.',
            ));
        }
    }

Verwenden Sie eine Kennung mit einem Präfix Ihrer Extension, damit sie nicht
mit künftigen Prüfungen dieser Extension kollidiert. :php:`AuditFinding`,
:php:`AuditSeverity` und das Event sind öffentliche API und folgen der
semantischen Versionierung.

..  _developer-kind-event:

Den Typ neuer Gruppen bestimmen
===============================

Import- und Synchronisations-Werkzeuge (z. B. für LDAP) legen Gruppen oft
über den DataHandler ohne Typ an. Solche Gruppen erhalten den Standard-Typ:
den TCA-Default, überschrieben durch ``TCAdefaults.be_groups.tx_begroups_kind``
im User- und Page-TSconfig. Mit dem Event
:php:`\Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent` bestimmen Sie
den Typ stattdessen aus den Werten des Datensatzes, zum Beispiel aus einem
Präfix des Titels:

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/KindFromTitlePrefix.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final readonly class KindFromTitlePrefix
    {
        private const PREFIXES = ['R_' => 'role', 'DBM_' => 'db_mount', 'FM_' => 'file_mount'];

        #[AsEventListener('my-extension/kind-from-title-prefix')]
        public function __invoke(ModifyKindOfNewGroupEvent $event): void
        {
            $title = $event->getRecord()['title'] ?? '';
            foreach (self::PREFIXES as $prefix => $kind) {
                if (is_string($title) && str_starts_with($title, $prefix)) {
                    $event->setKind($kind);
                }
            }
        }
    }

Das Event wird nur für neue Gruppen ohne Typ ausgelöst; ein ausdrücklich
angegebener Typ wird nie ersetzt. Ein nicht konfigurierter Typ wird
ignoriert. Für den gewählten Typ gelten alle Regeln – eine Gruppe, die zum
Baustein wird, behält nur die Rechte ihres Typs, und eine klassische Gruppe
wird abgelehnt, solange klassische Gruppen abgeschaltet sind.

..  _developer-planned:

Geplant
=======

Der folgende Teil des Relaunch ist für Version 1.1 geplant und noch nicht
verfügbar:

*   Bearbeiten im Modul „Rollen & Bausteine“ (eine Matrix aus Rollen und
    Bausteinen). Die Übersicht ohne Bearbeitung ist bereits verfügbar, siehe
    :ref:`usage-module`.

..  _developer-contributing:

Mitwirken
=========

Siehe
`CONTRIBUTING.de.md <https://github.com/Cretection/be_groups/blob/main/CONTRIBUTING.de.md>`__
und die
`Coding-Leitlinien <https://github.com/Cretection/be_groups/blob/main/CODING_GUIDELINES.de.md>`__
im Repository.
