<?php

declare(strict_types=1);

use Cretection\BeGroups\DataHandling\GroupKindRules;
use Cretection\BeGroups\Form\FormDataProvider\KindSelection;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowDefaultValues;
use TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca;

defined('TYPO3') or die();

// Enforces the rules of the group kind model on every DataHandler write (forms, imports, API).
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['be_groups']
    = GroupKindRules::class;

// Offers the kind "classic" in the group form only while classic groups are allowed.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][KindSelection::class] = [
    'depends' => [
        InitializeProcessedTca::class,
        DatabaseRowDefaultValues::class,
    ],
    'before' => [
        DatabaseRecordTypeValue::class,
    ],
];
