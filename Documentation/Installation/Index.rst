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
or install it in the module :guilabel:`System > Extensions`. Then run the
database analyzer in :guilabel:`Admin Tools > Maintenance`.

..  _installation-after:

After the installation
======================

*   All existing backend groups are of kind "Classic". Nothing changes for
    your users.
*   Convert the existing groups with the assistants, see :ref:`migration`,
    or create the first building blocks and roles yourself, see
    :ref:`concept`.

..  _installation-update:

Updating
========

Read the changelog of the new version first, see :ref:`changelog`. After
updating, flush all caches and set the extension up again:

..  code-block:: bash

    vendor/bin/typo3 cache:flush
    vendor/bin/typo3 extension:setup

TYPO3 keeps the configuration of its services in a cache. Until it is
flushed, saving groups and users can fail with an error.

..  _installation-uninstall:

Uninstallation
==============

Remove the extension with :bash:`composer remove cretection/be-groups` (or
in the extension manager). All permissions stay in the core fields and keep
working: roles become plain groups with subgroups. Afterwards the database
analyzer offers to remove the column :sql:`be_groups.tx_begroups_kind`.
