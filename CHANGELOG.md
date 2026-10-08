# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) for its public API
(see `CODING_GUIDELINES.md` §3.6).

## [Unreleased]

Relaunch for TYPO3 14.3 LTS (version 1.0.0).

### Added

- Kind model aligned with the official TYPO3 guideline "Setting up backend
  user groups": `role`, `acl`, `page_group`, `db_mount`, `file_mount`,
  `file_operations`, `category_mount`, `language`, `tsconfig`, `workspace`
  and `classic`. Only configured kinds (items of `tx_begroups_kind` with a
  form of their own) are valid; numeric legacy kinds and kinds of uninstalled
  extensions are neither roles nor building blocks.
- Role composition in the core field `subgroup`. The role form uses the
  side-by-side list of the core, grouped by kind; the user form lists all
  groups grouped by kind. Neither list is filtered, so saving a form never
  drops stored assignments, and the order of members is kept.
- Rules R1–R4, enforced on every save operation (backend form, DataHandler
  API, import and sync tools), including soft-deleted records:
  - R1 clears permission fields that do not belong to the kind, including
    default values of new records. Only permission fields are managed; other
    data on `be_groups` is never touched.
  - R2 and R4 only reject newly added relations; stored relations are kept.
    `NEW…` placeholders, `uid|label`, URL-encoded and table-prefixed values
    are understood.
  - New records without kind get the kind the DataHandler stores (TCA
    default, `TCAdefaults` in user and page TSconfig).
- Every intervention of the rules is written to the system log and shown to
  the editor as flash message. Corrections are reported after the save
  operation has completed, so an aborted save (e.g. a cancelled sudo mode
  confirmation) leaves no log entry.
- Read-only backend module "Roles & Building Blocks" (Administration): roles
  with their building blocks and users, building blocks with every group and
  user using them, classic groups and groups with an unknown kind; sorting by
  title or usage, filtering by kind; markers for roles with invalid members,
  building blocks with subgroups or direct users, and unused building blocks.
  The issue count does not depend on the filter; kinds of other extensions
  use their own label and icon.
- Consistency check `vendor/bin/typo3 begroups:audit` (read-only): unknown
  kinds, classic groups, building blocks with subgroups, foreign permissions
  on groups, invalid or missing members of roles, building blocks assigned to
  users directly, permissions on user records and users that ignore the
  mounts of their groups. Exit code 1 on errors (`--fail-on-warnings`: on
  warnings too), JSON output with `--format=json`.
- PSR-14 event `AfterAuditFindingsCollectedEvent`: other extensions add
  findings of their own checks.
- PSR-14 event `ModifyKindOfNewGroupEvent`: import and synchronisation
  tools that create groups without a kind can choose it from the values of
  the record, e.g. from a prefix of the title.
- Assistant `begroups:classify` (`--dry-run`): proposes a kind for every
  classic group and changes the kind of groups that serve one purpose,
  combine other groups (role) or only own pages (page group).
- Assistant `begroups:split` (uids or `--all`, `--dry-run`): moves the
  permissions of a classic group into new building blocks, one per kind, and
  makes the group a role with the same uid, so assignments stay unchanged. A
  new page group takes over as owner group of new pages where the group itself
  had this task.
- Both assistants write through the DataHandler in one transaction per group.
  They verify that all other groups and all users are unchanged and that TYPO3
  grants every combination of groups of the users, and the group itself, the
  same as before, and roll back otherwise.
- `begroups:audit` reports lists of groups with duplicates or entries TYPO3
  ignores (`unclean-group-list`).
- Extension setting `allowClassicGroups` (default: enabled). It takes effect
  immediately: when disabled, the form no longer offers "classic" (except for
  groups that already are classic) and new groups start as "role".
- Upgrade wizard `beGroups_kindMigration` for data of be_groups 0.0.x and
  the AOE version 1.x. It never changes effective permissions:
  - It refuses to run until the database structure has been updated.
  - Members of META groups that were only listed in the former `subgroup_*`
    fields are reported, not added.
  - File permissions of building blocks move into "file operations" building
    blocks per permission set and hidden state; groups with other foreign
    settings, and groups whose referencing lists have no room left, become
    "classic".
  - Soft-deleted groups and users are migrated as well; the wizard is
    repeatable.
  - It prints a hint if the former setting `onlyShowMetaGroup` is still
    enabled; its successor is `allowClassicGroups`.
- Icons in the style of TYPO3 14 for the light and the dark backend theme:
  the kinds use monochrome icons of the core, the module has its own
  monochrome icon with the accent color of the theme.
- Documentation in English and German, including a credits and history
  page for the original author Michael Klapper.

### Changed

- Supports TYPO3 14.3 LTS and PHP 8.2–8.5 only (TYPO3 15 will be supported
  by version 1.x as soon as it is released).
- The kind is stored as a string identifier instead of a number.
- The column `be_groups.subgroup` is enlarged to 2048 characters, so roles
  can combine many building blocks.
- The license changed from GPL-3.0-or-later to GPL-2.0-or-later, in line with
  the TYPO3 core.
- All metadata lives in `composer.json`.

### Removed

- The columns `subgroup_r`, `subgroup_l`, `subgroup_pa`, `subgroup_fm`,
  `subgroup_pm`, `subgroup_ts`, `subgroup_ws` and `subgroup_cat` (read by the
  upgrade wizard, removed by the database analyzer afterwards).
- The synchronisation hook between `subgroup_*` and `subgroup`, which could
  empty `subgroup` on programmatic saves.
- `ext_emconf.php`, `ext_icon.png` and the legacy update wizard.
- The extension setting `explicitAllow` (without effect since TYPO3 12).
- The extension setting `onlyShowMetaGroup` (successor: `allowClassicGroups`).

## [0.0.9] - 2022-06-03

Last release of the revival for TYPO3 11.

### History of the versions 0.0.1–0.0.9 (2022, Jonathan Starck)

- Revived the extension for TYPO3 11.
- Changed the namespace from `AOE` to `Cretection`.
- Replaced deprecated functions, for example
  `ExtensionManagementUtility::siteRelPath()`.
- Added SVG icons, the option `onlyShowMetaGroup` and translations via
  Crowdin.

## Earlier history (AOE / morphodo)

### 2017 (Jonathan Klauck, Martin Tepper)

- Made the extension compatible with TYPO3 8 LTS.
- Restored the "Extended" tab.

### 2016 (Tomas Norre Mikkelsen, Dragan Tomic, Stefan Rotsch)

- Replaced deprecated function calls like `t3lib_div`.
- Switched to namespaces and added `composer.json`.
- Made the extension compatible with TYPO3 7 LTS.

### 2014 (Christian Zenker)

- Made the field `file_permissions` editable and regrouped the fields of the
  rights form.

### 2012 (Michael Klapper, original author)

- 2012-09-06: Flash message when the "hide in list" option is toggled;
  manual update of "hide in list"; improved ordering of all be_groups
  listings; original multiselect box for groups of kind "default all"; the
  update wizard handles "default all" records.
- 2012-09-04: Update the `subgroup_*` fields when switching from "default"
  to "META".
- 2012-08-23: Two wizards to migrate to the new be_groups interface.
- 2012-07-05: Split the field `subgroup` into seven new fields to improve the
  user experience.
- 2012-07-04: Initial release – "create new" icon on the field `subgroup`,
  only META groups selectable in be_users, META groups hidden in be_groups.

[Unreleased]: https://github.com/Cretection/be_groups/compare/0.0.9...HEAD
[0.0.9]: https://github.com/Cretection/be_groups/releases/tag/0.0.9
