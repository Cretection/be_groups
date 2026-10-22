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

After the installation, all groups are of kind "Classic (all settings)" and
keep working unchanged. Two assistants convert them without changing any
effective permission. Both show their plan with ``--dry-run`` first:

..  code-block:: bash

    vendor/bin/typo3 begroups:classify --dry-run
    vendor/bin/typo3 begroups:classify
    vendor/bin/typo3 begroups:split --all --dry-run
    vendor/bin/typo3 begroups:split --all
    vendor/bin/typo3 begroups:audit

``begroups:classify`` proposes a kind for every classic group and changes the
kind where nothing else changes:

*   A group whose permissions all belong to one kind of building block
    becomes that building block, unless it is assigned to users directly –
    for example, a group with nothing but page tree entry points becomes a
    "Page tree mount".
*   A group that grants nothing itself but combines other groups becomes a
    "Role". A group that grants nothing, combines nothing and owns no pages,
    but is assigned to users directly, becomes a "Role" as well; it keeps its
    users and every reference to its uid, for example as workspace member or
    person responsible for a stage.
*   A group that grants nothing but owns pages becomes a
    "Page permission group", unless it is assigned to users directly.
*   All other groups are listed: groups to split, and groups that need your
    decision, with the reason – for example an empty group that no user has
    directly, a permission field that belongs to no kind (without
    EXT:workspaces also the access to the live workspace, see
    :ref:`migration-without-workspaces`), the root of the
    page tree as page mount, subgroups with duplicates or entries that TYPO3
    and the backend form read differently (such as ``be_groups_5`` or
    ``05``), or page, file or category mounts with entries that the
    DataHandler cannot copy into a building block (such as ``05``,
    ``pages_5`` or empty entries).

``begroups:split`` splits the groups you name by uid, or with ``--all`` every
group that ``begroups:classify`` proposes to split:

*   The permissions move into one building block per kind. The building
    blocks get the title of the group; TYPO3 shows their kind as prefix,
    for example ``ACL: Editors`` and ``DBM: Editors`` (see
    :ref:`usage-prefixes`). Their description names the group, for example
    ``Created by begroups:split from group 17 "Editors".`` A hidden group
    stays hidden; its
    building blocks are only reachable through it, so showing the group
    again restores its permissions as before.
*   **The group keeps its uid and becomes a role.** Users and other groups
    keep their assignments. The new building blocks follow the former
    subgroups, so the precedence of TSconfig stays the same.
*   TYPO3 makes the first group of a user the owner group of the pages the
    user creates. If that is the group itself – it has no active subgroups –
    a new page permission group without permissions (``PG: Editors``)
    becomes its first member and takes over this task. It belongs to the
    role alone, so the owner group has exactly the same members as before.
*   The building blocks are always new, even if an identical one exists. An
    existing group may be referenced elsewhere – as owner group of pages, as
    workspace member or in TSconfig conditions – so making more users members
    of it could grant them more. Merge identical building blocks yourself
    where that is intended.

Both assistants show their plan as a table, print the result of every group
they convert – converted or failed, with the reason – and close with a
summary. ``begroups:split`` also names every group given by uid that it
skips, with the reason; for a group that needs no split, it names the kind
``begroups:classify`` changes it to. The exit code is ``1`` if a conversion
failed or a group given by uid cannot be split (also with ``--dry-run``),
``2`` if the arguments of ``begroups:split`` are invalid, and ``0``
otherwise. A uid is a whole number from 1 to 2147483647, the largest uid
every database of TYPO3 can store.

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
    page permission group.

Otherwise – for example because another extension changes a group or a user
while saving – the transaction is rolled back and the group stays unchanged;
the result names the user or group that changed and its changed fields. This
also applies to database errors; the result names the first three errors the
DataHandler logged and counts the others, as the system log is rolled back
as well. On PostgreSQL, the result only names the aborted transaction. If
another error stops a conversion, for example an exception of another
extension, the conversion is rolled back as well: the command names the
group as failed, counts the groups it did not process, shows the error and
ends with an exit code other than ``0``.

Cache commands of page TSconfig (``TCEMAIN.clearCacheCmd``) run only after
the transaction is committed: flushing caches would end the transaction
early on MySQL and MariaDB. If the cache commands fail after the commit, the
group stays converted and the result says so; clear the caches yourself
then.

Limits of the verification:

*   TYPO3 resolves groups with internal API, so the extension uses a model of
    it; tests compare the model with TYPO3 itself.
*   Conditions that compare the complete list of groups of a user (for
    example ``backend.user.userGroupList``) also see the new building
    blocks. Groups that listeners of the core event
    ``AfterGroupsResolvedEvent`` add at runtime (e.g. single sign-on) are not
    known to the verification.
*   Only groups and users are compared. What another extension changes in
    other tables while a group is converted – for example making a new
    building block a workspace member or the owner group of pages – is
    committed with the conversion, even if it grants the users of the group
    more.
*   The transaction covers the database connection of :sql:`be_groups`: if
    :sql:`sys_log`, :sql:`sys_history` or :sql:`sys_refindex` are mapped to
    another connection, their entries of a rolled back conversion remain.

Afterwards:

#.  Check the result with ``begroups:audit`` (see :ref:`usage-audit`) and
    the module *Roles & Building Blocks*.
#.  Resolve roles that contain roles (``role-invalid-member``). They occur
    when a combining group contained mixed groups, which are roles after the
    split. The permissions are unchanged, but ``begroups:audit`` reports
    each of these roles as an error and exits with code ``1`` until they are
    resolved, so a deployment that runs the check stops there. The
    assistants do not resolve them themselves, because the inner role may
    own pages, be a member of a workspace or be used in TSconfig conditions.
    Check this first: without the inner role, the users of the outer role
    lose that access, and conditions on the inner role no longer match.

    Replace the inner role by its building blocks where that suits you, at
    the position of the inner role. The field adds selected items at the
    end, so move the building blocks up to the inner role with
    :guilabel:`Move selected items upwards` – or with
    :guilabel:`Move selected items to top` if the inner role is the first
    member – before you save. The first group of a user becomes the owner
    group of the pages the user creates, and the order decides the
    precedence of TSconfig. Check the precedence in the module afterwards.

    Converted roles also keep references to groups that were deleted before
    (``role-missing-member``, an error as well). TYPO3 ignores them; saving
    the role in the backend form removes them.
#.  Decide on the groups the assistants could not convert. For an empty
    group that no user has directly, choose a kind; delete it only if no
    group, workspace or TSconfig condition refers to it. A group with the
    root of the page tree as page mount (``0``) cannot be split, because
    TYPO3 does not store the root as page mount of a new building block.
    Keep it classic, or replace the root by the pages on the top level of
    the page tree and split the group then. This is not quite the same:
    pages created on the top level later are not mounted. The backend form
    does not show the root as page mount: saving such a group in the form,
    or a "Page tree mount" building block with the root, removes it. For a
    group that allows editing in the live workspace while EXT:workspaces is
    not installed, see :ref:`migration-without-workspaces`.
#.  Switch off classic groups, see :ref:`configuration`, once
    ``begroups:audit`` reports no classic group (``classic-group``) any
    more. Remaining classic groups keep working, but are reported as errors
    from then on (``classic-group-disabled``).

..  attention::

    Changing the kind of a group manually clears all fields that do not
    belong to the new kind (rule R1). Prefer the assistants, or check the
    group before you change its kind.

..  _migration-without-workspaces:

Installations without EXT:workspaces
====================================

The field :sql:`workspace_perms` of a group (:guilabel:`Edit Live (Online)`)
allows editing in the live workspace. Without EXT:workspaces, every user
works in the live workspace: TYPO3 ignores the field, no form shows it, and
the kind "Workspace" is not configured. A stored value takes effect again as
soon as EXT:workspaces is installed again. After EXT:workspaces has been
removed, most groups usually still have a value in the field, because every
group whose users worked live needed it.

Classic groups with access to the live workspace
    The assistants do not convert them: the field belongs to no kind, and a
    conversion would clear it. ``begroups:classify`` and ``begroups:split``
    give this as the reason. There are two ways out:

    *   If you use workspaces, install EXT:workspaces again and keep it. The
        assistants then convert these groups with "Workspace" building
        blocks.
    *   If you no longer use workspaces, clear the field and run the
        assistants again. No form shows the field, so clear it with SQL:

        ..  code-block:: sql

            UPDATE be_groups SET workspace_perms = 0
                WHERE tx_begroups_kind = 'classic' AND workspace_perms <> 0;

        The statement writes past the DataHandler, so neither the history
        nor the system log records it. As long as EXT:workspaces is not
        installed, it changes no effective permission. If you install
        EXT:workspaces later, grant the access to the live workspace with a
        "Workspace" building block, see :ref:`concept-combination`.

"Workspace" building blocks
    They have an unknown kind (``unknown-kind``, see :ref:`concept-kinds`).
    Keep them, also in their roles, if EXT:workspaces may be installed
    again: they take effect again then. Until then, the consistency check
    also reports the roles that contain them (``role-invalid-member``). Do
    not choose another kind: that clears their access to the live workspace
    (R1), and their users lack it once EXT:workspaces is installed again. If
    you no longer use workspaces, remove them from their roles and delete
    them.

Other roles and building blocks with access to the live workspace
    Their kinds do not show the field. The consistency check reports a
    stored value, for example one written by SQL, as without effect for now
    (``foreign-permissions``); saving the group removes it.

..  _migration-starter:

Starting with the default groups of TYPO3
=========================================

TYPO3 creates the two recommended groups "Editor" and "Advanced Editor" on
the command line. Split them afterwards to start with two roles:

..  code-block:: bash

    vendor/bin/typo3 setup:begroups:default --groups=Both
    vendor/bin/typo3 begroups:split --all

The result is two roles, "Editor" and "Advanced Editor", each with its own
building blocks of the kinds "Page permission group" (the owner group of new
pages), "Access rights", "Page tree mount" and "File mount": ``PG: Editor``,
``ACL: Editor``, ``DBM: Editor`` and ``FM: Editor``.

The presets of TYPO3 do not allow the live workspace. With EXT:workspaces,
add a "Workspace" building block that allows it
(:guilabel:`Edit Live (Online)`) to both roles, or their users see no module,
see :ref:`concept-combination`.

..  attention::

    Split the two groups before you edit them. TYPO3 stores no value in
    their field :sql:`file_permissions`, which grants no file operations.
    The backend form shows the default of the field instead, all file
    operations, so saving one of the groups in the form – even without a
    change – grants all file operations to its users. The building blocks
    of the split store no file operations.
