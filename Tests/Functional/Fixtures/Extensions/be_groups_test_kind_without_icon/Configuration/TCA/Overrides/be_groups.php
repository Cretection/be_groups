<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A kind of another extension with a form of its own, but without an icon
ExtensionManagementUtility::addTcaSelectItem('be_groups', 'tx_begroups_kind', [
    'label' => 'Kind without icon',
    'value' => 'my_plain',
]);
$GLOBALS['TCA']['be_groups']['types']['my_plain'] = [
    'showitem' => '--div--;core.form.tabs:general, title, tx_begroups_kind, --div--;core.form.tabs:access, hidden',
];
