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

/**
 * Reads errors from the system log. The DataHandler reports failed writes only there.
 *
 * @internal
 */
final readonly class SystemLogRepository
{
    private const TABLE = 'sys_log';

    /**
     * Value of SystemLogType::DB, used by the DataHandler.
     */
    private const TYPE_DATABASE = 1;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findLastUid(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->selectLiteral('MAX(' . $queryBuilder->quoteIdentifier('uid') . ') AS ' . $queryBuilder->quoteIdentifier('last_uid'))
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchAssociative();
        return is_array($row) ? (int)DatabaseRow::fromArray($row)->get('last_uid') : 0;
    }

    /**
     * @return list<string> the messages of database errors (type 1, as logged by the DataHandler) after the
     *                      given entry, with their values inserted
     */
    public function findErrorMessagesAfter(int $uid, int $limit = 3): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('details', 'log_data')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->gt('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter(self::TYPE_DATABASE, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('error', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('uid')
            ->setMaxResults($limit)
            ->executeQuery();

        $messages = [];
        while ($row = $result->fetchAssociative()) {
            $entry = DatabaseRow::fromArray($row);
            $messages[] = $this->format($entry->get('details'), $entry->get('log_data'));
        }
        return $messages;
    }

    /**
     * Inserts the logged values into "{name}" and sprintf style placeholders.
     */
    private function format(string $details, string $logData): string
    {
        $data = $logData !== '' ? json_decode($logData, true) : null;
        if (!is_array($data)) {
            return $details;
        }
        $values = array_map(static fn(mixed $value): string => is_scalar($value) ? (string)$value : '', $data);
        if (!array_is_list($values)) {
            $replacements = [];
            foreach ($values as $name => $value) {
                $replacements['{' . $name . '}'] = $value;
            }
            return strtr($details, $replacements);
        }
        try {
            return vsprintf($details, $values);
        } catch (\ValueError) {
            return $details;
        }
    }
}
