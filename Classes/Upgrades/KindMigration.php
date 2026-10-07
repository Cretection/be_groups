<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Cretection\BeGroups\Upgrades;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\RepeatableInterface;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Migrates groups of be_groups 0.0.x (and AOE be_groups 1.x) to the kind model
 * without changing any effective permission.
 *
 * - The numeric kinds 0-9 become the kinds of GroupKind (META groups become roles).
 * - File permissions stored on building blocks move into "file operations" building blocks
 *   that are added wherever the original group is used. Hidden groups get hidden blocks,
 *   so nothing that was inactive becomes active.
 * - Groups carrying other settings their new kind does not display become "classic".
 * - The composition of roles is not changed. Members that the former form showed in its
 *   "subgroup_*" fields but that are not effective are reported for manual review.
 *
 * All records are migrated, including soft-deleted ones. Writes use plain SQL in one
 * transaction like the core upgrade wizards, because no backend user exists during upgrades.
 * The wizard is repeatable: it only touches groups that still have a numeric kind.
 *
 * @internal
 */
#[UpgradeWizard('beGroups_kindMigration')]
final class KindMigration implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const GROUPS_TABLE = 'be_groups';
    private const USERS_TABLE = 'be_users';
    private const FILE_PERMISSIONS = 'file_permissions';
    private const LEGACY_KIND_VALUES = ['', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    private const LEGACY_SUBGROUP_COLUMNS = ['subgroup_r', 'subgroup_l', 'subgroup_pa', 'subgroup_fm', 'subgroup_pm', 'subgroup_ts', 'subgroup_ws', 'subgroup_cat'];
    private const DROPPED_COLUMN_PREFIX = 'zzz_deleted_';

    private OutputInterface $output;

    /**
     * @var array<int, string> subgroup lists of all groups, kept in sync with the database
     */
    private array $subgroupLists = [];

    /**
     * @var array<int, string> usergroup lists of all users, kept in sync with the database
     */
    private array $usergroupLists = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly BackendGroupRepository $backendGroupRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly KindFieldResolver $kindFieldResolver,
        private readonly ColumnInspector $columnInspector,
        private readonly ExtensionSettings $extensionSettings,
    ) {
        $this->output = new NullOutput();
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate be_groups kinds';
    }

    public function getDescription(): string
    {
        return 'Converts groups of the former be_groups extension (numeric kinds, META groups) to roles and building '
            . 'blocks without changing any effective permission. Requires the updated database structure.';
    }

    /**
     * @return list<string>
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $kindColumn = $this->columnInspector->inspect(self::GROUPS_TABLE)[strtolower(GroupKind::FIELD_NAME)] ?? null;
        if ($kindColumn === null) {
            return false;
        }
        if (!$kindColumn->isString) {
            // The column of the former extension still has to be converted by the database analyzer.
            return true;
        }
        return $this->backendGroupRepository->findByKindsIncludingDeleted(self::LEGACY_KIND_VALUES) !== [];
    }

    public function executeUpdate(): bool
    {
        $groupColumns = $this->columnInspector->inspect(self::GROUPS_TABLE);
        $kindColumn = $groupColumns[strtolower(GroupKind::FIELD_NAME)] ?? null;
        if ($kindColumn === null || !$kindColumn->isString) {
            $this->output->writeln(
                '<error>The column be_groups.tx_begroups_kind still has the integer type of the former extension. '
                . 'Apply all changes of "Admin Tools > Maintenance > Analyze Database Structure" first, then run this wizard again.</error>',
            );
            return false;
        }
        $subgroupColumn = $groupColumns['subgroup'] ?? null;
        $usergroupColumn = $this->columnInspector->inspect(self::USERS_TABLE)['usergroup'] ?? null;
        if ($subgroupColumn === null || $usergroupColumn === null) {
            return false;
        }

        $this->connectionPool->getConnectionForTable(self::GROUPS_TABLE)->transactional(function () use ($subgroupColumn, $usergroupColumn): void {
            $this->migrate($subgroupColumn, $usergroupColumn);
        });

        $this->output->writeln('<info>Please update the reference index ("vendor/bin/typo3 referenceindex:update").</info>');
        if ($this->extensionSettings->isLegacyOnlyShowMetaGroupEnabled()) {
            $this->output->writeln(
                '<comment>The former option "onlyShowMetaGroup" is enabled. Its successor is "allowClassicGroups": '
                . 'disable it in the extension configuration to keep allowing only roles for users.</comment>',
            );
        }
        return true;
    }

    private function migrate(ColumnInfo $subgroupColumn, ColumnInfo $usergroupColumn): void
    {
        $legacyGroups = $this->backendGroupRepository->findByKindsIncludingDeleted(self::LEGACY_KIND_VALUES);
        $this->subgroupLists = $this->backendGroupRepository->findSubgroupListsIncludingDeleted();
        $this->usergroupLists = $this->backendUserRepository->findUsergroupListsIncludingDeleted();
        $workspacesAvailable = ExtensionManagementUtility::isLoaded('workspaces');

        /** @var array<string, list<int>> $groupsToSplit groups whose file permissions move into a new building block, by hidden state and permissions */
        $groupsToSplit = [];
        $migratedGroups = [];
        foreach ($legacyGroups as $uid => $group) {
            $kind = $this->mapLegacyKind($group->get(GroupKind::FIELD_NAME));
            if ($kind === GroupKind::Workspace && !$workspacesAvailable) {
                $kind = GroupKind::Classic;
            }
            if ($kind === GroupKind::Role) {
                $this->reportIneffectiveLegacyMembers($uid, $group);
            }

            if ($kind !== GroupKind::Classic) {
                $foreignFields = $this->findForeignFieldsWithValue($kind, $group);
                $otherForeignFields = array_values(array_diff($foreignFields, [self::FILE_PERMISSIONS]));
                if ($otherForeignFields !== []) {
                    $this->output->writeln(sprintf(
                        '<comment>Group [%d] "%s" carries settings its kind "%s" does not show (%s). It becomes "classic" to keep its permissions; please review it.</comment>',
                        $uid,
                        $group->get('title'),
                        $kind->value,
                        implode(', ', $otherForeignFields),
                    ));
                    $kind = GroupKind::Classic;
                } elseif ($foreignFields !== []) {
                    $groupsToSplit[$group->get('hidden') . '|' . $group->get(self::FILE_PERMISSIONS)][] = $uid;
                }
            }

            $this->updateRecord(self::GROUPS_TABLE, $uid, [GroupKind::FIELD_NAME => $kind->value]);
            $migratedGroups[$uid] = $group->with([GroupKind::FIELD_NAME => $kind->value]);
        }

        $number = 0;
        foreach ($groupsToSplit as $groupUids) {
            $number++;
            $this->extractFilePermissions($number, $groupUids, $migratedGroups, $subgroupColumn, $usergroupColumn);
        }
        $this->output->writeln(sprintf('<info>Migrated %d groups to the kind model.</info>', count($migratedGroups)));
    }

    private function mapLegacyKind(string $legacyKind): GroupKind
    {
        return match ($legacyKind) {
            '1' => GroupKind::AccessControl,
            '2' => GroupKind::Language,
            '3' => GroupKind::Role,
            '4' => GroupKind::PageGroup,
            '5' => GroupKind::FileMount,
            '6' => GroupKind::DatabaseMount,
            '7' => GroupKind::TsConfig,
            '8' => GroupKind::Workspace,
            '9' => GroupKind::CategoryMount,
            default => GroupKind::Classic,
        };
    }

    /**
     * The former extension showed the members of META groups in "subgroup_*" fields, while only
     * "subgroup" was effective. Members that differ are reported, but never added: adding them
     * would grant permissions the users of the role do not have today.
     */
    private function reportIneffectiveLegacyMembers(int $uid, DatabaseRow $group): void
    {
        $effectiveMembers = RelationList::fromValue($group->get('subgroup'));
        $legacyMembers = [];
        foreach (self::LEGACY_SUBGROUP_COLUMNS as $column) {
            $value = $group->get($column) !== '' ? $group->get($column) : $group->get(self::DROPPED_COLUMN_PREFIX . $column);
            $legacyMembers = array_merge($legacyMembers, RelationList::fromValue($value)->getUids());
        }
        $ineffectiveMembers = array_values(array_unique(array_diff($legacyMembers, $effectiveMembers->getUids())));
        if ($ineffectiveMembers !== []) {
            $this->output->writeln(sprintf(
                '<comment>Role [%d] "%s": the former form listed the groups %s, but they are not effective and have not been added. Add them in the role form if intended.</comment>',
                $uid,
                $group->get('title'),
                implode(', ', $ineffectiveMembers),
            ));
        }
    }

    /**
     * Creates a "file operations" building block for groups sharing the same file permissions and
     * hidden state, and adds it next to every reference of these groups, so that effective
     * permissions stay exactly the same. A group whose references cannot take another entry
     * (column length) keeps its file permissions and becomes "classic" instead.
     *
     * @param list<int> $groupUids
     * @param array<int, DatabaseRow> $migratedGroups
     */
    private function extractFilePermissions(int $number, array $groupUids, array $migratedGroups, ColumnInfo $subgroupColumn, ColumnInfo $usergroupColumn): void
    {
        $splittableUids = [];
        foreach ($groupUids as $groupUid) {
            if ($this->canInsertNextToReferences($groupUid, $migratedGroups[$groupUid], $subgroupColumn, $usergroupColumn)) {
                $splittableUids[] = $groupUid;
                continue;
            }
            $this->updateRecord(self::GROUPS_TABLE, $groupUid, [GroupKind::FIELD_NAME => GroupKind::Classic->value]);
            $this->output->writeln(sprintf(
                '<comment>Group [%d] "%s" keeps its file permissions and becomes "classic": a list referencing it has no room for another building block.</comment>',
                $groupUid,
                $migratedGroups[$groupUid]->get('title'),
            ));
        }
        if ($splittableUids === []) {
            return;
        }

        $firstGroup = $migratedGroups[$splittableUids[0]];
        $titles = array_map(static fn(int $uid): string => $migratedGroups[$uid]->get('title'), $splittableUids);
        $fileOperationsUid = $this->createFileOperationsBlock($number, $firstGroup->get(self::FILE_PERMISSIONS), $firstGroup->get('hidden') === '1', $titles);
        $this->output->writeln(sprintf('<info>Created file operations block [%d] for: %s</info>', $fileOperationsUid, implode(', ', $titles)));

        foreach ($splittableUids as $groupUid) {
            if ($migratedGroups[$groupUid]->get(GroupKind::FIELD_NAME) === GroupKind::Role->value) {
                // A role cannot carry permissions itself, it gets the new building block instead.
                $this->writeSubgroupList($groupUid, RelationList::fromValue($this->subgroupLists[$groupUid] ?? '')->withEntryAfter($fileOperationsUid, null));
            } else {
                foreach ($this->subgroupLists as $referencingUid => $list) {
                    $references = RelationList::fromValue($list);
                    if ($references->contains($groupUid)) {
                        $this->writeSubgroupList($referencingUid, $references->withEntryAfter($fileOperationsUid, $groupUid));
                    }
                }
                foreach ($this->usergroupLists as $userUid => $list) {
                    $references = RelationList::fromValue($list);
                    if ($references->contains($groupUid)) {
                        $this->usergroupLists[$userUid] = $references->withEntryAfter($fileOperationsUid, $groupUid)->toString();
                        $this->updateRecord(self::USERS_TABLE, $userUid, ['usergroup' => $this->usergroupLists[$userUid]]);
                    }
                }
            }
            $this->updateRecord(self::GROUPS_TABLE, $groupUid, [self::FILE_PERMISSIONS => '']);
        }
    }

    /**
     * Whether every list referencing the group can take one more uid. The largest possible uid
     * is used for the check, so the result also holds for the uid the new block will get.
     */
    private function canInsertNextToReferences(int $groupUid, DatabaseRow $group, ColumnInfo $subgroupColumn, ColumnInfo $usergroupColumn): bool
    {
        $largestUid = PHP_INT_MAX;
        if ($group->get(GroupKind::FIELD_NAME) === GroupKind::Role->value) {
            return $subgroupColumn->fits(RelationList::fromValue($this->subgroupLists[$groupUid] ?? '')->withEntryAfter($largestUid, null)->toString());
        }
        foreach ([[$this->subgroupLists, $subgroupColumn], [$this->usergroupLists, $usergroupColumn]] as [$lists, $column]) {
            foreach ($lists as $list) {
                $references = RelationList::fromValue($list);
                if ($references->contains($groupUid) && !$column->fits($references->withEntryAfter($largestUid, $groupUid)->toString())) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param list<string> $sourceTitles
     */
    private function createFileOperationsBlock(int $number, string $filePermissions, bool $hidden, array $sourceTitles): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::GROUPS_TABLE);
        $now = time();
        $connection->insert(self::GROUPS_TABLE, [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'hidden' => $hidden ? 1 : 0,
            'title' => sprintf('File operations (migrated %d)', $number),
            'description' => 'Created by the be_groups upgrade wizard from: ' . implode(', ', $sourceTitles),
            GroupKind::FIELD_NAME => GroupKind::FileOperations->value,
            self::FILE_PERMISSIONS => $filePermissions,
        ]);
        return (int)$connection->lastInsertId();
    }

    private function writeSubgroupList(int $uid, RelationList $list): void
    {
        $this->subgroupLists[$uid] = $list->toString();
        $this->updateRecord(self::GROUPS_TABLE, $uid, ['subgroup' => $this->subgroupLists[$uid]]);
    }

    /**
     * @return list<string>
     */
    private function findForeignFieldsWithValue(GroupKind $kind, DatabaseRow $group): array
    {
        $fieldsWithValue = [];
        foreach (array_keys($this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind->value)) as $fieldName) {
            if (!$this->kindFieldResolver->isEmptyValue($fieldName, $group->get($fieldName))) {
                $fieldsWithValue[] = $fieldName;
            }
        }
        return $fieldsWithValue;
    }

    /**
     * @param array<string, string|int> $changes
     */
    private function updateRecord(string $table, int $uid, array $changes): void
    {
        $this->connectionPool->getConnectionForTable($table)->update($table, $changes, ['uid' => $uid]);
    }
}
