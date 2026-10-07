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
    a form displays. Therefore a building block may only have the fields its
    kind displays. Fields that do not belong to the kind are cleared when
    the group is saved.

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
        -   ``R_``
        -   Only :sql:`subgroup`, which may only contain building blocks.
    *   -   ``acl``
        -   Access rights
        -   ``ACL_``
        -   Modules (:sql:`groupMods`), table permissions
            (:sql:`tables_modify`, :sql:`tables_select`), allowed excludefields
            (:sql:`non_exclude_fields`), explicitly allowed values
            (:sql:`explicit_allowdeny`), page types (:sql:`pagetypes_select`),
            custom options (:sql:`custom_options`), MFA providers
            (:sql:`mfa_providers`), dashboard widgets
            (:sql:`availableWidgets`, with EXT:dashboard)
    *   -   ``page_group``
        -   Page permission group
        -   ``PG_``
        -   No own fields. Used as owner group for page permissions.
    *   -   ``db_mount``
        -   Page tree entry points
        -   ``DBM_``
        -   :sql:`db_mountpoints`
    *   -   ``file_mount``
        -   File mounts
        -   ``FM_``
        -   :sql:`file_mountpoints`
    *   -   ``file_operations``
        -   File operations
        -   ``FO_``
        -   :sql:`file_permissions`
    *   -   ``category_mount``
        -   Category mounts
        -   ``CM_``
        -   :sql:`category_perms`
    *   -   ``language``
        -   Languages
        -   ``L_``
        -   :sql:`allowed_languages`
    *   -   ``tsconfig``
        -   TSconfig
        -   ``TS_``
        -   :sql:`TSconfig`, :sql:`tsconfig_includes`
    *   -   ``workspace``
        -   Workspace
        -   ``WS_``
        -   :sql:`workspace_perms` (only with EXT:workspaces)
    *   -   ``classic``
        -   Classic
        -   –
        -   All fields in the core layout. This is how TYPO3 groups work
            without this extension.

All kinds additionally have a title, a description and the "disabled"
flag.

..  _concept-rules:

Rules
=====

The extension enforces four rules whenever a group or a backend user is
saved – in the backend form, via the DataHandler API, by import or sync
tools.

R1: A building block only grants what its kind displays
    Fields that do not belong to the kind are cleared. This also applies to
    default values: a new "Page tree entry points" group does not get the
    default file permissions.

R2: A role only contains building blocks
    Roles and classic groups cannot be part of a role. A role has no own
    permission fields (follows from R1).

R3: Building blocks have no subgroups
    Only roles and classic groups may have subgroups.

R4: Backend users only get roles
    As long as classic groups are allowed (see :ref:`configuration`),
    backend users may also get classic groups.

The rules correct the data and write an entry to the system log instead of
aborting the save operation. Import and synchronisation tools therefore
keep working.

..  _concept-classic:

The kind "Classic"
==================

"Classic" is a backend group as TYPO3 knows it without this extension: it
shows all permission fields, may have subgroups and is not restricted by the
rules R1 and R3. After installation, all existing groups are of kind
"Classic", so nothing changes for your users.

Classic groups are the bridge to the clean model: convert them step by step
and finally switch off classic groups in the extension configuration.
