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

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Form\FormDataProvider\KindSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(KindSelection::class)]
#[CoversClass(ExtensionSettings::class)]
final class GroupFormWithClassicGroupsDisabledTest extends AbstractFormTestCase
{
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'be_groups' => [
                'allowClassicGroups' => '0',
            ],
        ],
    ];

    #[Test]
    public function newGroupStartsAsRoleWithoutClassicKind(): void
    {
        $formData = $this->compileForm('be_groups', 0, 'new');

        self::assertSame('role', $formData['recordTypeValue']);
        self::assertNotContains('classic', $this->getItemValues($formData, 'tx_begroups_kind'));
    }

    #[Test]
    public function existingClassicGroupKeepsItsKindInTheForm(): void
    {
        $formData = $this->compileForm('be_groups', 5);

        self::assertSame('classic', $formData['recordTypeValue']);
        self::assertContains('classic', $this->getItemValues($formData, 'tx_begroups_kind'));
    }

    #[Test]
    public function otherGroupsCannotBeSwitchedToClassicInTheForm(): void
    {
        $formData = $this->compileForm('be_groups', 2);

        self::assertNotContains('classic', $this->getItemValues($formData, 'tx_begroups_kind'));
    }
}
