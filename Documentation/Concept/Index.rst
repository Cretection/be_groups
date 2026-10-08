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
    "Classic". Wizards and checks lead step by step to the clean model.

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
        -   No own fields. Used as owner group for page permissions.
    *   -   ``db_mount``
        -   Page tree entry points
        -   ``DBM``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   File mounts
        -   ``FM``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   File operations
        -   ``FO``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Category mounts
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
        -   Classic
        -   –
        -   All fields in the core layout. This is how TYPO3 groups work
            without this extension.

All kinds additionally have a title, a description and the "disabled"
flag. The title does not need to contain the kind: TYPO3 shows the prefix of
the kind in front of it, see :ref:`usage-prefixes`.

Only the configured kinds are valid: the items of the field
:sql:`tx_begroups_kind` that have a form of their own. A stored value that is
no such item – for example the kind of an uninstalled extension, or a value
written by SQL – is neither
a role nor a building block. Such groups are not changed, cannot be added to
roles and are listed separately in the module.

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
    and every field shown in the form of a role or a building block. This
    also applies to default values: a new "Page tree entry points" group does
    not get the default file permissions.

R2: A role only gets building blocks added
    Roles, classic groups, groups with an unknown kind, deleted or
    non-existing groups and the role itself cannot be added to a role. A
    role has no own permission fields (follows from R1).

R3: Building blocks have no subgroups
    Only roles and classic groups may have subgroups.

R4: Backend users only get roles assigned
    As long as classic groups are allowed (see :ref:`configuration`),
    backend users may also get classic groups.

Details of the rules:

*   **Stored relations are kept.** R2 and R4 only reject relations that are
    *added*. A role that already contains a classic group, or a user who
    already has a building block, keeps it – switching off classic groups or
    migrating never takes away access. The module marks such entries. A
    relation counts as stored only if TYPO3 reads it: a stored
    ``be_groups_12`` is no relation for TYPO3, so adding group 12 is checked.
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
    overridden by ``TCAdefaults`` in page TSconfig. The rules are checked for
    exactly this kind, and exactly this kind is stored.
*   **Unknown kinds are ignored.** A value that is no configured kind is not
    stored; the group keeps its kind (or a new group gets the default kind).

The rules correct the data instead of aborting the save operation; only a
group that would be created as "classic" while classic groups are disabled
is not created at all. Import and synchronisation tools therefore keep
working. Every intervention is written to the system log
(:guilabel:`Administration > Log`) and, in the backend, shown to the editor
as message: emptied fields of a former kind as information (this is what a
change of the kind is meant to do), rejected values and records as user
error. Fields the caller empties itself are not reported. Corrections are
reported after the save operation has completed:
if a save is aborted, for example because the password confirmation of the
sudo mode is cancelled, no correction is logged. A rejected record or a
rejected change to "classic" is reported right away.

..  _concept-classic:

The kind "Classic"
==================

"Classic" is a backend group as TYPO3 knows it without this extension: it
shows all permission fields, may have subgroups and is not restricted by the
rules R1 and R3. After installation, all existing groups are of kind
"Classic", so nothing changes for your users.

Classic groups are the bridge to the clean model: convert them step by step
and finally switch off classic groups in the extension configuration.

..  _concept-limitations:

Known limitations
=================

Permissions on backend user records
    Backend user records have permission fields of their own, for example
    file permissions, modules, TSconfig and mounts. TYPO3 merges them with
    the permissions of the groups. The role model cannot control them. Keep
    backend user records free of permissions and grant everything through
    roles. New users get all file operations by default (core default). The
    consistency check reports permissions on user records, see
    :ref:`usage-audit`.

Permission fields of other extensions
    A permission field that another extension adds to :sql:`be_groups` only
    through the core form – because the extension is loaded before this one
    and does not assign the field to a kind – is only shown for classic
    groups and is not enforced by rule R1. Assign such fields to a kind, see
    :ref:`developer-fields`.

Rejected records during imports
    When a rule rejects a record, for example a classic group while classic
    groups are disabled, this is visible in the system log, but not in the
    error list of the DataHandler that import tools usually evaluate. Run
    the consistency check after imports, see :ref:`usage-audit`.
