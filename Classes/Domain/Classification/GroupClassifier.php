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
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Domain\Repository\PageOwnerRepository;

/**
 * Proposes how a classic group fits into the role model, without changing effective permissions.
 *
 * Every permission field of the group is assigned to the kind of building block whose form
 * shows it. A group whose permissions belong to one kind only changes its kind; a group with
 * permissions of several kinds, with subgroups and own permissions, or that is assigned to
 * users directly is split: the group keeps its uid as a role, so assignments stay unchanged.
 *
 * @internal
 */
final readonly class GroupClassifier
{
    /**
     * Fields whose order decides the precedence of TSconfig. Building blocks with these fields
     * are never shared, as TYPO3 applies a group that is reached twice only once.
     */
    private const ORDER_SENSITIVE_FIELDS = ['TSconfig', 'tsconfig_includes'];

    public function __construct(
        private BackendGroupRepository $backendGroupRepository,
        private BackendUserRepository $backendUserRepository,
        private PageOwnerRepository $pageOwnerRepository,
        private KindRegistry $kindRegistry,
        private KindFieldResolver $kindFieldResolver,
    ) {}

    /**
     * @return list<Classification> a proposal for every classic group, ordered by uid
     */
    public function classifyAll(): array
    {
        return $this->classifyGroups(null);
    }

    public function classify(int $uid): ?Classification
    {
        return $this->classifyGroups($uid)[0] ?? null;
    }

    /**
     * @return list<Classification>
     */
    private function classifyGroups(?int $onlyUid): array
    {
        $groups = $this->backendGroupRepository->findAll();
        $directUserCounts = $this->countDirectUsers();
        $pageOwners = array_flip($this->pageOwnerRepository->findOwnerGroupUids());

        $classifications = [];
        foreach ($groups as $group) {
            if (($onlyUid === null || $group->getUid() === $onlyUid) && $group->get(GroupKind::FIELD_NAME) === GroupKind::Classic->value) {
                $classifications[] = $this->classifyGroup($group, $groups, $directUserCounts[$group->getUid()] ?? 0, isset($pageOwners[$group->getUid()]));
            }
        }
        return $classifications;
    }

    /**
     * @param list<DatabaseRow> $groups all non-deleted groups
     */
    private function classifyGroup(DatabaseRow $group, array $groups, int $directUsers, bool $ownsPages): Classification
    {
        $values = [];
        foreach ($this->kindFieldResolver->getManagedFieldNames() as $fieldName) {
            if ($fieldName !== 'subgroup' && !$this->kindFieldResolver->isEmptyValue($fieldName, $group->get($fieldName))) {
                $values[$fieldName] = $group->get($fieldName);
            }
        }
        [$concerns, $fieldsWithoutKind] = $this->assignToKinds($values);
        $isHidden = $group->get('hidden') === '1';
        $hasSubgroups = RelationList::fromValue($group->get('subgroup'))->getUids() !== [];
        $firstMembers = $this->findFirstMembers($groups);
        $concerns = array_map(fn(int $index, Concern $concern): Concern => new Concern(
            $concern->kind,
            $concern->values,
            // TYPO3 makes the first group a user resolves the owner group of the pages the user creates.
            // Without subgroups, the first building block takes this place: it must belong to this role only.
            $index === 0 && !$hasSubgroups ? null : $this->findReusableBlock($concern, $group->getUid(), $groups, $firstMembers),
        ), array_keys($concerns), $concerns);
        $proposal = fn(ClassificationAction $action, ?string $targetKind, string $reason): Classification
            => new Classification($group->getUid(), $group->get('title'), $isHidden, $action, $targetKind, $concerns, $reason);

        if ($fieldsWithoutKind !== []) {
            return $proposal(ClassificationAction::Manual, null, sprintf(
                'The fields %s belong to no kind of building block. Assign them to a kind or keep the group classic.',
                implode(', ', $fieldsWithoutKind),
            ));
        }
        if ($concerns === []) {
            if ($hasSubgroups) {
                return $proposal(ClassificationAction::ChangeKind, GroupKind::Role->value, 'Only combines other groups.');
            }
            if (!$ownsPages) {
                return $proposal(ClassificationAction::Manual, null, 'Grants no permissions. Choose a kind or delete the group.');
            }
            return $directUsers === 0
                ? $proposal(ClassificationAction::ChangeKind, GroupKind::PageGroup->value, 'Grants no permissions, but owns pages.')
                : $proposal(ClassificationAction::Manual, null, 'Owns pages and is assigned to users directly. Make it a page group and assign it through a role.');
        }
        if (count($concerns) === 1 && !$hasSubgroups && $directUsers === 0) {
            return $proposal(ClassificationAction::ChangeKind, $concerns[0]->kind, sprintf('Only grants permissions of the kind "%s".', $concerns[0]->kind));
        }
        if (count($concerns) > 1) {
            $reason = sprintf('Grants permissions of several kinds: %s.', implode(', ', array_map(static fn(Concern $concern): string => $concern->kind, $concerns)));
        } elseif ($hasSubgroups) {
            $reason = 'Combines other groups and grants permissions of its own.';
        } else {
            $reason = sprintf('Is assigned to %d user(s) directly, so it stays their role.', $directUsers);
        }
        return $proposal(ClassificationAction::Split, GroupKind::Role->value, $reason);
    }

    /**
     * Assigns each permission field to a kind of building block: preferably all to one kind,
     * otherwise each field to the first kind (in the order of the kinds) whose form shows it.
     *
     * @param array<string, string> $values
     * @return array{list<Concern>, list<string>} the concerns and the fields that belong to no kind
     */
    private function assignToKinds(array $values): array
    {
        if ($values === []) {
            return [[], []];
        }
        $kindFields = [];
        foreach ($this->kindRegistry->getDefinitions() as $definition) {
            if ($this->kindRegistry->isBuildingBlock($definition->value)) {
                $kindFields[] = [$definition->value, $this->kindFieldResolver->getPermissionFieldNames($definition->value)];
            }
        }
        foreach ($kindFields as [$kind, $fieldNames]) {
            if (array_diff(array_keys($values), $fieldNames) === []) {
                return [[new Concern($kind, $values)], []];
            }
        }

        $concerns = [];
        $assigned = [];
        foreach ($kindFields as [$kind, $fieldNames]) {
            $concernValues = array_diff_key(array_intersect_key($values, array_flip($fieldNames)), $assigned);
            if ($concernValues !== []) {
                $concerns[] = new Concern($kind, $concernValues);
                $assigned += $concernValues;
            }
        }
        return [$concerns, array_keys(array_diff_key($values, $assigned))];
    }

    /**
     * Finds an existing building block that grants exactly the same, so splitting many groups
     * does not create many identical blocks. Hidden blocks grant nothing and are never reused. A block
     * that is the first member of a group may be the owner group of new pages of some users; sharing
     * it would widen that group.
     *
     * @param list<DatabaseRow> $groups
     * @param array<int, true> $firstMembers
     */
    private function findReusableBlock(Concern $concern, int $sourceUid, array $groups, array $firstMembers): ?int
    {
        if (array_intersect(array_keys($concern->values), self::ORDER_SENSITIVE_FIELDS) !== []) {
            return null;
        }
        foreach ($groups as $candidate) {
            if ($candidate->getUid() !== $sourceUid
                && !isset($firstMembers[$candidate->getUid()])
                && $candidate->get(GroupKind::FIELD_NAME) === $concern->kind
                && $candidate->get('hidden') !== '1'
                && $this->grantsExactly($candidate, $concern->values)
            ) {
                return $candidate->getUid();
            }
        }
        return null;
    }

    /**
     * @param list<DatabaseRow> $groups
     * @return array<int, true> the uids of all groups that are the first member of a group
     */
    private function findFirstMembers(array $groups): array
    {
        $firstMembers = [];
        foreach ($groups as $group) {
            $firstMember = RelationList::fromValue($group->get('subgroup'))->getUids()[0] ?? null;
            if ($firstMember !== null) {
                $firstMembers[$firstMember] = true;
            }
        }
        return $firstMembers;
    }

    /**
     * @param array<string, string> $values
     */
    private function grantsExactly(DatabaseRow $group, array $values): bool
    {
        foreach ($this->kindFieldResolver->getManagedFieldNames() as $fieldName) {
            if (!$this->kindFieldResolver->isSameValue($fieldName, $group->get($fieldName), $values[$fieldName] ?? '')) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array<int, int> the number of users per group they are assigned to directly
     */
    private function countDirectUsers(): array
    {
        $counts = [];
        foreach ($this->backendUserRepository->findAllForOverview() as $user) {
            foreach (RelationList::fromValue($user->get('usergroup'))->getUids() as $groupUid) {
                $counts[$groupUid] = ($counts[$groupUid] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
