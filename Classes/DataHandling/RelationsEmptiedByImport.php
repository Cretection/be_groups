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

namespace Cretection\BeGroups\DataHandling;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * The relations an import has emptied, so that the import can set them again.
 *
 * EXT:impexp writes an "Update records" import in two DataHandler runs: the first run writes every
 * relation field empty (Import::addSingle()), the second run sets the relations (Import::setRelations()).
 * In the second run, the relations stored before the import are the ones the first run emptied;
 * otherwise R2 and R4 would remove stored relations that break them.
 *
 * An importing write that empties a relation field remembers the relations stored before, for the
 * next write of the field in the same request. That write uses them as stored relations if it is an
 * importing write and the field is still empty. Every write of the field ends what is remembered.
 *
 * @internal
 */
final readonly class RelationsEmptiedByImport
{
    private const CACHE_IDENTIFIER = 'begroups_relations_emptied_by_import';

    public function __construct(
        #[Autowire(service: 'cache.runtime')]
        private FrontendInterface $runtimeCache,
    ) {}

    /**
     * The relations a write of a stored record is checked against.
     *
     * @param RelationList $stored the relations stored in the database
     * @param RelationList $incoming the relations the write sets
     */
    public function getStoredRelations(string $table, int $uid, string $fieldName, RelationList $stored, RelationList $incoming, bool $isImporting): RelationList
    {
        $key = $table . ':' . $uid . ':' . $fieldName;
        $emptiedRelations = $this->getEmptiedRelations();
        $relationsBeforeImport = $emptiedRelations[$key] ?? null;
        unset($emptiedRelations[$key]);
        if ($isImporting && $incoming->entries === [] && $stored->entries !== []) {
            $emptiedRelations[$key] = $stored->getUids();
        }
        $this->runtimeCache->set(self::CACHE_IDENTIFIER, $emptiedRelations);

        if ($isImporting && $relationsBeforeImport !== null && $stored->entries === []) {
            return RelationList::fromEntries($relationsBeforeImport);
        }
        return $stored;
    }

    /**
     * @return array<string, list<int>>
     */
    private function getEmptiedRelations(): array
    {
        $emptiedRelations = $this->runtimeCache->get(self::CACHE_IDENTIFIER);
        if (!is_array($emptiedRelations)) {
            return [];
        }
        /** @var array<string, list<int>> $emptiedRelations */
        return $emptiedRelations;
    }
}
