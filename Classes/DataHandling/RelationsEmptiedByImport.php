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
 * relation field empty, or with its TCA default if it has one (Import::addSingle()), the second run
 * sets the relations (Import::setRelations()). In the second run, the relations stored before the
 * import are the ones the first run emptied; otherwise R2 and R4 would remove stored relations that
 * break them.
 *
 * An importing write that empties a relation field remembers the relations stored before, for the
 * next write of the field in the same request. That write uses them as stored relations if it is an
 * importing write and the field is still empty. Every write of the field ends what is remembered.
 * A field counts as empty if it is empty or holds the TCA default.
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
     * @param RelationList $default the TCA default of the field, which an import writes instead of an empty value
     */
    public function getStoredRelations(string $table, int $uid, string $fieldName, RelationList $stored, RelationList $incoming, RelationList $default, bool $isImporting): RelationList
    {
        $isEmpty = static fn(RelationList $relations): bool => $relations->entries === [] || $relations->entries === $default->entries;
        $key = $table . ':' . $uid . ':' . $fieldName;
        $emptiedRelations = $this->getEmptiedRelations();
        $relationsBeforeImport = $emptiedRelations[$key] ?? null;
        unset($emptiedRelations[$key]);
        if ($isImporting && $isEmpty($incoming) && $stored->entries !== [] && $stored->entries !== $incoming->entries) {
            $emptiedRelations[$key] = $stored->getUids();
        }
        $this->runtimeCache->set(self::CACHE_IDENTIFIER, $emptiedRelations);

        if ($isImporting && $relationsBeforeImport !== null && $isEmpty($stored)) {
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
