..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

..  _developer-kinds:

Kinds are TCA types
===================

The kind is the type field of :sql:`be_groups`
(``ctrl.type = tx_begroups_kind``). Each kind is a regular entry in
``$GLOBALS['TCA']['be_groups']['types']``. There is no proprietary
registry – you use the TCA API you already know.

A kind is valid if it is an item of the field :sql:`tx_begroups_kind` and has
a type of its own. Everything except ``role`` and ``classic`` is a building
block. Other values – kinds of an uninstalled extension, items without a type
of their own, values written by SQL – are neither roles nor building blocks:
their groups have an unknown kind.

..  _developer-fields:

Assign your own fields to a kind
================================

If your extension adds a permission field to :sql:`be_groups`, add it to the
kind it belongs to and to ``classic``. As soon as a field is shown in the form
of a role or a building block, rule R1 manages it: it is kept for that kind
and cleared for all others. Classic groups show every permission field, like
the groups of TYPO3 without this extension, and after the installation all
groups are classic: without ``classic``, the field cannot be set in their form
until they are converted. A field that is not assigned to any kind (and is no
permission field of the core) is not enforced: neither a field that only
classic groups show nor one that the forms of all kinds of this extension
show, as ``addToAllTCAtypes()`` without a list of types adds it – see
:ref:`concept-limitations`. Fields that grant no permissions, such as
identifiers of a synchronisation tool, are never touched.

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_groups.php

    <?php

    declare(strict_types=1);

    use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

    defined('TYPO3') or die();

    ExtensionManagementUtility::addToAllTCAtypes(
        'be_groups',
        'tx_myextension_permissions',
        'acl,classic',
        'after:custom_options',
    );

Declare a dependency on ``cretection/be-groups`` (``require`` or
``suggest`` in :file:`composer.json`) so that your TCA override is loaded
after this extension. This extension creates the types of its kinds when its
own override is loaded, ``classic`` as a copy of the core form. Before that,
the types ``acl`` and ``classic`` do not exist, and the field is shown in no
form.

..  _developer-own-kind:

Add your own kind
=================

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
            'group' => 'buildingBlocks',
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

    // Classic groups show every permission field
    ExtensionManagementUtility::addToAllTCAtypes(
        'be_groups',
        'tx_myextension_news_permissions',
        'classic',
        'after:custom_options',
    );

Use a prefixed, non-numeric identifier for your kind (``my_news``, not
``news``) to avoid collisions with future kinds of this extension. The item
group ``buildingBlocks`` lists the kind among the building blocks of the
field :guilabel:`Kind`; without it, the kind is listed above all groups. The
record lists of TYPO3 sort groups by the identifier of their kind and then
by title, so the identifier also decides where the groups of your kind are
listed there, see :ref:`usage-prefixes`.

The label of your kind is shown as prefix in front of the titles of its
groups, see :ref:`usage-prefixes`. Give your kind an icon in its item and in
``typeicon_classes``, as above: TYPO3 shows the icon of the item in the field
:guilabel:`Kind` and in the module "Roles & Building Blocks", and the one of
``typeicon_classes`` as the icon of its groups, for example in the record
lists and in the core module :guilabel:`Users`. Without an icon in the item,
the module shows a question mark, as for groups of an unknown kind; without
``typeicon_classes``, the groups of your kind get the icon of roles.

Optionally give your kind a label in the lists of the role and user forms,
which are grouped by kind (otherwise the lists show its identifier as
heading). Groups of a kind that is not configured (any more), for example
after your extension is uninstalled, are listed under "Groups with an
unknown kind (cannot be added to a role)" in the form of a role and under
"Groups with an unknown kind (cannot be assigned)" in the form of a user. be_groups keeps the
groups that cannot be added to a role, and the groups of an unknown kind, at
the end of these lists, after all TCA overrides:

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_groups.php

    $GLOBALS['TCA']['be_groups']['types']['role']['columnsOverrides']['subgroup']['config']['itemGroups']['my_news']
        = 'my_extension.db:be_groups.kind.news';

In the form of a user, give the label the hint of the building blocks of this
extension, for example ``News (building blocks, assign through a role)``:
building blocks are assigned through roles, a direct assignment is rejected
(R4).

..  code-block:: php
    :caption: EXT:my_extension/Configuration/TCA/Overrides/be_users.php

    $GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['itemGroups']['my_news']
        = 'my_extension.db:be_users.usergroup.group.news';

..  _developer-audit:

Adding checks to the consistency check
======================================

The event :php:`\Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent`
is dispatched after the built-in checks of ``begroups:audit``. Listeners read
the findings with :php:`getFindings()` and add their own with
:php:`addFinding()`. The command sorts and counts all findings, so findings
of listeners affect the exit code like any other.

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
            // Your check, for example based on the kinds of your extension
            $event->addFinding(new AuditFinding(
                AuditSeverity::Warning,
                'my-news-editor-without-storage',
                'be_groups',
                12,
                'META: News editors',
                'The role grants the news module, but no storage folder.',
            ));
        }
    }

Use an identifier with a prefix of your extension, so it cannot collide with
future checks of this extension. Pass the title as the backend shows it, as
the findings of this extension do: groups with the prefix of their kind.
:php:`\TYPO3\CMS\Backend\Utility\BackendUtility::getRecordTitle('be_groups', $row)`
returns it. :php:`AuditFinding`, :php:`AuditSeverity` and the event are
public API and follow semantic versioning.

..  _developer-kind-event:

Choosing the kind of new groups
===============================

Import and synchronisation tools (e.g. for LDAP) often create groups through
the DataHandler without a kind. Such groups get the default kind: the TCA
default, overridden by ``TCAdefaults.be_groups.tx_begroups_kind`` in user
TSconfig and in the global page TSconfig (groups are stored on the root
level, so the TSconfig of a page has no effect, see :ref:`concept-rules`).
The event
:php:`\Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent` lets you choose
the kind from the values of the record instead, for example from a prefix of
the title:

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

The event is dispatched for every new group without a valid kind: the record
sets no kind, an empty one or one that is not configured. Such a value is
ignored and logged as user error. A configured kind is never replaced. A kind
that a listener sets and that is not configured is ignored as well: the group
gets the default kind. All rules apply to the chosen kind – a group that becomes a building block keeps only
the permissions of its kind, and a classic group is rejected while classic
groups are disabled.

..  _developer-planned:

Planned
=======

The following part of the relaunch is planned for version 1.1 and is not
available yet:

*   Editing in the module "Roles & Building Blocks" (a matrix of roles and
    building blocks). The read-only overview is available already, see
    :ref:`usage-module`.

..  _developer-contributing:

Contributing
============

See
`CONTRIBUTING.md <https://github.com/Cretection/be_groups/blob/main/CONTRIBUTING.md>`__
and the
`coding guidelines <https://github.com/Cretection/be_groups/blob/main/CODING_GUIDELINES.md>`__
in the repository.
