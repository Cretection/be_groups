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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Read access to be_users records. Disabled users are included, deleted ones are not.
 *
 * @internal
 */
final readonly class BackendUserRepository
{
    private const TABLE = 'be_users';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Returns the stored usergroup list of a user, including soft-deleted users
     * (the DataHandler writes to them as well), or null if the user does not exist.
     */
    public function findUsergroupListByUid(int $uid): ?string
    {
        if ($uid <= 0) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'usergroup')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : DatabaseRow::fromArray($row)->get('usergroup');
    }

    /**
     * Returns the usergroup lists of all users that have groups, including soft-deleted users, indexed by uid.
     *
     * @return array<int, string>
     */
    public function findUsergroupListsIncludingDeleted(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('uid', 'usergroup')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->neq('usergroup', $queryBuilder->createNamedParameter('')))
            ->executeQuery();

        $lists = [];
        while ($row = $result->fetchAssociative()) {
            $user = DatabaseRow::fromArray($row);
            $lists[$user->getUid()] = $user->get('usergroup');
        }
        return $lists;
    }

    /**
     * Returns all users with the fields needed to show who has which group.
     *
     * @return list<DatabaseRow>
     */
    public function findAllForOverview(): array
    {
        $result = $this->createQueryBuilder()
            ->select('uid', 'username', 'realName', 'usergroup', 'disable')
            ->from(self::TABLE)
            ->executeQuery();

        $users = [];
        while ($row = $result->fetchAssociative()) {
            $users[] = DatabaseRow::fromArray($row);
        }
        return $users;
    }

    /**
     * Returns all non-deleted users with their groups, mount options and the given permission fields,
     * for the consistency check. Passwords and other personal data are not read.
     *
     * @param list<string> $permissionFields
     * @return list<DatabaseRow>
     */
    public function findAllForAudit(array $permissionFields): array
    {
        $result = $this->createQueryBuilder()
            ->select('uid', 'username', 'admin', 'disable', 'usergroup', 'options', ...$permissionFields)
            ->from(self::TABLE)
            ->orderBy('uid')
            ->executeQuery();

        $users = [];
        while ($row = $result->fetchAssociative()) {
            $users[] = DatabaseRow::fromArray($row);
        }
        return $users;
    }

    /**
     * Returns all users with all fields as the database returns them, including deleted and disabled
     * ones, indexed by uid. The values are not normalized: they are only meant to be compared with
     * another read through the same connection, which is much faster for many users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllRowsIncludingDeleted(): array
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
            $rows[is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : 0] = $row;
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
