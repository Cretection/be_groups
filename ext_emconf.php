<?php
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

$EM_CONF[$_EXTKEY] = array(
  'title' => 'Improved backend groups management interface.',
  'description' => 'This extension provide several new options to restructure large amount of be_groups records.',
  'category' => 'be',
  'author_company' => 'Cretection',
  'author' => 'Jonathan Starck',
  'author_email' => 'info@cretection.it',
  'state' => 'beta',
  'uploadfolder' => false,
  'createDirs' => '',
  'clearCacheOnLoad' => 1,
  'version' => '0.0.4',
  'constraints' => array(
      'depends' => array(
          'php' => '7.2.0-8.2.99',
          'typo3' => '11.4.0-11.5.99',
      ),
      'conflicts' => array(
      ),
      'suggests' => array(
      ),
  ),
);