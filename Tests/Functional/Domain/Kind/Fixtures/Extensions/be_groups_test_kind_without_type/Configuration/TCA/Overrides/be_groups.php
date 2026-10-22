<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// An item of the kind selector without a type (form) of its own, e.g. a forgotten types entry
ExtensionManagementUtility::addTcaSelectItem('be_groups', 'tx_begroups_kind', [
    'label' => 'Kind without type',
    'value' => 'my_orphan',
]);
