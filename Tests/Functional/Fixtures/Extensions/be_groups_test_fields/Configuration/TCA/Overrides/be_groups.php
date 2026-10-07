<?php

declare(strict_types=1);

defined('TYPO3') or die();

$GLOBALS['TCA']['be_groups']['columns']['tx_test_sync_id'] = [
    'label' => 'Synchronisation identifier',
    'config' => [
        'type' => 'passthrough',
    ],
];
