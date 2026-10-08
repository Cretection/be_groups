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

namespace Cretection\BeGroups\Domain\Repository;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Read access to be_groups records. Hidden groups are always included, deleted ones only where stated.
 *
 * @internal
 */
final readonly class BackendGroupRepository
{
    private const TABLE = 'be_groups';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Returns the requested fields of a group, including soft-deleted groups:
     * the DataHandler writes to them as well, so the rules have to see them.
     *
     * @param list<string> $fieldNames
     */
    public function findFieldsByUid(int $uid, array $fieldNames): ?DatabaseRow
    {
        if ($uid <= 0) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', ...$fieldNames)
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : DatabaseRow::fromArray($row);
    }

    /**
     * Returns the kind of every given non-deleted group, indexed by uid. Missing or deleted groups are not part of the result.
     *
     * @param list<int> $uids
     * @return array<int, string>
     */
    public function findKindsByUids(array $uids): array
    {
        $uids = array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->createQueryBuilder();
        $result = $queryBuilder
            ->select('uid', GroupKind::FIELD_NAME)
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
            )
            ->executeQuery();

        $kinds = [];
        while ($row = $result->fetchAssociative()) {
            $group = DatabaseRow::fromArray($row);
            $kinds[$group->getUid()] = $group->get(GroupKind::FIELD_NAME);
        }
        return $kinds;
    }

    /**
     * Returns complete records of the given kinds, including soft-deleted ones, indexed by uid.
     *
     * @param list<string> $kinds
     * @return array<int, DatabaseRow>
     */
    public function findByKindsIncludingDeleted(array $kinds): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in(GroupKind::FIELD_NAME, $queryBuilder->createNamedParameter($kinds, Connection::PARAM_STR_ARRAY)),
            )
            ->orderBy('uid')
            ->executeQuery();

        $groups = [];
        while ($row = $result->fetchAssociative()) {
            $group = DatabaseRow::fromArray($row);
            $groups[$group->getUid()] = $group;
        }
        return $groups;
    }

    /**
     * Returns the subgroup lists of all groups that have subgroups, including soft-deleted groups, indexed by uid.
     *
     * @return array<int, string>
     */
    public function findSubgroupListsIncludingDeleted(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('uid', 'subgroup')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->neq('subgroup', $queryBuilder->createNamedParameter('')))
            ->executeQuery();

        $lists = [];
        while ($row = $result->fetchAssociative()) {
            $group = DatabaseRow::fromArray($row);
            $lists[$group->getUid()] = $group->get('subgroup');
        }
        return $lists;
    }

    /**
     * Returns all groups with the fields needed to show the permission structure.
     *
     * @return list<DatabaseRow>
     */
    public function findAllForOverview(): array
    {
        $result = $this->createQueryBuilder()
            ->select('uid', 'title', GroupKind::FIELD_NAME, 'subgroup', 'hidden')
            ->from(self::TABLE)
            ->executeQuery();

        $groups = [];
        while ($row = $result->fetchAssociative()) {
            $groups[] = DatabaseRow::fromArray($row);
        }
        return $groups;
    }

    /**
     * Returns all non-deleted groups with all fields, ordered by uid.
     *
     * @return list<DatabaseRow>
     */
    public function findAll(): array
    {
        $result = $this->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('uid')
            ->executeQuery();

        $groups = [];
        while ($row = $result->fetchAssociative()) {
            $groups[] = DatabaseRow::fromArray($row);
        }
        return $groups;
    }

    /**
     * Returns a non-deleted group with all fields.
     */
    public function findByUid(int $uid): ?DatabaseRow
    {
        $queryBuilder = $this->createQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return is_array($row) ? DatabaseRow::fromArray($row) : null;
    }

    /**
     * Returns all groups with all fields, including deleted and disabled ones, ordered by uid.
     *
     * @return list<DatabaseRow>
     */
    public function findAllIncludingDeleted(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('uid')
            ->executeQuery();

        $rows = [];
        while ($row = $result->fetchAssociative()) {
            $rows[] = DatabaseRow::fromArray($row);
        }
        return $rows;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        return $queryBuilder;
    }
}
