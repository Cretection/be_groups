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
    public function onlyRolesAndClassicGroupsAreNoBuildingBlocks(): void
    {
        foreach (GroupKind::cases() as $kind) {
            self::assertSame($kind !== GroupKind::Role && $kind !== GroupKind::Classic, $kind->isBuildingBlock(), $kind->value);
        }
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
