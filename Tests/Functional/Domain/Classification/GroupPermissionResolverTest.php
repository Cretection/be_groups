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
use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The model of GroupPermissionResolver must match what TYPO3 itself grants, checked through
 * the public API of the backend user.
 */
#[CoversClass(GroupPermissionResolver::class)]
#[CoversClass(GrantedPermissions::class)]
final class GroupPermissionResolverTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    private const MODULES = ['web_layout', 'web_list', 'web_info', 'records'];

    protected array $coreExtensionsToLoad = ['workspaces'];

    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_tsconfig',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Resolution.csv');
        // The core only mounts folders that exist in a storage
        $this->getConnectionPool()->getConnectionForTable('sys_file_storage')->insert('sys_file_storage', [
            'uid' => 1,
            'pid' => 0,
            'name' => 'fileadmin',
            'driver' => 'Local',
            'configuration' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
                . '<field index="basePath"><value index="vDEF">fileadmin/</value></field>'
                . '<field index="pathType"><value index="vDEF">relative</value></field>'
                . '<field index="caseSensitive"><value index="vDEF">1</value></field>'
                . '</language></sheet></data></T3FlexForms>',
            'is_online' => 1,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_default' => 1,
        ]);
        foreach (['images', 'documents'] as $folder) {
            GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/fileadmin/' . $folder);
        }
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
            'included TSconfig file' => [8, '1,10'],
            'page mounts TYPO3 drops' => [9, '11'],
            'hidden flag other than 1' => [10, '1,12'],
            'file mounts TYPO3 drops' => [11, '13'],
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
        foreach (['pages', 'tt_content', 'sys_note'] as $table) {
            self::assertSame($backendUser->check('tables_select', $table), in_array($table, $granted->fields['tables_select'], true), $table);
        }
        $webmounts = $backendUser->getWebmounts();
        sort($webmounts);
        self::assertSame($webmounts, array_map(self::toInt(...), $granted->fields['db_mountpoints']));
        // The core adds the file mounts of a user to the storages in backend requests only
        $GLOBALS['TYPO3_REQUEST'] = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $fileMounts = [];
        foreach ($backendUser->getFileStorages() as $storage) {
            foreach (array_keys($storage->getFileMounts()) as $folderIdentifier) {
                $fileMounts[] = $storage->getUid() . ':' . $folderIdentifier;
            }
        }
        sort($fileMounts);
        $grantedFileMounts = array_map($this->getFileMountIdentifier(...), $granted->fields['file_mountpoints']);
        sort($grantedFileMounts);
        self::assertSame($fileMounts, $grantedFileMounts);
        $filePermissions = array_keys(array_filter($backendUser->getFilePermissions(), static fn(mixed $granted): bool => $granted === true));
        sort($filePermissions);
        $grantedFilePermissions = array_values(array_unique($granted->fields['file_permissions']));
        sort($grantedFilePermissions);
        self::assertSame($filePermissions, $grantedFilePermissions);
        $categoryMounts = array_values(array_map(static fn(mixed $uid): string => is_scalar($uid) ? (string)$uid : '', $backendUser->getCategoryMountPoints()));
        sort($categoryMounts);
        self::assertSame($categoryMounts, $granted->fields['category_perms']);
        // Live editing (workspace_perms bit 1) is the only workspace the users may have.
        self::assertSame(($granted->workspacePermissions & 1) === 1 ? 0 : -99, $backendUser->workspace);
        $options = $backendUser->getTSConfig()['options.'] ?? [];
        self::assertSame(is_array($options) ? ($options['probe'] ?? null) : null, $this->getLastProbe($granted));
    }

    private function getFileMountIdentifier(string $uid): string
    {
        $identifier = $this->getConnectionPool()->getConnectionForTable('sys_filemounts')
            ->select(['identifier'], 'sys_filemounts', ['uid' => (int)$uid])
            ->fetchOne();
        return is_string($identifier) ? $identifier : 'unknown file mount ' . $uid;
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    #[Test]
    #[DataProvider('userDataProvider')]
    public function hasTheOwnerGroupOfNewPagesThatTypo3Uses(int $userUid, string $usergroupList): void
    {
        // Administrators may create pages anywhere; their groups are resolved all the same.
        $this->getConnectionPool()->getConnectionForTable('be_users')->update('be_users', ['admin' => 1], ['uid' => $userUid]);
        $this->setUpBackendUser($userUid);
        $groups = [];
        foreach ($this->get(BackendGroupRepository::class)->findAll() as $group) {
            $groups[$group->getUid()] = $group;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => ['NEW1' => ['pid' => 0, 'title' => 'New page']]], []);
        $dataHandler->process_datamap();

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        // New pages are hidden.
        $queryBuilder->getRestrictions()->removeAll();
        $page = $queryBuilder->select('perms_groupid')->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($dataHandler->substNEWwithIDs['NEW1'] ?? 0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($page);
        self::assertSame(
            $this->get(GroupPermissionResolver::class)->resolve($usergroupList, $groups)->firstGroupUid,
            self::toInt($page['perms_groupid']),
        );
    }

    private function getLastProbe(GrantedPermissions $granted): ?string
    {
        $probe = null;
        foreach ($granted->tsConfig as $tsConfig) {
            $content = str_starts_with($tsConfig, 'include: ')
                ? (string)file_get_contents(GeneralUtility::getFileAbsFileName(substr($tsConfig, 9)))
                : $tsConfig;
            if (preg_match('/^options\.probe = (\w+)$/m', $content, $matches) === 1) {
                $probe = $matches[1];
            }
        }
        return $probe;
    }
}
