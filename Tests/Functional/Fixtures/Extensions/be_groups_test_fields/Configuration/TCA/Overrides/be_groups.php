<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$GLOBALS['TCA']['be_groups']['columns']['tx_test_sync_id'] = [
    'label' => 'Synchronisation identifier',
    'config' => [
        'type' => 'passthrough',
    ],
];

// A permission field of another extension that only the form of classic groups shows
$GLOBALS['TCA']['be_groups']['columns']['tx_test_permission'] = [
    'label' => 'Permission of another extension',
    'config' => [
        'type' => 'input',
    ],
];
ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'tx_test_permission', 'classic');
