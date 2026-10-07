..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _introduction-what:

What does it do?
================

Large TYPO3 installations quickly end up with dozens or hundreds of backend
user groups. Every group can configure everything: modules, tables, page
tree entry points, file mounts, languages, TSconfig. After a while nobody
knows which group grants what, and why an editor can do something.

Backend Group Kinds brings order into this:

*   Every group gets a **kind**. A kind defines which fields the group shows
    and which permissions it may grant. A "File mount" group only grants file
    mounts, a "Languages" group only grants languages.
*   **Roles** combine building blocks. An "Editor Marketing" role is composed
    of the access rights, page tree entry points, file mounts and languages
    an editor of the marketing team needs.
*   **Backend users only get roles.** What a user may do follows from the
    roles assigned to them – nothing else.
*   **What is not visible does not apply.** A building block can only grant
    what its kind shows. Hidden leftovers of former configurations are
    cleared.

The extension only adds one field to the core table :sql:`be_groups` – the
kind. All permissions stay in the core fields, and TYPO3 itself resolves
them as usual.

..  _introduction-why:

Why?
====

The official TYPO3 documentation recommends exactly this structure:
system groups (page tree entry points, file mounts, category mounts,
page permissions), access control list groups and role groups that only
aggregate other groups, distinguished by prefixes such as ``R_``,
``DBM_``, ``FM_`` or ``ACL_``.

See `Setting up backend user groups
<https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Administration/PermissionsManagement/SettingUpBackendGroups/Index.html>`__
in TYPO3 Explained. The same page also states the gap this extension fills:

    "TYPO3 currently lacks the feature to categorize backend user groups by
    context or purpose"

Backend Group Kinds implements the recommendation as real group types: the
user interface guides you, and the rules are enforced instead of relying on
naming conventions.

..  _introduction-requirements:

Requirements
============

*   TYPO3 14.3 LTS
*   PHP 8.2 – 8.5

..  _introduction-not:

What it does not do
===================

*   It does not evaluate permissions itself. TYPO3 resolves the groups and
    their subgroups as usual.
*   It is not a "permissions as code" tool. Extensions such as
    `b13/permission-sets <https://github.com/b13/permission-sets>`__ cover
    deployable permissions and can be combined with this extension.
*   It does not manage page permissions (owner, group, everybody). Use the
    core module :guilabel:`Permissions` for this.
