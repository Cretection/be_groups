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
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Read access to the owner groups of pages (page permissions).
 *
 * @internal
 */
final readonly class PageOwnerRepository
{
    private const TABLE = 'pages';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return list<int> the uids of all groups that own at least one non-deleted page
     */
    public function findOwnerGroupUids(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $result = $queryBuilder
            ->select('perms_groupid')
            ->distinct()
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->gt('perms_groupid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery();

        $uids = [];
        while ($row = $result->fetchAssociative()) {
            $uids[] = (int)DatabaseRow::fromArray($row)->get('perms_groupid');
        }
        return $uids;
    }
}
