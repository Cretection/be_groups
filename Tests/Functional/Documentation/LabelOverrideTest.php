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

namespace Cretection\BeGroups\Tests\Functional\Documentation;

use Cretection\BeGroups\Backend\GroupRecordTitle;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Other prefixes are configured as the manual shows (Usage, "The kind as prefix"): the
 * ext_localconf.php of a site package overrides the labels kind.prefix.<kind> through the
 * option TYPO3 14 reads, with the German labels in a file with the prefix "de." next to it.
 */
#[CoversClass(KindPrefix::class)]
#[CoversClass(GroupRecordTitle::class)]
final class LabelOverrideTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_sitepackage',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function languageDataProvider(): array
    {
        return [
            'English' => ['default', 'ROLE: Editors'],
            'German' => ['de', 'ROLLE: Editors'],
        ];
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function theOverriddenPrefixIsShownAndTheOthersStay(string $language, string $expectedRoleTitle): void
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($language);

        self::assertSame($expectedRoleTitle, BackendUtility::getRecordTitle('be_groups', ['uid' => 1, 'title' => 'Editors', 'tx_begroups_kind' => 'role']));
        self::assertSame('ACL: Editors', BackendUtility::getRecordTitle('be_groups', ['uid' => 2, 'title' => 'Editors', 'tx_begroups_kind' => 'acl']));
    }
}
