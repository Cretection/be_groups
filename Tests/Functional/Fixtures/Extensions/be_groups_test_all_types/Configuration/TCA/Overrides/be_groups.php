<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A permission field of an extension that does not know kinds and is loaded after this one, like
// the field "Permission sets" of b13/permission-sets: it is added to every type of be_groups.
$GLOBALS['TCA']['be_groups']['columns']['tx_test_all_types'] = [
    'label' => 'Permission of all types',
    'config' => [
        'type' => 'input',
    ],
];
ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'tx_test_all_types');
