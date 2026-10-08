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

namespace Cretection\BeGroups\Domain\Classification;

use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Domain\Repository\SystemLogRepository;
use Doctrine\DBAL\Exception as DatabaseException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Converts classic groups as proposed by the GroupClassifier.
 *
 * All changes are written through the DataHandler (rules, system log, history, reference
 * index) within one transaction per group. Before it is committed, the conversion is verified:
 *
 * - All other groups and all users are unchanged, and the group itself only changed its kind,
 *   its subgroups and the permission fields that moved into the building blocks. This also
 *   catches changes of other extensions while saving.
 * - For every combination of groups a user has, and for the group itself, TYPO3 grants the
 *   same as before (see GroupPermissionResolver).
 *
 * If anything differs, the transaction is rolled back and the group stays unchanged.
 *
 * Requires an authenticated administrator ($GLOBALS['BE_USER']).
 *
 * @internal
 */
final readonly class GroupConverter
{
    private const TABLE = 'be_groups';

    public function __construct(
        private GroupClassifier $groupClassifier,
        private GroupPermissionResolver $groupPermissionResolver,
        private BackendGroupRepository $backendGroupRepository,
        private BackendUserRepository $backendUserRepository,
        private SystemLogRepository $systemLogRepository,
        private KindFieldResolver $kindFieldResolver,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Changes the kind of a classic group whose permissions belong to one kind.
     */
    public function changeKind(int $uid): ConversionResult
    {
        return $this->transactional($uid, function () use ($uid): ConversionResult {
            $before = $this->takeSnapshot();
            $classification = $this->groupClassifier->classifyInSnapshot($uid, $before);
            $groupBefore = $before->groups[$uid] ?? null;
            if ($classification === null || $groupBefore === null || $groupBefore->get('deleted') === '1'
                || $classification->action !== ClassificationAction::ChangeKind || $classification->targetKind === null
            ) {
                return new ConversionResult($uid, ConversionStatus::Skipped, 'Not a classic group whose kind can be changed, see begroups:classify.');
            }

            $this->process([self::TABLE => [$uid => [GroupKind::FIELD_NAME => $classification->targetKind]]]);

            $after = $this->backendGroupRepository->findByUid($uid);
            if ($after === null || $after->get(GroupKind::FIELD_NAME) !== $classification->targetKind) {
                return new ConversionResult($uid, ConversionStatus::Failed, 'The kind could not be changed, e.g. because a rule or another extension rejected it. Nothing was changed.');
            }
            // Also covers permission fields of other extensions, which TYPO3 does not merge itself.
            foreach ($this->kindFieldResolver->getManagedFieldNames() as $fieldName) {
                if (!$this->kindFieldResolver->isSameValue($fieldName, $groupBefore->get($fieldName), $after->get($fieldName))) {
                    return new ConversionResult($uid, ConversionStatus::Failed, sprintf('The field "%s" would change; nothing was changed.', $fieldName));
                }
            }
            $change = $this->findChange($uid, $before, [], null);
            if ($change !== null) {
                return new ConversionResult($uid, ConversionStatus::Failed, $change);
            }
            return new ConversionResult($uid, ConversionStatus::Converted, sprintf('The kind is now "%s".', $classification->targetKind));
        });
    }

    /**
     * Moves the permissions of a classic group into new building blocks and makes the group a role
     * that contains them. The group keeps its uid, so users and other groups keep their assignments.
     */
    public function split(int $uid): ConversionResult
    {
        return $this->transactional($uid, function () use ($uid): ConversionResult {
            $before = $this->takeSnapshot();
            $classification = $this->groupClassifier->classifyInSnapshot($uid, $before);
            $group = $before->groups[$uid] ?? null;
            if ($classification === null || $group === null || $group->get('deleted') === '1' || $classification->action !== ClassificationAction::Split) {
                return new ConversionResult($uid, ConversionStatus::Skipped, 'Not a classic group that can be split, see begroups:classify.');
            }

            $blockUids = $this->createBlocks($group, $classification);
            if ($blockUids === null) {
                return new ConversionResult(
                    $uid,
                    ConversionStatus::Failed,
                    'A building block could not be created with exactly the permissions of the group, e.g. because of values '
                    . 'the DataHandler no longer accepts. Nothing was changed.',
                );
            }

            $subgroups = RelationList::fromValue($group->get('subgroup'));
            foreach ($blockUids as $blockUid) {
                // Appended after the existing subgroups: TYPO3 applies subgroups before the group
                // itself, so the TSconfig of the group keeps its precedence.
                $subgroups = $subgroups->withEntryAfter($blockUid, null);
            }
            $this->process([self::TABLE => [$uid => [
                GroupKind::FIELD_NAME => GroupKind::Role->value,
                'subgroup' => $subgroups->toString(),
            ] + $this->kindFieldResolver->getForeignFieldsWithEmptyValue(GroupKind::Role->value)]]);

            $role = $this->backendGroupRepository->findByUid($uid);
            if ($role === null || $role->get(GroupKind::FIELD_NAME) !== GroupKind::Role->value) {
                return new ConversionResult($uid, ConversionStatus::Failed, 'The group could not be changed into a role; nothing was changed.');
            }
            foreach ($this->kindFieldResolver->getForeignFieldsWithEmptyValue(GroupKind::Role->value) as $fieldName => $emptyValue) {
                if (!$this->kindFieldResolver->isEmptyValue($fieldName, $role->get($fieldName))) {
                    return new ConversionResult($uid, ConversionStatus::Failed, sprintf('The field "%s" of the role could not be emptied; nothing was changed.', $fieldName));
                }
            }
            // See GroupClassifier: a page group without permissions as first member takes over as owner of new pages.
            $firstConcern = $classification->concerns[0] ?? null;
            $ownerPageGroupUid = $firstConcern !== null && $firstConcern->kind === GroupKind::PageGroup->value && $firstConcern->values === []
                ? $blockUids[0]
                : null;
            $change = $this->findChange($uid, $before, $blockUids, $ownerPageGroupUid);
            if ($change !== null) {
                return new ConversionResult($uid, ConversionStatus::Failed, $change);
            }
            return new ConversionResult(
                $uid,
                ConversionStatus::Converted,
                sprintf('The group is now a role with %d new building block(s).', count($blockUids)),
                $blockUids,
            );
        });
    }

    /**
     * @return list<int>|null the new building blocks in the order of the concerns, or null if one
     *                        does not grant exactly the permissions of its concern
     */
    private function createBlocks(DatabaseRow $group, Classification $classification): ?array
    {
        $concerns = $classification->concerns;
        $datamap = [];
        foreach ($concerns as $index => $concern) {
            $datamap[self::TABLE]['NEW_begroups_split_' . $index] = [
                'pid' => (int)$group->get('pid'),
                // The kind is shown as prefix wherever TYPO3 shows the title (see GroupRecordTitle).
                'title' => $group->get('title'),
                'description' => sprintf('Created by begroups:split from group %d "%s".', $group->getUid(), $group->get('title')),
                // Visible: the blocks are only reachable through the group, which keeps its hidden state,
                // so showing the group again restores its permissions as before.
                'hidden' => 0,
                GroupKind::FIELD_NAME => $concern->kind,
            ] + $concern->values;
        }
        if ($datamap === []) {
            return [];
        }

        $dataHandler = $this->process($datamap);
        $blockUids = [];
        foreach ($concerns as $position => $plannedConcern) {
            $blockUid = $dataHandler->substNEWwithIDs['NEW_begroups_split_' . $position] ?? null;
            $block = is_int($blockUid) ? $this->backendGroupRepository->findByUid($blockUid) : null;
            if ($block === null || !$this->grantsExactly($block, $plannedConcern)) {
                return null;
            }
            $blockUids[] = $block->getUid();
        }
        return $blockUids;
    }

    private function grantsExactly(DatabaseRow $block, Concern $concern): bool
    {
        if ($block->get(GroupKind::FIELD_NAME) !== $concern->kind || $block->get('hidden') === '1') {
            return false;
        }
        foreach ($this->kindFieldResolver->getManagedFieldNames() as $fieldName) {
            if (!$this->kindFieldResolver->isSameValue($fieldName, $block->get($fieldName), $concern->values[$fieldName] ?? '')) {
                return false;
            }
        }
        return true;
    }

    private function takeSnapshot(): ConversionSnapshot
    {
        $groups = [];
        foreach ($this->backendGroupRepository->findAllIncludingDeleted() as $group) {
            $groups[$group->getUid()] = $group;
        }
        return new ConversionSnapshot($groups, $this->backendUserRepository->findAllRowsIncludingDeleted());
    }

    /**
     * @param list<int> $createdUids
     * @param int|null $ownerPageGroupUid the new page group that may take the place of the group as owner of new pages
     * @return string|null a description of the change, or null if nothing changed
     */
    private function findChange(int $uid, ConversionSnapshot $before, array $createdUids, ?int $ownerPageGroupUid): ?string
    {
        $after = $this->takeSnapshot();
        // Users and groups that are new or no longer exist count as changed as well.
        foreach (array_keys($before->users + $after->users) as $userUid) {
            if (($before->users[$userUid] ?? null) !== ($after->users[$userUid] ?? null)) {
                return sprintf('The user %d would change while saving; nothing was changed.', $userUid);
            }
        }
        foreach (array_keys($before->groups + $after->groups) as $groupUid) {
            if ($groupUid !== $uid && !in_array($groupUid, $createdUids, true)
                && ($before->groups[$groupUid] ?? null)?->values !== ($after->groups[$groupUid] ?? null)?->values
            ) {
                return sprintf('The group %d would change while saving; nothing was changed.', $groupUid);
            }
        }
        $changingFields = [
            'tstamp' => true,
            GroupKind::FIELD_NAME => true,
            'subgroup' => true,
        ] + array_fill_keys($this->kindFieldResolver->getManagedFieldNames(), true);
        foreach ($before->groups[$uid]->values as $fieldName => $value) {
            if (!isset($changingFields[$fieldName]) && ($after->groups[$uid]->values[$fieldName] ?? null) !== $value) {
                return sprintf('The field "%s" of the group would change; nothing was changed.', $fieldName);
            }
        }
        return $this->findPermissionChange($uid, $before->getActiveGroups(), $after->getActiveGroups(), $before->getUsergroupLists(), $createdUids, $ownerPageGroupUid);
    }

    /**
     * Compares what TYPO3 grants every combination of groups a user has, before and after the
     * conversion, and what the group itself grants (also while it is hidden or not assigned).
     * Only the new building blocks may be added to the resolved groups. The owner group of new
     * pages may only change from the group to the new page group: it is new and only reachable
     * through the group, so it has exactly the users of the group.
     *
     * Combinations without the group are not compared: all other groups and users are unchanged.
     * Combinations that cannot reach the group through subgroups are not even resolved, so the
     * effort grows with the users of the group instead of with all users.
     *
     * @param array<int, DatabaseRow> $groupsBefore
     * @param array<int, DatabaseRow> $groupsAfter
     * @param list<string> $usergroupLists
     * @param list<int> $createdUids
     */
    private function findPermissionChange(int $uid, array $groupsBefore, array $groupsAfter, array $usergroupLists, array $createdUids, ?int $ownerPageGroupUid): ?string
    {
        $checks = array_map(
            static fn(string $usergroupList): array => [$usergroupList, sprintf('users with the groups "%s"', $usergroupList), $groupsBefore, $groupsAfter],
            $this->groupPermissionResolver->findListsReaching($uid, $usergroupLists, $groupsBefore),
        );
        $checks[] = [(string)$uid, 'the group itself', $this->withVisibleGroup($groupsBefore, $uid), $this->withVisibleGroup($groupsAfter, $uid)];

        foreach ($checks as [$usergroupList, $affected, $groupsToCompareBefore, $groupsToCompareAfter]) {
            $before = $this->groupPermissionResolver->resolve($usergroupList, $groupsToCompareBefore);
            if (!in_array($uid, $before->groupUids, true)) {
                continue;
            }
            $after = $this->groupPermissionResolver->resolve($usergroupList, $groupsToCompareAfter);
            $change = match (true) {
                $after->fields !== $before->fields => sprintf('permissions (%s)', implode(', ', array_keys(array_filter(
                    $before->fields,
                    static fn(array $values, string $fieldName): bool => $values !== ($after->fields[$fieldName] ?? []),
                    ARRAY_FILTER_USE_BOTH,
                )))),
                $after->workspacePermissions !== $before->workspacePermissions => 'workspace permissions',
                $after->tsConfig !== $before->tsConfig => 'TSconfig',
                array_values(array_diff($after->groupUids, $createdUids)) !== $before->groupUids => 'group memberships',
                $after->firstGroupUid !== $before->firstGroupUid
                    && !($before->firstGroupUid === $uid && $after->firstGroupUid === $ownerPageGroupUid) => 'owner group of new pages',
                default => null,
            };
            if ($change !== null) {
                return sprintf('The %s of %s would change; nothing was changed.', $change, $affected);
            }
        }
        return null;
    }

    /**
     * @param array<int, DatabaseRow> $groups
     * @return array<int, DatabaseRow>
     */
    private function withVisibleGroup(array $groups, int $uid): array
    {
        if (isset($groups[$uid])) {
            $groups[$uid] = $groups[$uid]->with(['hidden' => '0']);
        }
        return $groups;
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     */
    private function process(array $datamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        return $dataHandler;
    }

    /**
     * Runs a conversion in a transaction that is only committed if the conversion succeeded.
     * The system log is rolled back as well, so the result names the errors the DataHandler logged.
     *
     * @param \Closure(): ConversionResult $conversion
     */
    private function transactional(int $uid, \Closure $conversion): ConversionResult
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->beginTransaction();
        try {
            $lastLogUid = $this->systemLogRepository->findLastUid();
            $result = $conversion();
            if ($result->status === ConversionStatus::Failed) {
                $errors = $this->systemLogRepository->findErrorMessagesAfter($lastLogUid);
                if ($errors !== []) {
                    $result = new ConversionResult($uid, $result->status, $result->message . ' Logged errors: ' . implode(' | ', $errors));
                }
            }
        } catch (DatabaseException $exception) {
            // e.g. PostgreSQL aborts the whole transaction after a failed statement of the DataHandler
            $connection->rollBack();
            return new ConversionResult($uid, ConversionStatus::Failed, sprintf('Database error, nothing was changed: %s', $exception->getMessage()));
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $result->status === ConversionStatus::Converted ? $connection->commit() : $connection->rollBack();
        return $result;
    }
}
