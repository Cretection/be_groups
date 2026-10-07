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

..  _developer-fields:

Assign your own fields to a kind
================================

If your extension adds a permission field to :sql:`be_groups`, add it to the
kind it belongs to. Otherwise the field is only visible in the kind
"Classic", and rule R1 clears it for all other kinds.

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

Declare a dependency on ``cretection/be-groups`` (``require`` or
``suggest`` in :file:`composer.json`) so that your TCA override is loaded
after this extension.

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

Use a prefixed identifier for your kind (``my_news``, not ``news``) to avoid
collisions with future kinds of this extension.

..  _developer-planned:

Planned
=======

The following parts of the relaunch are planned for version 1.0.0 and are
not available yet:

*   PSR-14 events at the extension points (kind assignment, classification,
    consistency check results).
*   Editing in the module "Roles & Building Blocks" (a matrix of roles and
    building blocks). The read-only overview is available already, see
    :ref:`usage-module`.
*   A consistency check on the command line (``begroups:audit``).
*   Assistants that suggest kinds (``begroups:classify``) and split mixed
    classic groups into building blocks and a role (``begroups:split``).

..  _developer-contributing:

Contributing
============

See
`CONTRIBUTING.md <https://github.com/Cretection/be_groups/blob/main/CONTRIBUTING.md>`__
and the
`coding guidelines <https://github.com/Cretection/be_groups/blob/main/CODING_GUIDELINES.md>`__
in the repository.
