<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A permission field of another extension that the forms of classic groups, roles and access rights show
$GLOBALS['TCA']['be_groups']['columns']['tx_test_role_permission'] = [
    'label' => 'Permission of another extension',
    'config' => [
        'type' => 'input',
    ],
];
ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'tx_test_role_permission', 'classic,role,acl');
