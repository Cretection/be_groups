..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

The complete changelog is maintained in
`CHANGELOG.md <https://github.com/Cretection/be_groups/blob/main/CHANGELOG.md>`__
in the repository.

..  _changelog-1-0-0:

1.0.0 (unreleased)
==================

Relaunch for TYPO3 14.3 LTS.

*   New kind model aligned with the official TYPO3 guideline: roles,
    access rights, page permission groups, page tree entry points, file
    mounts, file operations, category mounts, languages, TSconfig,
    workspace and classic.
*   Roles are composed in the core field :sql:`subgroup`. The additional
    columns :sql:`subgroup_*` of earlier versions are gone; the upgrade
    wizard ``beGroups_kindMigration`` migrates them.
*   The rules R1 – R4 are enforced on every save operation, see
    :ref:`concept-rules`.
*   The consistency check ``begroups:audit`` finds everything that does
    not follow the role model, with exit codes for deployments and
    monitoring and the event ``AfterAuditFindingsCollectedEvent`` for checks
    of other extensions, see :ref:`usage-audit`.
*   TYPO3 14.3 LTS and PHP 8.2 – 8.5 only.
*   The license changed to GPL-2.0-or-later, in line with the TYPO3 core.
*   :file:`ext_emconf.php` was removed; all metadata lives in
    :file:`composer.json`.

..  _changelog-history:

Earlier versions
================

0.0.1 – 0.0.9 (2022)
    Revival for TYPO3 11 by Jonathan Starck.

1.x (2012 – 2017)
    Original extension by Michael Klapper, maintained by AOE for TYPO3 4.x
    to 8 LTS. See :ref:`credits`.
