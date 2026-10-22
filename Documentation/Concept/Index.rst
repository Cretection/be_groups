..  include:: /Includes.rst.txt

..  _concept:

=======
Concept
=======

..  _concept-principles:

Principles
==========

Core native
    Permissions are stored in the core fields of :sql:`be_groups` only. The
    composition of a role is the core field :sql:`subgroup`. The extension
    stores exactly one additional field: the kind
    (:sql:`tx_begroups_kind`). TYPO3 resolves groups and permissions as
    usual.

What is not visible does not apply
    TYPO3 evaluates *all* permission fields of a group, regardless of what
    a form displays. Therefore a building block may only have values in the
    permission fields its kind displays. Permission fields that do not belong
    to the kind are cleared when the group is saved. Other data on
    :sql:`be_groups`, for example identifiers of synchronisation tools, is
    never touched.

Guide, don't break
    After installation, all existing groups keep working unchanged as kind
    "Classic (all settings)". Wizards and checks lead step by step to the
    clean model.

..  _concept-kinds:

Kinds
=====

The kinds follow the official TYPO3 guideline
`Setting up backend user groups
<https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Administration/PermissionsManagement/SettingUpBackendGroups/Index.html>`__,
extended by "TSconfig" and "Workspace".

..  list-table::
    :header-rows: 1
    :widths: 18 20 10 52

    *   -   Identifier
        -   Name
        -   Prefix
        -   Fields of the kind
    *   -   ``role``
        -   Role
        -   ``META``
        -   Only :sql:`subgroup`, which may only contain building blocks.
    *   -   ``acl``
        -   Access rights
        -   ``ACL``
        -   Modules (:sql:`groupMods`), table permissions
            (:sql:`tables_modify`, :sql:`tables_select`), allowed excludefields
            (:sql:`non_exclude_fields`), explicitly allowed values
            (:sql:`explicit_allowdeny`), page types (:sql:`pagetypes_select`),
            custom options (:sql:`custom_options`), MFA providers
            (:sql:`mfa_providers`), dashboard widgets
            (:sql:`availableWidgets`, with EXT:dashboard)
    *   -   ``page_group``
        -   Page permission group
        -   ``PG``
        -   No own fields. Used as owner group of pages; put it first in a
            role, see :ref:`concept-combination`.
    *   -   ``db_mount``
        -   Page tree mount
        -   ``DBM``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   File mount
        -   ``FM``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   File operations
        -   ``FO``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Category mount
        -   ``CM``
        -   :sql:`category_perms`
    *   -   ``language``
        -   Languages
        -   ``L``
        -   :sql:`allowed_languages`
    *   -   ``tsconfig``
        -   TSconfig
        -   ``TS``
        -   :sql:`TSconfig`, :sql:`tsconfig_includes`
    *   -   ``workspace``
        -   Workspace
        -   ``WS``
        -   :sql:`workspace_perms` (only with EXT:workspaces)
    *   -   ``classic``
        -   Classic (all settings)
        -   –
        -   All fields in the core layout. This is how TYPO3 groups work
            without this extension.

All kinds additionally have a title, the toggle :guilabel:`Group enabled`
(tab :guilabel:`Access`) and a description (tab :guilabel:`Notes`). The title
does not need to contain the kind: TYPO3 shows the prefix of the kind in
front of it, see :ref:`usage-prefixes`.

Only the configured kinds are valid: the items of the field
:sql:`tx_begroups_kind` that have a form of their own. A stored value that is
no such item – for example the kind of an uninstalled extension, or a value
written by SQL – is neither a role nor a building block. TYPO3 shows such a
group in the full form of the core, so every value in effect stays visible,
and the rules empty none of them. They do not restrict its permission fields
either: values entered in the form are saved and take effect wherever the
group is still a member, so choose a kind before you edit such a group. The
group keeps its stored subgroups, but gets no new ones until a kind is chosen
(R3). It cannot be added to a role or
assigned to a user: the forms of roles and users list it under a heading of
its own, and the module lists it separately.

Without EXT:workspaces, the kind "Workspace" is not configured either, so
"Workspace" building blocks have an unknown kind. As every user works in the
live workspace then, TYPO3 ignores their access to the live workspace
(:sql:`workspace_perms`), and no form shows the field. Keep them if
EXT:workspaces may be installed again: they take effect again then. Do not
choose another kind for them: that clears :sql:`workspace_perms` (R1), and
their users lack the access to the live workspace once EXT:workspaces is
installed again. If you no longer use workspaces, remove them from their
roles and delete them, see :ref:`migration-without-workspaces`.

..  _concept-combination:

How TYPO3 combines building blocks
==================================

TYPO3 merges the permissions of all groups of a user – the building blocks of
all their roles – and of the user record. For most kinds, every building block
adds permissions. Four things work differently:

Languages and category mounts restrict
    A user without any language may edit all languages, and a user without any
    category mount sees all categories. A "Languages" or "Category mount"
    building block therefore grants its languages or categories and at the
    same time restricts the user to the languages or categories of all their
    groups – in all their roles. A role without such a building block allows
    all languages and categories, unless another role of the user restricts
    them, and removing or hiding the last such building block of a user
    gives the user all languages or categories. Give every role whose users
    are to be restricted a "Languages" or "Category mount" building block;
    roles can share one.

The live workspace needs a "Workspace" building block
    With EXT:workspaces, a user may only work in the live workspace if one of
    their groups or the user record allows it (:sql:`workspace_perms`,
    :guilabel:`Edit Live (Online)`). TYPO3 allows it neither for new groups
    nor for new users. As user records stay free of permissions, give every
    role whose users work in the live workspace a "Workspace" building block
    that allows it; one shared building block is enough. Users without live
    access and without membership in a custom workspace see no module at all
    (*No workspace access*). Custom workspaces are granted in the workspace
    record, where roles and building blocks can be members or owners.
    Without EXT:workspaces, every user works in the live workspace, and TYPO3
    ignores :sql:`workspace_perms` until EXT:workspaces is installed again,
    see :ref:`migration-without-workspaces`.

The first building block owns the new pages
    TYPO3 makes the first group it resolves for a user the owner group of the
    pages the user creates. It resolves the subgroups of a group before the
    group itself, so this is the first building block of the user's first
    role that is not hidden. By default, members of the owner group may show
    and edit these pages, create subpages and edit their content
    (``$GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPermissions']['group']``).
    Every other role that contains this building block gets this access to
    the new pages as well. Put a "Page permission group" of the role first,
    as the assistants do (see :ref:`migration`), or set the owner group of
    new pages with ``TCEMAIN.permissions.groupid`` in page TSconfig.

Page tree mounts need page permissions
    A page tree mount only shows the pages the user may read. Give the pages
    a "Page permission group" of the role as group in the core module
    :guilabel:`Permissions`, or grant the access to everybody there.

..  _concept-rules:

Rules
=====

The extension enforces four rules whenever a group or a backend user is
saved – in the backend form, via the DataHandler API, by import or sync
tools. Soft-deleted records follow the rules as well, so a restored record
never brings back a forbidden configuration.

R1: A building block only grants what its kind displays
    Permission fields that do not belong to the kind are cleared. The
    managed fields are the permission fields of :sql:`be_groups` in the core
    and every field shown in the form of a role or a building block, unless
    the forms of all kinds show it (see :ref:`concept-limitations`). This
    also applies to default values: a new group of the kind "Page tree mount"
    does not get the default file permissions.

R2: A role only gets building blocks added
    Roles, classic groups, groups with an unknown kind, deleted or
    non-existing groups and the role itself cannot be added to a role. A
    role has no own permission fields (follows from R1).

R3: Only roles and classic groups have subgroups
    Building blocks have none (follows from R1). A group of an unknown kind
    keeps its stored subgroups, but gets no new ones until a kind is chosen.

R4: Backend users only get roles assigned
    As long as classic groups are allowed (see :ref:`configuration`),
    backend users may also get classic groups, and the message of a
    rejection names both.

Details of the rules:

*   **Stored relations are kept.** R2 and R4 only reject relations that are
    *added*. A role that already contains a classic group, or a user who
    already has a building block, keeps it – switching off classic groups or
    migrating never takes away access. The module marks such entries. A
    relation counts as stored only if TYPO3 reads it: a stored
    ``be_groups_12`` is no relation for TYPO3, so adding group 12 is checked.
    An import of EXT:impexp with :guilabel:`Update records` keeps stored
    relations as well, although it first writes the fields empty, or with
    their default in the TCA, and then sets the relations again.
*   **The order is kept.** The order of the members of a role and of the
    groups of a user matters for the TSconfig inheritance; rejected entries
    are removed, the others keep their position.
*   **Every notation is understood.** Relations are checked whether they are
    given as uid, as ``uid|label``, URL-encoded, with table prefix
    (``be_groups_12``) or as ``NEW…`` placeholder of a group that is created
    in the same DataHandler call. A placeholder that is also used for a
    record of another table is rejected: it could stand for another record
    when the relation is stored.
*   **Default relations are checked.** New users and roles that do not set
    their groups get the default of the TCA and of ``TCAdefaults`` (including
    type-specific defaults). The rules check this default like any other
    value and store the result explicitly.
*   **New records without kind** get the kind the DataHandler would set: the
    default of the TCA, overridden by ``TCAdefaults`` in user TSconfig,
    overridden by ``TCAdefaults`` in page TSconfig. Groups are stored on the
    root level, so only the global page TSconfig applies to them, for example
    :file:`Configuration/page.tsconfig` of a site package. The TSconfig of a
    page, even of a root page, has no effect on groups. The rules are checked
    for exactly this kind, and exactly this kind is stored.

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/page.tsconfig

        TCAdefaults.be_groups.tx_begroups_kind = role
*   **Unknown kinds are ignored.** A value that is no configured kind is not
    stored; the group keeps its kind. A new group gets the default kind, or
    the kind a listener of an event chooses, see :ref:`developer-kind-event`.

The rules correct the data instead of aborting the save operation; only a
classic group that would be created while classic groups are disabled is not
created at all. Import and synchronisation tools therefore keep
working. Every intervention is written to the system log
(:guilabel:`Administration > Log`) and, in the backend, shown to the editor
as message. Each entry names the record: the title with the prefix of its
kind and the uid, for a new record the uid it got, and for a record that was
not created the title it was given.

Permission values that do not belong to the kind of the group (R1, R3) are
emptied and reported as information: after a change of the kind – this is
what the change is meant to do – as well as when the caller sends them in
fields of another kind, for example a synchronisation tool that sets the
same fields for all groups. Rejected relations (R2, R4, and the new
subgroups of a group with an unknown kind, R3), unknown kinds and rejected
records are reported as user error. Fields the caller empties
itself are not reported, nor are default values a new group never gets.
Corrections are reported after the save operation has completed:
if a save is aborted, for example because the password confirmation of the
sudo mode is cancelled, no correction is logged. A rejected record or a
rejected change to a classic group is reported right away.

..  _concept-classic:

Classic groups
==============

A group of the kind "Classic (all settings)" is a backend group as TYPO3
knows it without this extension: it shows all permission fields, may have
subgroups and is not restricted by the rules R1 and R3. After installation,
all existing groups are of this kind, so nothing changes for your users.

Classic groups are the bridge to the clean model: convert them step by step
and finally switch off classic groups in the extension configuration.

..  _concept-limitations:

Known limitations
=================

Permissions on backend user records
    Backend user records have permission fields of their own, for example
    file permissions, modules, TSconfig, mounts and, with EXT:workspaces,
    access to the live workspace. TYPO3 merges them with
    the permissions of the groups. The role model cannot control them. Keep
    backend user records free of permissions and grant everything through
    roles. The consistency check reports permissions on user records, see
    :ref:`usage-audit`.

    New users get all file operations by default (core default). To create
    users without them, set an empty default in the global page TSconfig;
    backend users are stored on the root level as well:

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/page.tsconfig

        TCAdefaults.be_users.file_permissions =

    The form of a new user then starts without file operations, and users
    created through the DataHandler, for example with
    ``vendor/bin/typo3 backend:user:create`` or by an import, get none either.
    Existing users keep theirs; clear them in the user record.

Permission fields of other extensions
    A permission field that another extension adds to :sql:`be_groups`
    belongs to no kind if only the form of classic groups shows it – for
    example because the extension is loaded before this one – or if the
    forms of all kinds show it, because the extension adds it to every type
    (``addToAllTCAtypes()`` without a list of types). The field
    :guilabel:`Permission Sets` of
    `b13/permission-sets <https://github.com/b13/permission-sets>`__ is such
    a field: a permission set can grant permissions of several kinds. Rule R1
    does not restrict fields of no kind. Assign permission fields to a kind,
    see :ref:`developer-fields`. The consistency check reports values in
    fields of no kind on roles and building blocks (``unassigned-fields``),
    and the assistants do not convert groups that have values in them.

Rejected records during imports
    When a rule rejects a record or a relation, for example a classic group
    while classic groups are disabled, the DataHandler stores the rest and
    reports no error. The rejection is neither in the error list of the
    DataHandler that import tools usually evaluate nor in the stored data, so
    the consistency check cannot find it either. After an import, check the
    system log (:guilabel:`Administration > Log`): every rejection is an
    entry for the table :sql:`be_groups` or :sql:`be_users` that names the
    record, a record that was not created by its title. Scripts find
    these entries in :sql:`sys_log` with ``type`` 4 and ``error`` 1. The
    consistency check (see :ref:`usage-audit`) finds data written around the
    DataHandler, for example by SQL.
