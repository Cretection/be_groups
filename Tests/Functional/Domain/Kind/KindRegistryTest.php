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

namespace Cretection\BeGroups\Tests\Functional\Domain\Kind;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindDefinition;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(KindRegistry::class)]
#[CoversClass(KindDefinition::class)]
final class KindRegistryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_kind_without_icon',
    ];

    /**
     * Groups of an unknown kind are neither roles nor building blocks, and a kind without an icon
     * of its own is no role: none of them may look like a role.
     */
    #[Test]
    public function onlyRolesUseTheIconOfRoles(): void
    {
        $kindRegistry = $this->get(KindRegistry::class);

        self::assertTrue($kindRegistry->isBuildingBlock('my_plain'));
        foreach (['my_plain', 'my_uninstalled_kind', ''] as $kind) {
            self::assertNotSame(GroupKind::Role->iconIdentifier(), $kindRegistry->getIconIdentifier($kind), $kind);
            self::assertSame(KindRegistry::FALLBACK_ICON, $kindRegistry->getIconIdentifier($kind), $kind);
        }
        self::assertSame(GroupKind::Role->iconIdentifier(), $kindRegistry->getIconIdentifier('role'));
    }
}
