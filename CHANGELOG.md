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
  and `classic`.
- Role composition in the core field `subgroup`, grouped by kind.
- Rules R1–R4, enforced on every save operation (backend form, DataHandler
  API, import and sync tools).
- Every correction made by the rules is written to the system log and shown
  to the editor as flash message.
- Read-only backend module "Roles & Building Blocks" (Administration): roles
  with their building blocks and users, building blocks with their usage,
  sorting, filtering by kind and markers for inconsistencies.
- Extension setting `allowClassicGroups` (default: enabled).
- Upgrade wizard `beGroups_kindMigration` for data of be_groups 0.0.x and
  the AOE version 1.x. It restores META groups emptied by the former
  synchronisation bug and moves file permissions of building blocks into new
  "file operations" building blocks, so no permission is lost.
- Documentation in English and German, including a credits and history
  page for the original author Michael Klapper.

### Changed

- Supports TYPO3 14.3 LTS and PHP 8.2–8.5 only.
- The kind is stored as a string identifier instead of a number.
- The license changed from GPL-3.0-or-later to GPL-2.0-or-later, in line with
  the TYPO3 core.
- All metadata lives in `composer.json`.

### Removed

- The columns `subgroup_r`, `subgroup_l`, `subgroup_pa`, `subgroup_fm`,
  `subgroup_pm`, `subgroup_ts`, `subgroup_ws` and `subgroup_cat` (migrated by
  the upgrade wizard).
- The synchronisation hook between `subgroup_*` and `subgroup`, which could
  empty `subgroup` on programmatic saves.
- `ext_emconf.php`, `ext_icon.png` and the legacy update wizard.
- The extension setting `explicitAllow` (without effect since TYPO3 12).

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
