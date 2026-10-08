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

// Creates the roles, building blocks and users the end-to-end tests work with, through the
// DataHandler like an administrator: php Build/tests/playwright/scenario.php <instance path>
// Called by "Build/Scripts/setupE2E.sh" on a fresh instance. The same scenario is shown in the
// screenshots of the manual.

use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

$instancePath = rtrim($argv[1] ?? '', '/');
if ($instancePath === '' || !is_file($instancePath . '/vendor/autoload.php')) {
    fwrite(STDERR, 'Usage: php Build/tests/playwright/scenario.php <path of the TYPO3 instance>' . chr(10));
    exit(2);
}
chdir($instancePath);
$classLoader = require $instancePath . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($classLoader);
Bootstrap::initializeBackendUser(CommandLineUserAuthentication::class);
Bootstrap::initializeBackendAuthentication();

$connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
$insert = static function (string $table, array $row) use ($connectionPool): int {
    $connection = $connectionPool->getConnectionForTable($table);
    $connection->insert($table, $row);
    return (int)$connection->lastInsertId();
};

// Records the kinds point to. A page for the page tree mount, as "typo3 setup" creates none.
$website = $insert('pages', ['pid' => 0, 'title' => 'Website', 'doktype' => 1, 'is_siteroot' => 1, 'slug' => '/']);
$images = $insert('sys_filemounts', ['pid' => 0, 'title' => 'Images', 'identifier' => '1:/user_upload/images/']);
$documents = $insert('sys_filemounts', ['pid' => 0, 'title' => 'Documents', 'identifier' => '1:/user_upload/documents/']);
$products = $insert('sys_category', ['pid' => 0, 'title' => 'Products']);

$descriptions = [
    'Content editing' => 'Page and content modules with the content elements of the website.',
    'Page properties' => 'Page fields that editors may change.',
    'Media' => 'File list and file metadata.',
    'Website' => 'The page tree of the website.',
    'Images' => 'The image folder.',
    'Documents' => 'The document folder.',
    'Upload and edit files' => 'Upload, edit and rename files, without deleting them.',
    'English' => 'Content in the default language.',
    'Clear page cache' => 'Lets editors clear the page cache.',
    'Editor defaults' => 'Settings of the backend for editors.',
    'Marketing pages' => 'Owner group of the pages of the marketing team.',
    'Products' => 'Product categories.',
    'Drafts' => 'Edits the live workspace.',
    'Content manager' => 'Edits content, pages and files of the website.',
    'Marketing' => 'Edits campaign pages and product categories.',
    'Team lead' => 'Contains the role "Content manager", as an assistant leaves nested groups.',
    'Legacy newsletter' => 'Classic group of the former newsletter setup.',
];
$group = static fn(string $title, string $kind, array $fields = []): array
    => ['pid' => 0, 'title' => $title, 'description' => $descriptions[$title] ?? '', 'tx_begroups_kind' => $kind] + $fields;
$password = static fn(): string => bin2hex(random_bytes(16)) . 'Aa1!';
// TYPO3 reads "table_uid" in relation fields, so placeholders must not contain underscores
$datamap = [
    'be_groups' => [
        'NEWaclContent' => $group('Content editing', 'acl', [
            'groupMods' => 'web_layout,records,page_preview',
            'tables_select' => 'pages,tt_content,sys_file_reference',
            'tables_modify' => 'pages,tt_content,sys_file_reference',
            'pagetypes_select' => '1,4,254',
            'explicit_allowdeny' => 'tt_content:CType:text,tt_content:CType:textmedia,tt_content:CType:image',
        ]),
        'NEWaclPages' => $group('Page properties', 'acl', [
            'non_exclude_fields' => 'pages:nav_hide,pages:hidden,pages:starttime,pages:endtime',
        ]),
        'NEWaclMedia' => $group('Media', 'acl', [
            'groupMods' => 'media_management',
            'tables_select' => 'sys_file,sys_file_metadata',
            'tables_modify' => 'sys_file_metadata',
        ]),
        'NEWdbm' => $group('Website', 'db_mount', ['db_mountpoints' => (string)$website]),
        'NEWfmImages' => $group('Images', 'file_mount', ['file_mountpoints' => (string)$images]),
        'NEWfmDocuments' => $group('Documents', 'file_mount', ['file_mountpoints' => (string)$documents]),
        'NEWfo' => $group('Upload and edit files', 'file_operations', [
            'file_permissions' => 'readFolder,readFile,writeFile,addFile,renameFile,replaceFile',
        ]),
        'NEWlanguage' => $group('English', 'language', ['allowed_languages' => '0']),
        'NEWts' => $group('Clear page cache', 'tsconfig', ['TSconfig' => 'options.clearCache.pages = 1']),
        'NEWtsEditor' => $group('Editor defaults', 'tsconfig', ['TSconfig' => 'options.pageTree.showPageIdWithTitle = 1']),
        'NEWpg' => $group('Marketing pages', 'page_group'),
        'NEWcm' => $group('Products', 'category_mount', ['category_perms' => (string)$products]),
        'NEWws' => $group('Drafts', 'workspace', ['workspace_perms' => 1]),
        'NEWroleContent' => $group('Content manager', 'role', [
            'subgroup' => 'NEWaclContent,NEWaclPages,NEWaclMedia,NEWdbm,NEWfmImages,NEWfmDocuments,NEWfo,NEWlanguage,NEWts,NEWtsEditor',
        ]),
        'NEWroleMarketing' => $group('Marketing', 'role', [
            'subgroup' => 'NEWpg,NEWaclContent,NEWdbm,NEWfmImages,NEWfo,NEWcm,NEWws',
        ]),
        'NEWroleLead' => $group('Team lead', 'role', ['subgroup' => 'NEWaclPages,NEWts']),
        'NEWclassic' => $group('Legacy newsletter', 'classic', ['groupMods' => 'web_layout', 'db_mountpoints' => (string)$website]),
    ],
    'be_users' => [
        'NEWanna' => ['pid' => 0, 'username' => 'anna', 'disable' => 0, 'realName' => 'Anna Schmidt', 'password' => $password(), 'usergroup' => 'NEWroleContent'],
        'NEWben' => ['pid' => 0, 'username' => 'ben', 'disable' => 0, 'realName' => 'Ben Weber', 'password' => $password(), 'usergroup' => 'NEWroleMarketing'],
        'NEWcarla' => ['pid' => 0, 'username' => 'carla', 'disable' => 0, 'realName' => 'Carla Becker', 'password' => $password(), 'usergroup' => 'NEWroleLead'],
        'NEWdan' => ['pid' => 0, 'username' => 'dan', 'disable' => 1, 'realName' => 'Dan Fischer', 'password' => $password(), 'usergroup' => 'NEWclassic'],
    ],
];
$dataHandler = GeneralUtility::makeInstance(DataHandler::class);
$dataHandler->start($datamap, []);
$dataHandler->process_datamap();
if ($dataHandler->errorLog !== []) {
    fwrite(STDERR, implode(chr(10), $dataHandler->errorLog) . chr(10));
    exit(1);
}

// A role that contains another role, as the assistants leave nested groups after splitting. The
// rules reject adding it, so the stored state is written directly.
$teamLead = $dataHandler->substNEWwithIDs['NEWroleLead'];
$contentManager = $dataHandler->substNEWwithIDs['NEWroleContent'];
$connection = $connectionPool->getConnectionForTable('be_groups');
$subgroups = (string)$connection->select(['subgroup'], 'be_groups', ['uid' => $teamLead])->fetchOne();
$connection->update('be_groups', ['subgroup' => $contentManager . ',' . $subgroups], ['uid' => $teamLead]);

echo sprintf('Created %d groups and %d users.', count($datamap['be_groups']), count($datamap['be_users'])) . chr(10);
