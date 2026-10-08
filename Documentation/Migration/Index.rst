..  include:: /Includes.rst.txt

..  _migration:

==========================
Converting existing groups
==========================

..  note::

    Data of earlier versions of this extension (0.0.x for TYPO3 11, the AOE
    version 1.x) is not migrated. Install the extension in a TYPO3 14
    installation without it.

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
    decision, with the reason – for example an empty group, a permission
    field that belongs to no kind, the root of the page tree as page mount,
    or subgroups with duplicates or entries that TYPO3 and the backend form
    read differently (such as ``be_groups_5`` or ``05``).

``begroups:split`` splits the groups you name by uid, or with ``--all`` every
group that ``begroups:classify`` proposes to split:

*   The permissions move into one building block per kind. The building
    blocks get the title of the group; TYPO3 shows their kind as prefix,
    for example ``ACL: Editors`` and ``DBM: Editors`` (see
    :ref:`usage-prefixes`). A hidden group stays hidden; its
    building blocks are only reachable through it, so showing the group
    again restores its permissions as before.
*   **The group keeps its uid and becomes a role.** Users and other groups
    keep their assignments. The new building blocks follow the former
    subgroups, so the precedence of TSconfig stays the same.
*   TYPO3 makes the first group of a user the owner group of the pages the
    user creates. If that is the group itself – it has no active subgroups –
    a new page group without permissions (``PG: Editors``) becomes
    its first member and takes over this task. It belongs to the role alone,
    so the owner group has exactly the same members as before.
*   The building blocks are always new, even if an identical one exists. An
    existing group may be referenced elsewhere – as owner group of pages, as
    workspace member or in TSconfig conditions – so making more users members
    of it could grant them more. Merge identical building blocks yourself
    where that is intended.

Both assistants write through the DataHandler: every change is in the system
log and the history of the record. Each group is converted in a
transaction, which is only committed if

*   all other groups and all users are unchanged, and the group itself only
    changed its kind, its subgroups and the permissions that moved into the
    building blocks, and
*   TYPO3 grants every combination of groups a user has, and the group
    itself (also while it is hidden or not assigned yet), the same as
    before: the merged permissions, the workspace permissions, the TSconfig
    in the order TYPO3 applies it, the group memberships apart from the new
    building blocks, and the owner group of new pages apart from the new
    page group.

Otherwise – for example because another extension changes data while
saving – the transaction is rolled back and the group stays unchanged. This
also applies to database errors; the result names the errors the
DataHandler logged, as the system log is rolled back as well. On
PostgreSQL, the result only names the aborted transaction.

Limits of the verification:

*   TYPO3 resolves groups with internal API, so the extension uses a model of
    it; tests compare the model with TYPO3 itself.
*   Conditions that compare the complete list of groups of a user (for
    example ``backend.user.userGroupList``) also see the new building
    blocks. Groups that listeners of the core event
    ``AfterGroupsResolvedEvent`` add at runtime (e.g. single sign-on) are not
    known to the verification.
*   The transaction covers the database connection of :sql:`be_groups`: if
    :sql:`sys_log`, :sql:`sys_history` or :sql:`sys_refindex` are mapped to
    another connection, their entries of a rolled back conversion remain.

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
building blocks: a page group as owner of new pages, access rights, page
tree entry point and file mount (``PG: Editor``, ``ACL: Editor``,
``DBM: Editor`` and ``FM: Editor``).
