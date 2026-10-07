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

#.  Update the database structure first: apply all changes in
    :guilabel:`Admin Tools > Maintenance > Analyze Database Structure`, in
    particular the conversion of :sql:`tx_begroups_kind` from a number to a
    text column. Do **not** remove the old columns :sql:`subgroup_*` yet –
    the wizard still reads them for its report. If the column is still
    numeric, the wizard refuses to run and explains why: writing the new
    kinds into a numeric column would store ``0`` for every group.
#.  Run the wizard in :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or
    on the command line:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run beGroups_kindMigration

    Read its output – it lists every group that needs your attention.
#.  Update the reference index:

    ..  code-block:: bash

        vendor/bin/typo3 referenceindex:update

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

The wizard **never changes effective permissions**:

*   **Roles are not changed.** The former form showed the members of META
    groups in the fields :sql:`subgroup_*`, but only :sql:`subgroup` was in
    effect, and earlier versions could empty it. The wizard does not add the
    members listed only in the old fields – that would grant permissions the
    users do not have today. It reports them instead; add them in the role
    form if they are intended.
*   **File permissions get their own building block.** Groups that carry file
    permissions their new kind does not show (in earlier versions often the
    default file permissions) get a new building block of kind
    ``file_operations`` with exactly these permissions. One block is created per distinct set of
    permissions and per hidden state – hidden groups get a hidden block, so
    nothing that was inactive becomes active. The block is added directly
    after the original group wherever that group is used: in roles, in other
    groups and at backend users. A former META group gets the block as a
    member of its own.
*   **Lists without room keep the old state.** If a list that references
    such a group cannot take another entry, the group keeps its file
    permissions and becomes ``classic``. The wizard reports it.
*   **Groups with other settings become classic.** A group that carries
    settings its new kind does not show (for example TSconfig on a former
    "Rights" group) becomes ``classic`` and is reported for review.
*   **Deleted records are migrated as well**, so restoring a deleted group or
    user later does not bring back the old state.

The wizard is repeatable: it only touches groups that still have a numeric
kind and runs again when such groups reappear, for example after restoring
deleted records from a backup.

..  note::

    The former extension setting ``onlyShowMetaGroup`` is not migrated. Its
    successor is :ref:`allowClassicGroups <configuration-allowClassicGroups>`;
    the wizard prints a hint if the old setting is still enabled. Disable
    "Allow classic groups" to keep allowing only roles for backend users.

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
