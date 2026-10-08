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

use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
final class KindFieldResolverTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    #[Test]
    public function everyFieldOfTheClassicFormBelongsToAKindWithoutOtherExtensions(): void
    {
        self::assertSame([], $this->get(KindFieldResolver::class)->getUnassignedFieldNames());
    }

    #[Test]
    public function comparesListsAsSetsUnlessTheirOrderMatters(): void
    {
        $resolver = $this->get(KindFieldResolver::class);

        // The default flags of sort() do not order mixed numeric and other strings consistently.
        self::assertTrue($resolver->isSameValue('groupMods', '10,9,1a', '1a,10,9'));
        self::assertTrue($resolver->isSameValue('file_permissions', 'readFile,writeFile', 'writeFile,readFile'));
        self::assertFalse($resolver->isSameValue('groupMods', 'web_layout', 'web_layout,web_list'));
        self::assertFalse($resolver->isSameValue('subgroup', '1,2', '2,1'));
        self::assertFalse($resolver->isSameValue('tsconfig_includes', 'a.tsconfig,b.tsconfig', 'b.tsconfig,a.tsconfig'));
        self::assertFalse($resolver->isSameValue('TSconfig', 'a = 1', 'a = 1 '));
    }

    #[Test]
    public function knowsWhichValuesGrantNothing(): void
    {
        $resolver = $this->get(KindFieldResolver::class);

        self::assertTrue($resolver->isEmptyValue('category_perms', '0'));
        self::assertTrue($resolver->isEmptyValue('workspace_perms', 0));
        // The root of the page tree and the default language are permissions.
        self::assertFalse($resolver->isEmptyValue('db_mountpoints', '0'));
        self::assertFalse($resolver->isEmptyValue('allowed_languages', '0'));
        self::assertFalse($resolver->isEmptyValue('allowed_languages', '0', 'be_users'));
    }
}
