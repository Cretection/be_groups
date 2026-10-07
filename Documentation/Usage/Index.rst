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
*   A role has no settings of its own. It lists its building blocks, grouped
    by kind, and is assigned to backend users.

When a group is saved, all settings that do not belong to its kind are
removed, including default values. The system log (*Administration > Log*)
records every correction made by the extension.

..  _usage-module:

The module "Roles & Building Blocks"
====================================

Administrators find the module *Administration > Roles & Building Blocks*
next to the *Users* module. It is read-only and shows:

*   every role with its building blocks, grouped by kind, and its users,
*   every building block with the roles and classic groups using it and the
    users it is assigned to directly,
*   the remaining classic groups.

Roles can be sorted by title or by number of users, building blocks by title
or by usage. Building blocks can be filtered by kind; the selection is kept
per user.

Entries that do not follow the role model are marked: roles containing
groups that are no building blocks or no longer exist, and building blocks
assigned to users directly. Unused building blocks are marked as well.

Click a title to edit the group or user in the regular record form. The
buttons in the document header create a new role or a new building block.

The module is optional. To hide it, add its identifier to the user TSconfig
of the administrators:

..  code-block:: typoscript
    :caption: User TSconfig

    options.hideModules := addToList(begroups_overview)
