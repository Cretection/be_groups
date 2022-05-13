<?php

namespace Cretection\BeGroups\Service\TceMain;

/***************************************************************
 *  Copyright notice
 * 
 *  (c) 2022 Jonathan Starck <info@cretection.it>
 *
 *  Originally
 *  (c) 2012 Michael Klapper <michael.klapper@morphodo.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Cretection\BeGroups\Migrate\UserExperience;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This class controls the visibility of available fields from be_groups records.
 *
 * @link http://www.morphodo.com/
 * @author Michael Klapper <michael.klapper@morphodo.com>
 */
class ProcessFieldArray {

	/**
	 * @var array
	 */
	private $setIncludeListFlag = array (
		0 => null,
		1 => true,
		2 => true,
		3 => false,
		4 => false,
		5 => false,
		6 => false,
		7 => false,
		8 => false,
		9 => true,
	);

	/**
	 * Update inc_access_lists value if the table is "be_groups"
	 *
	 * @param array $incomingFieldArray Current record
	 * @param string $table Database table of current record
	 * @param integer $id Uid of current record
	 * @param DataHandler $parentObj
	 *
	 * @return string
	 */
	public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, $parentObj) {
		if ($table == 'be_groups') {
			$recordBefore = BackendUtility::getRecord('be_groups', $id, 'tx_begroups_kind,subgroup');

			/* $this->resetHiddenFields($incomingFieldArray, $id); */
			if ($recordBefore['tx_begroups_kind'] !== $incomingFieldArray['tx_begroups_kind']) {
				$this->setHideInListFlagIfTypeIsNotMeta($incomingFieldArray);
			}
			$this->setIncludeAccessListFlag($incomingFieldArray);

			// change type from "default" to "meta"
			if ($recordBefore['tx_begroups_kind'] === 0 && $incomingFieldArray['tx_begroups_kind'] === "3") {
				$subGroupRecordValues = array();
				if (is_null($incomingFieldArray['subgroup'])) {
					unset($incomingFieldArray['subgroup']);
					$subGroupIdList = $recordBefore['subgroup'];
				} else {
					$subGroupIdList = $this->getIdListFromArray($incomingFieldArray['subgroup']);
				}
				if ($subGroupIdList != '') {
					/* @var $userExperience UserExperience */
					$userExperience = GeneralUtility::makeInstance(UserExperience::class);
					$subGroupRecordValues = $userExperience->getSubGroupValueArray($subGroupIdList, $subGroupRecordValues);

					// final cleanup
					foreach (UserExperience::$ACCESS_TYPE_MAPPING as $index ) {
						if (array_key_exists($index, $subGroupRecordValues)) {
							$incomingFieldArray[$index] = explode(',', GeneralUtility::uniqueList($subGroupRecordValues[$index]));
						} else {
							$incomingFieldArray[$index] = NULL;
						}
					}
				}

			} elseif ($recordBefore['tx_begroups_kind'] === 3 && $incomingFieldArray['tx_begroups_kind'] === "3") {
				$this->mergeSubgroups($incomingFieldArray);
			}
		}
	}

	/**
	 * Build comma seperated list of IDs.
	 *
	 * @param array $fieldValue
	 * @return string
	 */
	protected function getIdListFromArray($fieldValue) {
		$subgroupList = '';

		if (is_array($fieldValue)) {
				// fix expected structure
			foreach ($fieldValue as $key => $value) {
				$subgroupList .= $value . ',';
			}
		}

		return trim($subgroupList, ',');
	}

	/**
	 * Merge all updates from "subgroup_*" fields back into the original "subgroup" field.
	 *
	 * @param array $incomingFieldArray
	 * @return void
	 */
	protected function mergeSubgroups(&$incomingFieldArray) {
		$selectedList = array();
		$subgroupList = array();
		$fieldListToMerge = array('subgroup_fm', 'subgroup_pm', 'subgroup_ws', 'subgroup_r', 'subgroup_pa', 'subgroup_ts', 'subgroup_l','subgroup_cat');

		foreach ($fieldListToMerge as $fieldName) {
			if (is_array($incomingFieldArray[$fieldName])) {
				$selectedList = $selectedList + array_flip($incomingFieldArray[$fieldName]);
			}
		}

		// fix expected structure
		foreach ($selectedList as $key => $value) {
			$subgroupList[] = $key;
		}

		$incomingFieldArray['subgroup'] = $subgroupList;
	}

	/**
	 * Reset all fields except the relevant for the current selected view.
	 *
	 * @param array $incomingFieldArray
	 * @param integer $id
	 * @return void
	 */
	/* 	protected function resetHiddenFields(&$incomingFieldArray, $id) {
		if (! is_null($this->setIncludeListFlag[$incomingFieldArray['tx_begroups_kind']]) ) {
			$fieldsToKeepArray = array_keys(BackendUtility::getTCAtypes('be_groups', $incomingFieldArray, 1));

			foreach ($incomingFieldArray as $column => $value) {
				if (! in_array($column, $fieldsToKeepArray) && (MathUtility::canBeInterpretedAsInteger($id) === true) ) {
					$incomingFieldArray[$column] = null;
				}
			}
		}
	} */

	/**
	 * Include the access list based on the defined matrix in member
	 * \AOE\BeGroups\Service\TceMain\ProcessFieldArray::$setIncludeListFlag
	 *
	 * @param array $incomingFieldArray
	 * @return void
	 */
	protected function setIncludeAccessListFlag(&$incomingFieldArray) {
		// update include access list flag
		if ($this->setIncludeListFlag[$incomingFieldArray['tx_begroups_kind']] === true) {
			$incomingFieldArray['inc_access_lists'] = 1;
		} elseif ($this->setIncludeListFlag[$incomingFieldArray['tx_begroups_kind']] === false) {
			$incomingFieldArray['inc_access_lists'] = 0;
		}
	}

	/**
	 * Be sure that the hide_in_list flag is always set to the correct
	 * value if the tx_begroups_kind is changed.
	 *
	 * @param array $incomingFieldArray
	 * @return void
	 */
	protected function setHideInListFlagIfTypeIsNotMeta(&$incomingFieldArray) {
		if ($incomingFieldArray['tx_begroups_kind'] == 3 || $incomingFieldArray['tx_begroups_kind'] == 0) {
			$incomingFieldArray['hide_in_lists'] = 0;
			$this->addFlashMessageNotice('Update "Hide in list" option to inactive state', 'This group record will be shown in be_user records to select as "Group".');
		} else {
			$incomingFieldArray['hide_in_lists'] = 1;
			$this->addFlashMessageNotice('Update "Hide in list" option to active state.', 'This group record will not be visible in be_user records and cannot be selected as "Group".');
		}
	}

	/**
	 * Add flash message of type notice to the backend user interface.
	 *
	 * @param string $title
	 * @param string $message
	 * @return void
	 */
	private function addFlashMessageNotice($title, $message) {
		/** @var FlashMessage $flashMessage */
		$flashMessage = GeneralUtility::makeInstance(FlashMessage::class,
			$title,
			$message,
			FlashMessage::INFO,
			TRUE
		);

		/** @var FlashMessageService $flashMessageService */
		$flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
		$messageQueue = $flashMessageService->getMessageQueueByIdentifier();
		$messageQueue->addMessage($flashMessage);
	}
}
