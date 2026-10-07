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

use Cretection\BeGroups\DataHandling\UidList;
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
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Migrates groups of be_groups 0.0.x (and AOE be_groups 1.x) to the kind model.
 *
 * - The numeric kinds 0-9 become the string kinds of GroupKind.
 * - META groups become roles. Their composition is restored from the legacy
 *   "subgroup_*" columns, which repairs groups emptied by a bug of the old version.
 * - File permissions stored on building blocks are moved into new "file operations"
 *   building blocks, which are added wherever the original block is used.
 * - Groups carrying other settings their new kind does not display become "classic",
 *   so that no permission is lost. They are listed for manual review.
 *
 * Writes use plain SQL like the core upgrade wizards, because no backend user exists
 * during upgrades; all writes happen in one transaction. The wizard runs only once:
 * afterwards no numeric kind is left.
 *
 * @internal
 */
#[UpgradeWizard('beGroups_kindMigration')]
final class KindMigration implements UpgradeWizardInterface, ChattyInterface
{
    private const GROUPS_TABLE = 'be_groups';
    private const USERS_TABLE = 'be_users';
    private const FILE_PERMISSIONS = 'file_permissions';
    private const LEGACY_KIND_VALUES = ['', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    private const LEGACY_SUBGROUP_COLUMNS = ['subgroup_r', 'subgroup_l', 'subgroup_pa', 'subgroup_fm', 'subgroup_pm', 'subgroup_ts', 'subgroup_ws', 'subgroup_cat'];
    private const DROPPED_COLUMN_PREFIX = 'zzz_deleted_';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly BackendGroupRepository $backendGroupRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly KindFieldResolver $kindFieldResolver,
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
        return 'Converts groups of the former be_groups extension (numeric kinds, META groups, subgroup_* columns) '
            . 'to roles and building blocks without losing any permission.';
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
        return $this->backendGroupRepository->findByKinds(self::LEGACY_KIND_VALUES) !== [];
    }

    public function executeUpdate(): bool
    {
        $this->connectionPool->getConnectionForTable(self::GROUPS_TABLE)->transactional(function (): void {
            $this->migrate();
        });
        return true;
    }

    private function migrate(): void
    {
        $legacyGroups = $this->backendGroupRepository->findByKinds(self::LEGACY_KIND_VALUES);
        $workspacesAvailable = ExtensionManagementUtility::isLoaded('workspaces');
        /** @var array<string, list<int>> $groupsByFilePermissions */
        $groupsByFilePermissions = [];
        /** @var array<int, DatabaseRow> $migratedGroups */
        $migratedGroups = [];

        foreach ($legacyGroups as $uid => $group) {
            $kind = $this->mapLegacyKind($group->get(GroupKind::FIELD_NAME));
            if ($kind === GroupKind::Workspace && !$workspacesAvailable) {
                $kind = GroupKind::Classic;
            }
            $changes = [];

            if ($kind === GroupKind::Role) {
                $changes['subgroup'] = $this->restoreRoleComposition($group)->toString();
                $group = $group->with($changes);
            }

            if ($kind !== GroupKind::Classic) {
                $foreignFields = $this->findForeignFieldsWithValue($kind, $group);
                $otherForeignFields = array_values(array_diff($foreignFields, [self::FILE_PERMISSIONS]));
                if ($otherForeignFields !== []) {
                    $this->output->writeln(sprintf(
                        '<comment>Group [%d] "%s" carries settings its kind "%s" does not display (%s). It becomes "classic" to keep all permissions; please review it.</comment>',
                        $uid,
                        $group->get('title'),
                        $kind->value,
                        implode(', ', $otherForeignFields),
                    ));
                    $kind = GroupKind::Classic;
                } elseif ($foreignFields !== []) {
                    $groupsByFilePermissions[$group->get(self::FILE_PERMISSIONS)][] = $uid;
                    $changes[self::FILE_PERMISSIONS] = '';
                }
            }

            $changes[GroupKind::FIELD_NAME] = $kind->value;
            $this->updateRecord(self::GROUPS_TABLE, $uid, $changes);
            $migratedGroups[$uid] = $group->with($changes);
        }

        $this->extractFilePermissions($groupsByFilePermissions, $migratedGroups);
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
     * The composition of a META group is the union of "subgroup" and all legacy
     * "subgroup_*" columns, which may already have been renamed by the database analyzer.
     */
    private function restoreRoleComposition(DatabaseRow $group): UidList
    {
        $uids = UidList::fromValue($group->get('subgroup'))->uids;
        foreach (self::LEGACY_SUBGROUP_COLUMNS as $column) {
            $value = $group->get($column) !== '' ? $group->get($column) : $group->get(self::DROPPED_COLUMN_PREFIX . $column);
            $uids = array_merge($uids, UidList::fromValue($value)->uids);
        }
        return UidList::fromValue($uids);
    }

    /**
     * Creates one "file operations" building block per distinct set of file permissions
     * and adds it everywhere the original group is used, so effective permissions stay the same.
     *
     * @param array<string, list<int>> $groupsByFilePermissions
     * @param array<int, DatabaseRow> $migratedGroups
     */
    private function extractFilePermissions(array $groupsByFilePermissions, array $migratedGroups): void
    {
        $number = 0;
        foreach ($groupsByFilePermissions as $filePermissions => $groupUids) {
            $number++;
            $titles = array_map(static fn(int $uid): string => $migratedGroups[$uid]->get('title'), $groupUids);
            $fileOperationsUid = $this->createFileOperationsBlock($number, $filePermissions, $titles);
            $this->output->writeln(sprintf('<info>Created file operations block [%d] for: %s</info>', $fileOperationsUid, implode(', ', $titles)));

            foreach ($groupUids as $groupUid) {
                if ($migratedGroups[$groupUid]->get(GroupKind::FIELD_NAME) === GroupKind::Role->value) {
                    // A role cannot carry permissions itself, it gets the new building block instead.
                    $composition = $this->backendGroupRepository->findFieldsByUid($groupUid, ['subgroup'])?->get('subgroup') ?? '';
                    $this->updateRecord(self::GROUPS_TABLE, $groupUid, [
                        'subgroup' => $this->insertAfter(UidList::fromValue($composition), $fileOperationsUid, null)->toString(),
                    ]);
                    continue;
                }
                $this->addNextToReferences(self::GROUPS_TABLE, 'subgroup', $this->backendGroupRepository->findSubgroupLists(), $groupUid, $fileOperationsUid);
                $this->addNextToReferences(self::USERS_TABLE, 'usergroup', $this->backendUserRepository->findUsergroupLists(), $groupUid, $fileOperationsUid);
            }
        }
    }

    /**
     * @param list<string> $sourceTitles
     */
    private function createFileOperationsBlock(int $number, string $filePermissions, array $sourceTitles): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::GROUPS_TABLE);
        $now = time();
        $connection->insert(self::GROUPS_TABLE, [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'title' => sprintf('File operations (migrated %d)', $number),
            'description' => 'Created by the be_groups upgrade wizard from: ' . implode(', ', $sourceTitles),
            GroupKind::FIELD_NAME => GroupKind::FileOperations->value,
            self::FILE_PERMISSIONS => $filePermissions,
        ]);
        return (int)$connection->lastInsertId();
    }

    /**
     * Adds $newUid directly after $referencedUid in every list that references it.
     *
     * @param array<int, string> $listsByUid
     */
    private function addNextToReferences(string $table, string $field, array $listsByUid, int $referencedUid, int $newUid): void
    {
        foreach ($listsByUid as $uid => $list) {
            $references = UidList::fromValue($list);
            if (in_array($referencedUid, $references->uids, true)) {
                $this->updateRecord($table, $uid, [$field => $this->insertAfter($references, $newUid, $referencedUid)->toString()]);
            }
        }
    }

    private function insertAfter(UidList $list, int $newUid, ?int $afterUid): UidList
    {
        if (in_array($newUid, $list->uids, true)) {
            return $list;
        }
        $uids = $list->uids;
        $position = $afterUid === null ? false : array_search($afterUid, $uids, true);
        if ($position === false) {
            $uids[] = $newUid;
        } else {
            array_splice($uids, $position + 1, 0, [$newUid]);
        }
        return UidList::fromValue($uids);
    }

    /**
     * @return list<string>
     */
    private function findForeignFieldsWithValue(GroupKind $kind, DatabaseRow $group): array
    {
        $fieldsWithValue = [];
        foreach ($this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind->value) as $fieldName => $emptyValue) {
            $value = $group->get($fieldName);
            if ($value !== '' && $value !== (string)$emptyValue) {
                $fieldsWithValue[] = $fieldName;
            }
        }
        return $fieldsWithValue;
    }

    /**
     * @param array<string, string> $changes
     */
    private function updateRecord(string $table, int $uid, array $changes): void
    {
        $this->connectionPool->getConnectionForTable($table)->update($table, $changes, ['uid' => $uid]);
    }
}
