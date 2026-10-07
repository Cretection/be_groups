..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

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
*   If you used an earlier version of this extension (0.0.x or the AOE
    version 1.x), run the upgrade wizard, see :ref:`migration`.
*   Create the first building blocks and roles, see :ref:`concept`.

..  _installation-uninstall:

Uninstallation
==============

Remove the extension with :bash:`composer remove cretection/be-groups` (or
in the extension manager). All permissions stay in the core fields and keep
working: roles become plain groups with subgroups. Afterwards the database
analyzer offers to remove the column :sql:`be_groups.tx_begroups_kind`.
