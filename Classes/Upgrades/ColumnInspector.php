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

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reads the actual column definitions of a table, independent of what TCA expects.
 *
 * @internal
 */
final readonly class ColumnInspector
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param non-empty-string $table
     * @return array<string, ColumnInfo> indexed by the lower-cased column name
     */
    public function inspect(string $table): array
    {
        $columns = [];
        $schemaManager = $this->connectionPool->getConnectionForTable($table)->createSchemaManager();
        foreach ($schemaManager->introspectTableColumnsByUnquotedName($table) as $column) {
            $type = $column->getType();
            $name = strtolower(trim($column->getObjectName()->toString(), '`"[]'));
            $columns[$name] = new ColumnInfo(
                $type instanceof StringType || $type instanceof TextType,
                $type instanceof IntegerType || $type instanceof SmallIntType || $type instanceof BigIntType,
                $type instanceof TextType ? null : $column->getLength(),
            );
        }
        return $columns;
    }
}
