<?php
if (!defined ('TYPO3_MODE')) die('Access denied.');

$configurationArray = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['be_groups']);
if (is_array($configurationArray) && array_key_exists('explicitAllow', $configurationArray) && $configurationArray['explicitAllow'] == 1) {
	$GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] = 'explicitAllow';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Cretection\BeGroups\Service\TceMain\ProcessFieldArray::class;