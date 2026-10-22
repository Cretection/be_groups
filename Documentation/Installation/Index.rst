..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements and compatibility
==============================

*   TYPO3 14.3 LTS and PHP 8.2 to 8.5.
*   TYPO3 15 will be supported by the same major version 1.x as soon as it is
    released. The extension is tested continuously against the development
    version of TYPO3 15 and only uses APIs that are neither deprecated nor
    internal in TYPO3 14.3 and TYPO3 15.
*   Support for a TYPO3 version ends together with the official end of its free
    community support (see https://get.typo3.org); for TYPO3 14 this is
    2029-06-30. Extended Long Term Support (ELTS) does not extend this period.
*   Each major version of the extension supports two consecutive major
    versions of TYPO3, so TYPO3 can be upgraded without switching to a new
    major version of the extension at the same time.

..  _installation-composer:

Composer mode
=============

..  code-block:: bash

    composer require cretection/be-groups

Then set up the extension and update the database schema:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

..  _installation-classic:

Classic mode
============

Download the extension from the
`TYPO3 Extension Repository (TER) <https://extensions.typo3.org/extension/be_groups>`__
or install it in the module :guilabel:`System > Extensions`. Then update the
database schema in :guilabel:`System > Maintenance > Analyze Database
Structure`.

..  _installation-after:

After the installation
======================

*   All existing backend groups are of kind "Classic (all settings)".
    Nothing changes for your users.
*   Convert the existing groups with the assistants, see :ref:`migration`,
    or create the first building blocks and roles yourself, see
    :ref:`concept`.

..  note::

    Updating the language packs (:bash:`language:update` or
    :guilabel:`System > Maintenance > Manage Language Packs`) logs a warning
    for be_groups as long as the TYPO3 translation server has no language
    packs for the extension (status code 404). The warning does no harm: the
    German translation is part of the extension, and other languages show
    the English texts until language packs exist.

..  _installation-update:

Updating
========

Read the changelog of the new version first, see :ref:`changelog`. After
updating, flush all caches and set the extension up again:

..  code-block:: bash

    vendor/bin/typo3 cache:flush
    vendor/bin/typo3 extension:setup

TYPO3 keeps the configuration of its services, the TCA and the labels in
caches. Until they are flushed, saving groups and users can fail with an
error, and the prefixes of the kinds can be missing: TYPO3 then shows the
label of the kind instead, for example ``Role: Editors`` instead of
``META: Editors``.

..  _installation-uninstall:

Uninstallation
==============

Remove the extension with :bash:`composer remove cretection/be-groups` (in
classic mode in the module :guilabel:`System > Extensions`). All permissions
stay in the core fields and keep working: roles become plain groups with
subgroups.

Long lists of subgroups
    The extension enlarges the field :sql:`be_groups.subgroup` to 2048
    characters, because roles combine many building blocks. Without the
    extension, TYPO3 defines the field with 255 characters again: the
    database analyzer offers to change it back, and :bash:`extension:setup`
    changes it without asking. Longer lists are then cut off (MySQL and
    MariaDB without strict mode), or the change fails (in strict mode and on
    PostgreSQL). Look for such lists before you remove the extension:

    ..  code-block:: sql

        SELECT uid, title FROM be_groups WHERE LENGTH(subgroup) > 255;

    If the query finds groups, keep the length in your site package:

    ..  code-block:: php
        :caption: EXT:my_sitepackage/Configuration/TCA/Overrides/be_groups.php

        <?php

        declare(strict_types=1);

        defined('TYPO3') or die();

        $GLOBALS['TCA']['be_groups']['columns']['subgroup']['config']['dbFieldLength'] = 2048;

The kinds
    After the removal, the database analyzer offers to remove the column
    :sql:`be_groups.tx_begroups_kind`. It holds the kinds of the groups.
    Keep it if you may install the extension again: every group then has
    its kind as before. If you remove it, all groups are of kind
    "Classic (all settings)" after a new installation, with unchanged
    permissions. The analyzer first renames the column to
    :sql:`zzz_deleted_tx_begroups_kind`. As long as that column exists, you
    can restore the kinds after a new installation, and then check the
    groups with ``begroups:audit``:

    ..  code-block:: sql

        UPDATE be_groups SET tx_begroups_kind = zzz_deleted_tx_begroups_kind
            WHERE zzz_deleted_tx_begroups_kind <> '';
