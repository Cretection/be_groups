..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-groups:

Creating building blocks and roles
==================================

Backend user groups are created in the root of the page tree, as usual.
Below the title, choose the **kind** of the group. The form only shows the
settings of that kind:

*   A building block covers exactly one concern, for example the page tree
    entry points of a site (*Page tree mount*) or the modules and tables an
    editor needs (*Access rights*).
*   A role has no settings of its own. It lists its building blocks and is
    assigned to backend users.

..  figure:: /Images/BuildingBlockForm.png
    :alt: Form of the building block "ACL: Content editing" with the field Kind and a row of icons, one per kind

    A building block of kind "Access rights": its permissions are in the tabs
    "Record Permissions" and "Module Permissions".

The menu :guilabel:`New building block` of the module
:ref:`Roles & Building Blocks <usage-module>` opens the form with the chosen
kind. A change of the kind changes the form, so TYPO3 asks to save the group
and reload the form (*Refresh required*, :guilabel:`Save and refresh`). As
the kind decides which permissions a group may grant, saving may ask for your
password. Enter the title before you change the kind: the group is stored
with the change.

When a group is saved, all permission settings that do not belong to its
kind are removed. A new group does not even get the default values of such
fields, for example the default file permissions. The system log
(*Administration > Log*) records removed values as information, for example
the settings of the former kind after a change of the kind. Default values a
new group never got are not logged, see :ref:`concept-rules`.

..  _usage-role-form:

Composing a role
----------------

The field :guilabel:`Building blocks` of a role is the side-by-side list of
the core. The available groups are grouped by kind; roles and classic groups
appear in groups of their own, marked as not allowed. Adding one of them, or
a group with an unknown kind, is rejected when the role is saved. The order
of the selected building blocks is kept. It matters twice: later building
blocks override the TSconfig of earlier ones, and the first one becomes the
owner group of the pages created by users who have this role as their first
role.

..  figure:: /Images/RoleForm.png
    :alt: Form of the role "META: Content manager" with its building blocks on the left and the available groups, grouped by kind, on the right

    A role lists its building blocks; the available groups are grouped by kind.
    The example is not a complete role: it starts with an "Access rights"
    building block and has no "Workspace" building block. Check your own roles
    as described below.

A role can combine many building blocks: the extension enlarges the field
:sql:`subgroup` to 2048 characters.

TYPO3 merges the building blocks of all roles of a user. Before you assign a
role, check what follows from that, see :ref:`concept-combination`:

*   Without a "Languages" or "Category mount" building block, the users of
    the role may edit all languages and see all categories, unless another of
    their roles restricts them.
*   With EXT:workspaces, the users of the role need a "Workspace" building
    block that allows the live workspace, unless they only work in custom
    workspaces.
*   The first building block owns the pages created by users who have this
    role as their first role; put a "Page permission group" of the role
    first.
*   The pages of its page tree mounts need page permissions for the role.

..  _usage-user-form:

Assigning roles to users
------------------------

The field :guilabel:`Group` of a backend user lists all groups, grouped into
roles, classic groups and building blocks of each kind. Building blocks are
marked "assign through a role"; assigning one directly is rejected when the
user is saved. While classic groups are disabled (see :ref:`configuration`),
the classic groups are listed under "Classic groups (disabled, cannot be
assigned)", and assigning one is rejected as well.

Neither list is filtered. A filtered list would drop every stored value that
is not part of it whenever the form is saved – with the full list, saving a
form keeps every assignment of an existing group.

Deleted groups and groups that do not exist are part of no list, as
everywhere in TYPO3: saving the form of a role or a user removes them, even
if nothing else was changed, and restoring the group in the
:guilabel:`Recycler` afterwards does not bring the assignment back. Restore
a deleted group before you edit the roles and users that contain it. The
module and the consistency check (``role-missing-member``) name the roles
that contain a deleted group.

..  _usage-prefixes:

The kind as prefix
==================

The title of a group does not need to say what the group is: wherever TYPO3
shows a group, its kind is put in front of the title automatically. A role
called "Editors" appears as ``META: Editors``, its page tree entry point as
``DBM: Editors``.

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   - Prefix
        - Kind
    *   - ``META``
        - Role
    *   - ``ACL``
        - Access rights
    *   - ``PG``
        - Page permission group
    *   - ``DBM``
        - Page tree mount
    *   - ``FM``
        - File mount
    *   - ``FO``
        - File operations
    *   - ``CM``
        - Category mount
    *   - ``L``
        - Languages
    *   - ``TS``
        - TSconfig
    *   - ``WS``
        - Workspace

Classic groups and groups with an unknown kind have no prefix. Kinds of other
extensions use their label. A group without a title is shown as, for example,
``DBM: [No title]``.

Lists that TYPO3 builds from the TCA – the record lists and the group fields
of the forms – sort groups by kind (in the order of the kind identifiers)
and then by title, so the groups of one kind stay together. Some core
modules sort by title instead, for example :guilabel:`Users` and
:guilabel:`Permissions`.

..  figure:: /Images/UsersModuleGroups.png
    :alt: List of backend user groups in the core module Users, each title with the prefix of its kind

    The core module :guilabel:`Users` shows the kind in front of every group.

The prefix appears in the group and user forms, in the record lists, in the
permissions module, in the owners and members of workspaces, in the element
browser, in the core module :guilabel:`Users` (lists of users and groups,
group filter, details, comparison and delete confirmation) and in the module
:ref:`Roles & Building Blocks <usage-module>` wherever the section does not
already name the kind.

..  note::

    The core module :guilabel:`Users` prints the titles itself. The extension
    therefore overrides those of its templates that print group titles, with
    copies of the templates of TYPO3 14.3 in which only the titles and the
    icons of the groups differ.
    The copies are only used while the templates of the core are unchanged:
    if a TYPO3 update changes one of them, the module uses the templates of
    the core again and shows the titles without prefix until a new version
    of this extension updates the copies. TYPO3 checks this when it builds
    the page TSconfig; after patching templates of the core without a new
    TYPO3 version, flush the caches.

To use other prefixes, override the labels ``kind.prefix.<kind>`` of
:file:`EXT:be_groups/Resources/Private/Language/db.xlf` with
``$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']``, for example in
a site package:

..  code-block:: php
    :caption: EXT:my_sitepackage/ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['EXT:be_groups/Resources/Private/Language/db.xlf'][]
        = 'EXT:my_sitepackage/Resources/Private/Language/Overrides/be_groups.db.xlf';

The file contains only the labels to change, for example ``kind.prefix.role``
with the source ``ROLE``. Labels in other languages come from a file in the
same folder whose name starts with the language, for example
:file:`de.be_groups.db.xlf` with the target ``ROLLE``. Flush the caches
afterwards.

..  _usage-module:

The module "Roles & Building Blocks"
====================================

Administrators find the module *Administration > Roles & Building Blocks*
next to the *Users* module. It is read-only and shows:

*   every role with its building blocks, grouped by kind, and its users;
    for a role with two or more active TSconfig building blocks also the
    order in which TYPO3 applies them, as later ones override earlier ones,
*   every building block with the roles and other groups using it and the
    users it is assigned to directly,
*   the remaining classic groups,
*   groups with an unknown kind, for example the kind of an uninstalled
    extension, with a warning.

..  figure:: /Images/RolesAndBuildingBlocksModule.png
    :alt: The module Roles & Building Blocks: a table of roles with their building blocks, grouped by kind, and their users

    The module lists every role with its building blocks and users.

..  figure:: /Images/RolesAndBuildingBlocksModuleDark.png
    :alt: The same module in the dark theme of the backend

    The module follows the light and the dark theme of the backend.

Roles can be sorted by title or by number of users, building blocks by title
or by usage. Titles and usernames are sorted alphabetically for the language
of the editor. Building blocks can be filtered by kind. A check mark shows
the selected sorting and kind, and the selection is kept per user.

Entries that do not follow the role model are marked: roles containing
groups that are no building blocks or no longer exist, building blocks that
have subgroups, building blocks assigned to users directly and groups with an
unknown kind. The number of these entries is shown at the top and does not
depend on the kind filter. Building blocks that no group and no user uses
are marked as unused. Hidden groups are marked "disabled" wherever they are
listed; TYPO3 does not apply them.

A member of a role that is no building block is marked "not a building
block" and shown with its kind, for example ``META: Editors`` for a role
within a role. As the row of the role explains, TYPO3 still grants its
permissions; replace it with building blocks that grant the same. Converting
groups with the assistants often leaves such roles, see
:ref:`migration-vanilla`.

Kinds added by other extensions are shown with their own label and icon.

Click a title to edit the group or user in the regular record form. The
button :guilabel:`New role` in the document header creates a role, the menu
:guilabel:`New building block` a building block of the chosen kind, including
the kinds of other extensions.

The module is optional. To hide it, add its identifier to the user TSconfig
of the administrators:

..  code-block:: typoscript
    :caption: User TSconfig

    options.hideModules := addToList(begroups_overview)

..  _usage-audit:

Checking groups and users
=========================

The rules apply to every save operation. Data written around the
DataHandler – SQL, synchronisation tools – and
assignments stored before the extension was installed are found by the
consistency check. It reads all groups and users and changes nothing:

..  code-block:: bash

    vendor/bin/typo3 begroups:audit

..  list-table::
    :header-rows: 1
    :widths: 30 12 58

    *   - Check
        - Severity
        - Finding
    *   - ``unknown-kind``
        - ``error``
        - The group has no kind, or its kind is not configured or has no form
          of its own, for example the kind of an uninstalled extension. Choose
          a kind.
    *   - ``classic-group``
        - ``warning``
        - A classic group. Convert it into a role or building blocks:
          ``begroups:classify`` shows how, or what needs your decision, see
          :ref:`migration-vanilla`.
    *   - ``classic-group-disabled``
        - ``error``
        - A classic group, although classic groups are disabled, see
          :ref:`configuration`. Convert it as well.
    *   - ``building-block-with-subgroups``
        - ``error``
        - A building block has subgroups.
    *   - ``foreign-permissions``
        - ``error``
        - A group carries permissions that its kind does not show. They are
          in effect until the group is saved again.
    *   - ``role-missing-member``
        - ``error``
        - A role contains a group that does not exist or is deleted.
    *   - ``role-invalid-member``
        - ``error``
        - A role contains a role, a classic group or a group with an unknown
          kind.
    *   - ``user-building-block``
        - ``error``
        - A building block is assigned to a user directly.
    *   - ``user-permissions``
        - ``warning``
        - A user record carries permissions of its own, which TYPO3 adds to
          those of the roles. New users get all file operations by default
          (core default of :sql:`file_permissions`); clear them in the user
          record. To create new users without them, see
          :ref:`concept-limitations`. With EXT:workspaces, access to the live
          workspace (:sql:`workspace_perms`) counts as well; users created
          before EXT:workspaces was installed have it. Grant it with a
          "Workspace" building block instead, see
          :ref:`concept-combination`.
    *   - ``unassigned-fields``
        - ``warning``
        - A role or a building block has values in fields of other
          extensions that belong to no kind: only the form of classic groups
          or the forms of all kinds show them, so its kind does not restrict
          them. If they grant permissions, assign the fields to a kind, see
          :ref:`developer-fields` and :ref:`concept-limitations`.
    *   - ``unclean-group-list``
        - ``warning``
        - The subgroups of a group or the groups of a user contain duplicates
          or entries that TYPO3 and the backend form read differently, such
          as ``be_groups_5`` or ``05``. Saving the record in the backend can
          add or remove groups; check them first. Spaces, empty entries and
          ``0`` are not reported: TYPO3 and the form ignore them alike.
    *   - ``user-ignores-group-mounts``
        - ``warning``
        - The field :guilabel:`Mount from groups` (:sql:`options`) of a user
          does not include all page tree and file mounts of the groups.

Hidden groups and disabled users are checked as well and marked
"(disabled)". Administrators and deleted records are skipped. Extensions can
add checks of their own, see :ref:`developer-audit`.

Options:

``--format=json``
    Prints the result as JSON (``errors``, ``warnings`` and ``findings``
    with ``severity``, ``identifier``, ``table``, ``uid``, ``title`` and
    ``message``) for monitoring and further processing. The ``title`` is the
    title as the backend shows it, for groups with the prefix of their kind,
    or the username.

``--fail-on-warnings``
    Treats warnings like errors when it comes to the exit code.

The exit code makes the check usable in deployments, CI pipelines and
monitoring:

..  list-table::
    :header-rows: 1
    :widths: 15 85

    *   - Code
        - Meaning
    *   - ``0``
        - No errors (warnings are allowed unless ``--fail-on-warnings`` is
          set).
    *   - ``1``
        - Errors found, or warnings with ``--fail-on-warnings``.
    *   - ``2``
        - Invalid value of ``--format``; the message goes to the error
          output, not into the JSON. Unknown options are rejected by the
          console with exit code ``1``.

..  code-block:: bash
    :caption: Example: stop a deployment on errors

    vendor/bin/typo3 begroups:audit --format=json > begroups-audit.json
