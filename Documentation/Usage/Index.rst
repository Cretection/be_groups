..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-groups:

Creating building blocks and roles
==================================

Backend user groups are created in the root of the page tree, as usual. The
first field of a group is its **kind**. After choosing a kind, the form only
shows the settings of that kind:

*   A building block covers exactly one concern, for example the page tree
    entry points of a site (*Page tree mount*) or the modules and tables an
    editor needs (*Access rights*).
*   A role has no settings of its own. It lists its building blocks and is
    assigned to backend users.

When a group is saved, all permission settings that do not belong to its
kind are removed, including default values. The system log
(*Administration > Log*) records every correction made by the extension.

..  _usage-role-form:

Composing a role
----------------

The field :guilabel:`Building blocks` of a role is the side-by-side list of
the core. The available groups are grouped by kind; roles and classic groups
appear in groups of their own, marked as not allowed. Adding one of them is
rejected when the role is saved. The order of the selected building blocks
is kept – it decides the precedence of their TSconfig.

A role can combine many building blocks: the extension enlarges the field
:sql:`subgroup` to 2048 characters.

..  _usage-user-form:

Assigning roles to users
------------------------

The field :guilabel:`Group` of a backend user lists all groups, grouped into
roles, classic groups and building blocks of each kind. Building blocks are
marked "assign through a role"; assigning one directly is rejected when the
user is saved.

Neither list is filtered. A filtered list would drop every stored value that
is not part of it whenever the form is saved – with the full list, saving a
form never removes an existing assignment.

..  _usage-module:

The module "Roles & Building Blocks"
====================================

Administrators find the module *Administration > Roles & Building Blocks*
next to the *Users* module. It is read-only and shows:

*   every role with its building blocks, grouped by kind, and its users,
*   every building block with the roles and other groups using it and the
    users it is assigned to directly,
*   the remaining classic groups,
*   groups with an unknown kind, for example a numeric kind of the former
    extension before the upgrade wizard has run, with a warning.

Roles can be sorted by title or by number of users, building blocks by title
or by usage. Building blocks can be filtered by kind; the selection is kept
per user.

Entries that do not follow the role model are marked: roles containing
groups that are no building blocks or no longer exist, building blocks that
have subgroups, building blocks assigned to users directly and groups with an
unknown kind. The number of these entries is shown at the top and does not
depend on the kind filter. Building blocks that no group and no user uses
are marked as unused.

Kinds added by other extensions are shown with their own label and icon.

Click a title to edit the group or user in the regular record form. The
buttons in the document header create a new role or a new building block.

The module is optional. To hide it, add its identifier to the user TSconfig
of the administrators:

..  code-block:: typoscript
    :caption: User TSconfig

    options.hideModules := addToList(begroups_overview)

..  _usage-audit:

Checking groups and users
=========================

The rules apply to every save operation. Data written around the
DataHandler – SQL, synchronisation tools, the former extension – and
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
        - error
        - The kind of the group is not configured, for example a numeric kind
          of the former extension. Run the upgrade wizard or choose a kind.
    *   - ``classic-group``
        - warning
        - A classic group. Split it into building blocks and a role.
    *   - ``classic-group-disabled``
        - error
        - A classic group, although classic groups are disabled, see
          :ref:`configuration`.
    *   - ``building-block-with-subgroups``
        - error
        - A building block has subgroups.
    *   - ``foreign-permissions``
        - error
        - A group carries permissions that its kind does not show. They are
          in effect until the group is saved again.
    *   - ``role-missing-member``
        - error
        - A role contains a group that does not exist (any more).
    *   - ``role-invalid-member``
        - error
        - A role contains a role, a classic group or a group with an unknown
          kind.
    *   - ``user-building-block``
        - error
        - A building block is assigned to a user directly.
    *   - ``user-permissions``
        - warning
        - A user record carries permissions of its own, which TYPO3 adds to
          those of the roles. New users get all file operations by default
          (core default of :sql:`file_permissions`); clear them in the user
          record.
    *   - ``user-ignores-group-mounts``
        - warning
        - The field :guilabel:`Mount from groups` (:sql:`options`) of a user
          does not include all page tree and file mounts of the groups.

Hidden groups and disabled users are checked as well and marked
"(disabled)". Administrators and deleted records are skipped. Extensions can
add checks of their own, see :ref:`developer-audit`.

Options:

``--format=json``
    Prints the result as JSON (``errors``, ``warnings`` and ``findings``
    with ``severity``, ``identifier``, ``table``, ``uid``, ``title`` and
    ``message``) for monitoring and further processing.

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
        - Invalid option, for example an unknown format.

..  code-block:: bash
    :caption: Example: stop a deployment on errors

    vendor/bin/typo3 begroups:audit --format=json > begroups-audit.json
