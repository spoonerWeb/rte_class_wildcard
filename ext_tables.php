<?php
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

defined('TYPO3_MODE') or die('Access denied.');

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$boot = function ($packageKey) {

	$extConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['rte_class_wildcard']);

	if ($extConfig['useFontAwesome']) {
		ExtensionManagementUtility::addPageTSConfig(
			'<INCLUDE_TYPOSCRIPT: source="FILE:EXT:rte_class_wildcard/Configuration/TypoScript/FontAwesome.ts">'
		);
	}

	if ($extConfig['useGlyphicons']) {
		ExtensionManagementUtility::addPageTSConfig(
			'<INCLUDE_TYPOSCRIPT: source="FILE:EXT:rte_class_wildcard/Configuration/TypoScript/Glyphicons.ts">'
		);
	}
};


$boot($_EXTKEY);
unset($boot);