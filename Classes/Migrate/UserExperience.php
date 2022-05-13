<?php

namespace Cretection\BeGroups\Migrate;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class controls the visibility of available fields from be_groups records.
 *
 * @link http://www.morphodo.com/
 * @author Michael Klapper <michael.klapper@morphodo.com>
 */
class UserExperience {

	static $ACCESS_TYPE_MAPPING = array (
			1 => 'subgroup_r',
			2 => 'subgroup_l',
			4 => 'subgroup_pa',
			5 => 'subgroup_fm',
			6 => 'subgroup_pm',
			7 => 'subgroup_ts',
			8 => 'subgroup_ws',
			9 => 'subgroup_cat',
		);

	/**
	 * Display the wizard form to choose a migration process to the new
	 * be_groups user interface.
	 *
	 * @return string
	 */
	public function wizardForm() {
		$step = GeneralUtility::_GP('step') ? GeneralUtility::_GP('step') : FALSE;
		$hideInListWizardStep = GeneralUtility::_GP('hideInListWizardStep') ? TRUE : FALSE;
		$subGroupWizardStep = GeneralUtility::_GP('subGroupWizardStep') ? TRUE : FALSE;
		$content = '';
		$formPanel = '<form method="POST">%s</form>';
		$controls = '<input type="hidden" name="step" value="1" />';
		$controls .= '<input type="checkbox" name="hideInListWizardStep" value="1" /> Activate "hide in list" checkbox for records != "META" or "Default all" (' . $this->hideInListWizardAffectsCount() . ' rows).<br />';
		$controls .= '<input type="checkbox" name="subGroupWizardStep" value="1" /> Split be_groups configuration into seperate fields (' . $this->subGroupWizardAffectsCount() . ' rows).<br />';
		$controls .= '<input type="submit" value="Submit" />';

		switch($step) {
			case 1:
				$processList = '<ol>%s</ol>';
				$listItem = '';
				$content .= '<h1>Following actions processed:</h1>';
				if ($hideInListWizardStep) {
					$this->setHideInListFlag();
					$listItem .= '<h2>hide in list wizard OK</h2>';
				}
				if ($subGroupWizardStep) {
					$updatedRecords = $this->subGroupWizard();
					$updateListItem = '';

					foreach ($updatedRecords as $record) {
						$updateListItem .= '<li>[' . $record['uid'] . '] ' . $record['title'] . '</li>';
					}

					$listItem .= sprintf('<h2>Subgroup wizard</h2><h3>Updated the following records:</h3><ul>%s</ul>', $updateListItem);
				}
				$content .= sprintf($processList, $listItem);
				break;
			default:
				$content = sprintf($formPanel, $controls);
				break;
		}

		return $content;
	}

	/**
	 * Update be_groups records and set the hide_in_list flag on
	 * those records who are not of type "META".
	 *
	 * @return void
	 */
	protected function setHideInListFlag() {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('be_groups', 'tx_begroups_kind NOT IN(0,3) AND deleted = 0', array('hide_in_lists' => 1));
	}

	/**
	 * Return the integer count of affected rows that will be
	 * updated during wizard action.
	 *
	 * @return integer
	 */
	protected function hideInListWizardAffectsCount() {
		return $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', 'be_groups', 'tx_begroups_kind NOT IN(0,3) and hide_in_lists = 0 AND deleted = 0');
	}

	/**
	 * Return the integer count of affected rows that will be
	 * updated during wizard action.
	 *
	 * @return integer
	 */
	protected function subGroupWizardAffectsCount() {
		return $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', 'be_groups', 'subgroup != \'\' AND deleted = 0 AND hidden = 0 AND deleted = 0');
	}

	/**
	 * 0 = all
	 * 1 = authorization + extensions
	 * 2 = language
	 * 3 = meta
	 * 4 = page access group
	 * 5 = starting point of files system
	 * 6 = starting point of page tree
	 * 7 = tsconfig
	 * 8 = workspace
	 *
	 * @return void
	 */
	public function subGroupWizard() {
		$updatedRecords = array();
			// select all relevant records to update
		$groupRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title,subgroup,tx_begroups_kind', 'be_groups', 'subgroup != \'\' AND deleted = 0');

		foreach ($groupRows as $group) {
			$subGroupRecordValues = array();
			$subGroupRecordValues = $this->getSubGroupValueArray($group['subgroup'], $subGroupRecordValues);

			if (!is_array($subGroupRecordValues)) {
				continue;
			}

				// final cleanup
			foreach ($subGroupRecordValues as $index => $value) {
				$subGroupRecordValues[$index] = GeneralUtility::uniqueList($value);
			}

			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('be_groups', 'uid=' . $group['uid'], $subGroupRecordValues);
			$updatedRecords[] = array(
				'uid' => $group['uid'],
				'title' => $group['title'],
			);
		}

		return $updatedRecords;
	}

	/**
	 * This method collects all referenced subgroups and prepare the data for sql update operation.
	 *
	 * Method is called recursive.
	 *
	 * @param string $recordUidList
	 * @param array $subGroupRecordValues
	 * @param bool $recursion
	 * @return mixed
	 */
	public function getSubGroupValueArray($recordUidList, $subGroupRecordValues, $recursion = FALSE) {
		$subGroupRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title,subgroup,tx_begroups_kind', 'be_groups', 'uid IN(' . trim($recordUidList, ',') . ') AND deleted = 0');

		foreach ($subGroupRecords as $record) {

				// group of type META detected
			if ($record['tx_begroups_kind'] == 3 && $record['subgroup'] != '' && $recursion === FALSE) {
				$subGroupRecordValues = $this->getSubGroupValueArray($record['subgroup'], $subGroupRecordValues, TRUE);
			}

			foreach (self::$ACCESS_TYPE_MAPPING as $kind => $fieldName ) {
				if ($record['tx_begroups_kind'] == $kind) {
					$subGroupRecordValues[$fieldName] .= $record['uid'] . ',';
				}
			}
		}

			// cleanup
		foreach ($subGroupRecordValues as $index => $value) {
			if ($index == 'subgroup') {
				continue;
			}
			$subGroupRecordValues[$index] = rtrim($value, ',') . ',';
			$subGroupRecordValues['subgroup'] .= $value . ',';
		}
		$subGroupRecordValues['subgroup'] = GeneralUtility::uniqueList($subGroupRecordValues['subgroup']) . ',';

		return $subGroupRecordValues;
	}
}
