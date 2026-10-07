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
unchanged. Two assistants convert them without changing any effective
permission. Both show their plan with ``--dry-run`` first:

..  code-block:: bash

    vendor/bin/typo3 begroups:classify --dry-run
    vendor/bin/typo3 begroups:classify
    vendor/bin/typo3 begroups:split --all --dry-run
    vendor/bin/typo3 begroups:split --all
    vendor/bin/typo3 begroups:audit

``begroups:classify`` proposes a kind for every classic group and changes the
kind where nothing else changes:

*   A group whose permissions all belong to one kind of building block – for
    example only page tree entry points – becomes that building block, unless
    it is assigned to users directly.
*   A group that grants nothing itself but combines other groups becomes a
    role.
*   A group that grants nothing but owns pages becomes a page group.
*   All other groups are listed: groups to split, and groups that need your
    decision, with the reason – for example an empty group, or a permission
    field that belongs to no kind.

``begroups:split`` splits the groups you name by uid, or with ``--all`` every
group that ``begroups:classify`` proposes to split:

*   The permissions move into one building block per kind, named with the
    prefix of the kind and the title of the group, for example
    ``ACL_Editors`` and ``DBM_Editors``. A hidden group stays hidden; its
    building blocks are only reachable through it, so showing the group
    again restores its permissions as before.
*   **The group keeps its uid and becomes a role.** Users and other groups
    keep their assignments. The new building blocks follow the former
    subgroups, so the precedence of TSconfig stays the same.
*   An existing building block that grants exactly the same is reused instead
    of creating another one – except for TSconfig, whose order matters, and
    except for the first building block of a group without subgroups: TYPO3
    makes the first group of a user the owner group of the pages the user
    creates, so this block must belong to the role alone.

Both assistants write through the DataHandler: every change is in the system
log and the history of the record. Each group is converted in a transaction
and compared with its former state afterwards. If any permission would
differ – for example because another extension changes values while saving
– the transaction is rolled back and the group stays unchanged. This also
applies to database errors; the result names the error, as the entries of
the system log are rolled back as well.

Afterwards:

#.  Check the result with ``begroups:audit`` (see :ref:`usage-audit`) and
    the module *Roles & Building Blocks*.
#.  Decide on the groups the assistants could not convert.
#.  Switch off classic groups, see :ref:`configuration`.

..  attention::

    Changing the kind of a group manually clears all fields that do not
    belong to the new kind (rule R1). Prefer the assistants, or check the
    group before you change its kind.

..  _migration-starter:

Starting with the default groups of TYPO3
=========================================

TYPO3 creates the two recommended groups "Editor" and "Advanced Editor" on
the command line. Split them afterwards to start with two roles:

..  code-block:: bash

    vendor/bin/typo3 setup:begroups:default --groups=Both
    vendor/bin/typo3 begroups:split --all

The result are the roles "Editor" and "Advanced Editor", each with its own
access rights building block (``ACL_Editor``, ``ACL_Advanced Editor``), and
the page tree entry point and file mount building blocks both roles share
(``DBM_Editor``, ``FM_Editor``).
