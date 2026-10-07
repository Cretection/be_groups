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

namespace Cretection\BeGroups\Tests\Unit\Domain\Kind;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(GroupKind::class)]
final class GroupKindTest extends UnitTestCase
{
    #[Test]
    public function rolesAndClassicGroupsAreNoBuildingBlocks(): void
    {
        self::assertFalse(GroupKind::isBuildingBlock(GroupKind::Role->value));
        self::assertFalse(GroupKind::isBuildingBlock(GroupKind::Classic->value));
        self::assertFalse(GroupKind::isBuildingBlock(''));
    }

    #[Test]
    public function everyOtherKindIsABuildingBlock(): void
    {
        foreach (GroupKind::cases() as $kind) {
            if ($kind === GroupKind::Role || $kind === GroupKind::Classic) {
                continue;
            }
            self::assertTrue(GroupKind::isBuildingBlock($kind->value), $kind->value);
        }
        self::assertTrue(GroupKind::isBuildingBlock('kind_of_another_extension'));
    }

    #[Test]
    public function everyKindHasAnIconAndOnlyClassicHasNoPrefix(): void
    {
        foreach (GroupKind::cases() as $kind) {
            self::assertNotSame('', $kind->iconIdentifier(), $kind->value);
            self::assertSame($kind === GroupKind::Classic, $kind->prefix() === '', $kind->value);
        }
    }
}
