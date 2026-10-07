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
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Doctrine\DBAL\Exception as DatabaseException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Converts classic groups as proposed by the GroupClassifier.
 *
 * All changes are written through the DataHandler (rules, system log, history, reference
 * index) within one transaction per group. Afterwards the stored records are compared with
 * the former group: if the effective permissions differ in any way, the transaction is
 * rolled back and the group stays unchanged.
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
        private BackendGroupRepository $backendGroupRepository,
        private KindFieldResolver $kindFieldResolver,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Changes the kind of a classic group whose permissions belong to one kind.
     */
    public function changeKind(int $uid): ConversionResult
    {
        return $this->transactional($uid, function () use ($uid): ConversionResult {
            $classification = $this->groupClassifier->classify($uid);
            $before = $this->backendGroupRepository->findByUid($uid);
            if ($classification === null || $before === null
                || $classification->action !== ClassificationAction::ChangeKind || $classification->targetKind === null
            ) {
                return new ConversionResult($uid, ConversionStatus::Skipped, 'Not a classic group whose kind can be changed, see begroups:classify.');
            }

            $this->process([self::TABLE => [$uid => [GroupKind::FIELD_NAME => $classification->targetKind]]]);

            $after = $this->backendGroupRepository->findByUid($uid);
            if ($after === null || $after->get(GroupKind::FIELD_NAME) !== $classification->targetKind) {
                return new ConversionResult($uid, ConversionStatus::Failed, 'The kind could not be changed, e.g. because a rule or another extension rejected it. Nothing was changed.');
            }
            foreach ($this->kindFieldResolver->getManagedFieldNames() as $fieldName) {
                if (!$this->kindFieldResolver->isSameValue($fieldName, $before->get($fieldName), $after->get($fieldName))) {
                    return new ConversionResult($uid, ConversionStatus::Failed, sprintf('The field "%s" would change; nothing was changed.', $fieldName));
                }
            }
            return new ConversionResult($uid, ConversionStatus::Converted, sprintf('The kind is now "%s".', $classification->targetKind));
        });
    }

    /**
     * Moves the permissions of a classic group into building blocks and makes the group a role
     * that contains them. The group keeps its uid, so users and other groups keep their assignments.
     */
    public function split(int $uid): ConversionResult
    {
        return $this->transactional($uid, function () use ($uid): ConversionResult {
            $classification = $this->groupClassifier->classify($uid);
            $group = $this->backendGroupRepository->findByUid($uid);
            if ($classification === null || $group === null || $classification->action !== ClassificationAction::Split) {
                return new ConversionResult($uid, ConversionStatus::Skipped, 'Not a classic group that can be split, see begroups:classify.');
            }

            $blocks = $this->createBlocks($group, $classification);
            if ($blocks === null) {
                return new ConversionResult(
                    $uid,
                    ConversionStatus::Failed,
                    'A building block could not be created with exactly the permissions of the group, e.g. because of values '
                    . 'the DataHandler no longer accepts (modules or tables that do not exist any more). Save the group once '
                    . 'in the backend to clean it up. Nothing was changed.',
                );
            }
            [$blockUids, $createdUids, $reusedUids] = $blocks;

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
            if ($role === null
                || $role->get(GroupKind::FIELD_NAME) !== GroupKind::Role->value
                || $this->getEffectiveMembers($role->get('subgroup')) !== $this->getEffectiveMembers($subgroups->toString())
            ) {
                return new ConversionResult($uid, ConversionStatus::Failed, 'The group could not be changed into a role as planned; nothing was changed.');
            }
            foreach ($this->kindFieldResolver->getForeignFieldsWithEmptyValue(GroupKind::Role->value) as $fieldName => $emptyValue) {
                if (!$this->kindFieldResolver->isEmptyValue($fieldName, $role->get($fieldName))) {
                    return new ConversionResult($uid, ConversionStatus::Failed, sprintf('The field "%s" of the role could not be emptied; nothing was changed.', $fieldName));
                }
            }
            return new ConversionResult(
                $uid,
                ConversionStatus::Converted,
                sprintf('The group is now a role with %d new and %d existing building block(s).', count($createdUids), count($reusedUids)),
                $createdUids,
                $reusedUids,
            );
        });
    }

    /**
     * @return array{list<int>, list<int>, list<int>}|null all building blocks in the order of the concerns,
     *                                                    the created and the reused ones, or null if a
     *                                                    created block does not grant exactly the same
     */
    private function createBlocks(DatabaseRow $group, Classification $classification): ?array
    {
        $blocks = [];
        $reusedUids = [];
        $placeholders = [];
        $datamap = [];
        foreach ($classification->concerns as $index => $concern) {
            if ($concern->reusableBlockUid !== null) {
                $blocks[] = $concern->reusableBlockUid;
                $reusedUids[] = $concern->reusableBlockUid;
                continue;
            }
            $placeholder = 'NEW_begroups_split_' . $index;
            $blocks[] = $placeholder;
            $placeholders[$placeholder] = $concern;
            $datamap[self::TABLE][$placeholder] = [
                'pid' => (int)$group->get('pid'),
                'title' => (GroupKind::tryFrom($concern->kind)?->prefix() ?? strtoupper($concern->kind) . '_') . $group->get('title'),
                'description' => sprintf('Created by begroups:split from group %d "%s".', $group->getUid(), $group->get('title')),
                // Visible: the blocks are only reachable through the group, which keeps its hidden state,
                // so showing the group again restores its permissions as before.
                'hidden' => 0,
                GroupKind::FIELD_NAME => $concern->kind,
            ] + $concern->values;
        }
        if ($datamap === []) {
            return [$reusedUids, [], $reusedUids];
        }

        $dataHandler = $this->process($datamap);
        $createdUids = [];
        foreach ($placeholders as $placeholder => $concern) {
            $blockUid = $dataHandler->substNEWwithIDs[$placeholder] ?? null;
            $block = is_int($blockUid) ? $this->backendGroupRepository->findByUid($blockUid) : null;
            if ($block === null || !$this->grantsExactly($block, $concern)) {
                return null;
            }
            $createdUids[$placeholder] = $block->getUid();
        }
        return [
            array_map(static fn(int|string $block): int => is_int($block) ? $block : $createdUids[$block], $blocks),
            array_values($createdUids),
            $reusedUids,
        ];
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

    /**
     * The members TYPO3 resolves, in their order: entries without an existing group grant nothing.
     *
     * @return list<int>
     */
    private function getEffectiveMembers(string $subgroupList): array
    {
        return array_values(array_filter(
            RelationList::fromValue($subgroupList)->getUids(),
            fn(int $memberUid): bool => $this->backendGroupRepository->findByUid($memberUid) !== null,
        ));
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
     * Entries of the system log written during a rolled back conversion are gone as well, so the
     * result explains what happened.
     *
     * @param \Closure(): ConversionResult $conversion
     */
    private function transactional(int $uid, \Closure $conversion): ConversionResult
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->beginTransaction();
        try {
            $result = $conversion();
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
