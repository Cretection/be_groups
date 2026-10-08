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

namespace Cretection\BeGroups\Tests\Functional\Form;

use Cretection\BeGroups\Backend\GroupRecordTitle;
use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Form\FormDataProvider\KindSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * The group and user forms must never drop stored relations: a select field only keeps
 * values that are part of its items, so the items must not be filtered by kind.
 */
#[CoversClass(KindSelection::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(GroupRecordTitle::class)]
#[CoversClass(KindPrefix::class)]
final class GroupAndUserFormTest extends AbstractFormTestCase
{
    #[Test]
    public function userFormKeepsStoredAssignmentsThatAreNoRoles(): void
    {
        $formData = $this->compileForm('be_users', 4);

        self::assertSame(['6', '1'], $this->getRowValues($formData, 'usergroup'));
        self::assertContains('1', $this->getItemValues($formData, 'usergroup'));
    }

    #[Test]
    public function roleFormKeepsStoredMembersThatAreNoBuildingBlocksInTheirOrder(): void
    {
        $formData = $this->compileForm('be_groups', 7);

        self::assertSame(['5', '1'], $this->getRowValues($formData, 'subgroup'));
    }

    #[Test]
    public function groupsAreShownWithThePrefixOfTheirKind(): void
    {
        $formData = $this->compileForm('be_users', 4);

        $labels = $this->getItemLabels($formData, 'usergroup');
        self::assertContains('META: R editor', $labels);
        self::assertContains('ACL: ACL editing', $labels);
        self::assertContains('Classic all-in-one', $labels);
        self::assertSame('META: R other', $this->compileForm('be_groups', 7)['recordTitle'] ?? null);
    }

    #[Test]
    public function classicKindIsOfferedWhileClassicGroupsAreAllowed(): void
    {
        $formData = $this->compileForm('be_groups', 0, 'new');

        self::assertContains('classic', $this->getItemValues($formData, 'tx_begroups_kind'));
    }
}
