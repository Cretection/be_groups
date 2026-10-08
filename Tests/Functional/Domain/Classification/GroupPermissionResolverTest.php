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

namespace Cretection\BeGroups\Tests\Functional\Domain\Classification;

use Cretection\BeGroups\Domain\Classification\GrantedPermissions;
use Cretection\BeGroups\Domain\Classification\GroupPermissionResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The model of GroupPermissionResolver must match what TYPO3 itself grants, checked through
 * the public API of the backend user.
 */
#[CoversClass(GroupPermissionResolver::class)]
#[CoversClass(GrantedPermissions::class)]
final class GroupPermissionResolverTest extends FunctionalTestCase
{
    private const MODULES = ['web_layout', 'web_list', 'web_info', 'records'];

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Resolution.csv');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function userDataProvider(): array
    {
        return [
            'duplicate subgroups' => [1, '4'],
            'subgroup entry TYPO3 ignores' => [2, '5'],
            'cycle' => [3, '6'],
            'hidden subgroup' => [4, '8'],
            'order of the groups' => [5, '2,4'],
            'root page mount' => [6, '9,1'],
            'usergroup entry TYPO3 ignores' => [7, 'be_groups_1,2'],
        ];
    }

    #[Test]
    #[DataProvider('userDataProvider')]
    public function grantsWhatTypo3Grants(int $userUid, string $usergroupList): void
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $groups = [];
        foreach ($this->get(BackendGroupRepository::class)->findAll() as $group) {
            $groups[$group->getUid()] = $group;
        }

        $granted = $this->get(GroupPermissionResolver::class)->resolve($usergroupList, $groups);

        self::assertSame(array_map(self::toInt(...), $backendUser->userGroupsUID), $granted->groupUids);
        foreach (self::MODULES as $module) {
            self::assertSame($backendUser->check('modules', $module), in_array($module, $granted->fields['groupMods'], true), $module);
        }
        $webmounts = $backendUser->getWebmounts();
        sort($webmounts);
        self::assertSame($webmounts, array_map(self::toInt(...), $granted->fields['db_mountpoints']));
        $options = $backendUser->getTSConfig()['options.'] ?? [];
        self::assertSame(is_array($options) ? ($options['probe'] ?? null) : null, $this->getLastProbe($granted));
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function getLastProbe(GrantedPermissions $granted): ?string
    {
        $probe = null;
        foreach ($granted->tsConfig as $tsConfig) {
            if (preg_match('/^options\.probe = (\d+)$/', $tsConfig, $matches) === 1) {
                $probe = $matches[1];
            }
        }
        return $probe;
    }
}
