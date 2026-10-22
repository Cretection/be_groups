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
use Cretection\BeGroups\EventListener\HideClassicKindInNewRecordWizard;
use Cretection\BeGroups\Form\FormDataProvider\KindSelection;
use Cretection\BeGroups\Form\ItemsProcessor\UnknownKindItemGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The group and user forms must never drop stored relations: a select field only keeps
 * values that are part of its items, so the items must not be filtered by kind.
 */
#[CoversClass(KindSelection::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(GroupRecordTitle::class)]
#[CoversClass(KindPrefix::class)]
#[CoversClass(HideClassicKindInNewRecordWizard::class)]
#[CoversClass(UnknownKindItemGroup::class)]
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
    public function userFormOffersClassicGroupsWhileTheyAreAllowed(): void
    {
        $formData = $this->compileForm('be_users', 4);

        self::assertContains('Classic groups', $this->getItemGroupLabels($formData, 'usergroup'));
        self::assertStringNotContainsString('classic groups are disabled', $this->getFieldDescription($formData, 'usergroup'));
    }

    #[Test]
    public function roleFormKeepsStoredMembersThatAreNoBuildingBlocksInTheirOrder(): void
    {
        $formData = $this->compileForm('be_groups', 7);

        self::assertSame(['5', '1'], $this->getRowValues($formData, 'subgroup'));
    }

    /**
     * Groups of a kind that is not configured (any more) or of no kind can neither be added to a role
     * nor be assigned to a user. They stay in the lists, so that stored relations are kept.
     */
    #[Test]
    public function groupsWithAnUnknownKindAreListedAsNotAllowed(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['tx_begroups_kind' => 'my_uninstalled_kind'], ['uid' => 3]);
        $connection->update('be_groups', ['tx_begroups_kind' => ''], ['uid' => 4]);
        $roleForm = $this->compileForm('be_groups', 6);
        $userForm = $this->compileForm('be_users', 4);

        foreach (['3', '4'] as $uid) {
            self::assertSame('Groups with an unknown kind (cannot be added to a role)', $this->getItemGroupLabelOf($roleForm, 'subgroup', $uid), $uid);
            self::assertSame('Groups with an unknown kind (cannot be assigned)', $this->getItemGroupLabelOf($userForm, 'usergroup', $uid), $uid);
        }
        // The other groups keep the group of their kind
        self::assertSame('Access rights', $this->getItemGroupLabelOf($roleForm, 'subgroup', '1'));
        self::assertSame('Roles', $this->getItemGroupLabelOf($userForm, 'usergroup', '6'));
    }

    /**
     * Every kind heading of a grouped list takes a row of its own: next to the headings, the
     * lists keep the five rows of the core for the groups.
     */
    #[Test]
    public function groupedListsShowTheRowsOfTheCoreNextToTheKindHeadings(): void
    {
        foreach (['subgroup' => $this->compileForm('be_groups', 7), 'usergroup' => $this->compileForm('be_users', 4)] as $field => $formData) {
            $config = $this->getFieldConfiguration($formData, $field);
            $itemGroups = $config['itemGroups'] ?? null;
            self::assertIsArray($itemGroups);
            self::assertGreaterThanOrEqual(5 + count($itemGroups), $config['size'] ?? 0, $field);
        }
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

    /**
     * Lists show the kind in front of the title, so they are sorted by kind and title. "Archive"
     * sorts the same way with binary and with case-insensitive collations.
     */
    #[Test]
    public function groupListsAreSortedByKindAndTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RoleArchive.csv');

        // The subgroups of a classic group are a flat list
        self::assertSame(['1', '9', '2', '3', '4', '10', '6', '7'], $this->getItemValues($this->compileForm('be_groups', 5), 'subgroup'));
    }

    #[Test]
    public function classicKindIsOfferedWhileClassicGroupsAreAllowed(): void
    {
        $formData = $this->compileForm('be_groups', 0, 'new');

        self::assertContains('classic', $this->getItemValues($formData, 'tx_begroups_kind'));
    }

    /**
     * Type "0" stays the layout of groups with an unknown kind, but it is no kind: a group created
     * with it would be saved with an unknown kind.
     */
    #[Test]
    public function newRecordWizardOffersTheKindsButNoGroupWithoutKind(): void
    {
        self::assertSame(
            ['classic', 'role', 'acl', 'page_group', 'db_mount', 'file_mount', 'file_operations', 'category_mount', 'language', 'tsconfig'],
            $this->getTypesOfferedByTheNewRecordWizard(),
        );
    }
}
