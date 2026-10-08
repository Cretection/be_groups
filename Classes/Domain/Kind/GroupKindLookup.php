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

namespace Cretection\BeGroups\Domain\Kind;

use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Title and kind of a group by its uid, for lists that only know the uid. All groups are read
 * once per request.
 *
 * @internal
 */
final readonly class GroupKindLookup
{
    private const CACHE_IDENTIFIER = 'begroups_titles_and_kinds_by_uid';

    public function __construct(
        private BackendGroupRepository $backendGroupRepository,
        #[Autowire(service: 'cache.runtime')]
        private FrontendInterface $runtimeCache,
    ) {}

    public function getKind(int $uid): string
    {
        return $this->getGroups()[$uid]['kind'] ?? '';
    }

    public function getTitle(int $uid): string
    {
        return $this->getGroups()[$uid]['title'] ?? '';
    }

    /**
     * @return array<int, array{title: string, kind: string}>
     */
    private function getGroups(): array
    {
        $groups = $this->runtimeCache->get(self::CACHE_IDENTIFIER);
        if (!is_array($groups)) {
            $groups = $this->backendGroupRepository->findAllTitlesAndKinds();
            $this->runtimeCache->set(self::CACHE_IDENTIFIER, $groups);
        }
        /** @var array<int, array{title: string, kind: string}> $groups */
        return $groups;
    }
}
