..  include:: /Includes.rst.txt

..  _migration:

=========
Migration
=========

..  _migration-legacy:

From be_groups 0.0.x or the AOE version 1.x
===========================================

Earlier versions stored the kind as a number and the composition of META
groups in additional columns (:sql:`subgroup_r`, :sql:`subgroup_pm`, …).
The upgrade wizard :guilabel:`Migrate be_groups kinds`
(identifier ``beGroups_kindMigration``) converts this data:

#.  Update the database schema first (:guilabel:`Admin Tools > Maintenance >
    Analyze Database Structure`). Do **not** remove the old columns yet –
    the wizard still reads them.
#.  Run the wizard in :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or
    on the command line:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run beGroups_kindMigration

#.  Run the database analyzer again and remove the old columns
    :sql:`subgroup_*`.

What the wizard does:

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Old kind
        -   New kind
    *   -   0 "Default all"
        -   ``classic``
    *   -   1 "Rights"
        -   ``acl``
    *   -   2 "Language"
        -   ``language``
    *   -   3 "Meta"
        -   ``role``
    *   -   4 "Page access group"
        -   ``page_group``
    *   -   5 "File mount"
        -   ``file_mount``
    *   -   6 "Page mount"
        -   ``db_mount``
    *   -   7 "TSconfig"
        -   ``tsconfig``
    *   -   8 "Workspace"
        -   ``workspace``
    *   -   9 "Category"
        -   ``category_mount``

*   **Roles are repaired:** the :sql:`subgroup` field of former META groups
    becomes the union of :sql:`subgroup` and all old :sql:`subgroup_*`
    columns. Earlier versions could empty :sql:`subgroup` in some
    situations; the wizard restores it and reports the differences.
*   **File permissions get their own building block:** former "Rights" and
    "File mount" groups with file permissions get a new building block of
    kind ``file_operations``. It is added automatically to every role that
    contained the original group, so no user loses permissions.

The wizard can be run several times; it only changes what is not migrated
yet.

..  _migration-vanilla:

From a TYPO3 installation without this extension
================================================

After the installation, all groups are of kind "Classic" and keep working
unchanged. Convert them step by step:

#.  Create building blocks for page tree entry points, file mounts,
    languages and access rights – or change the kind of existing groups that
    already serve a single purpose.
#.  Create roles that combine these building blocks.
#.  Assign the roles to your backend users instead of the classic groups.
#.  Switch off classic groups, see :ref:`configuration`.

..  attention::

    Changing the kind of an existing group clears all fields that do not
    belong to the new kind (rule R1). Check the group before you change its
    kind.

Assistants that suggest kinds and split mixed groups automatically are
planned, see :ref:`developer-planned`.
