<?php
namespace SpoonerWeb\RteClassWildcard\Html;

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

use TYPO3\CMS\Core\Html\RteHtmlParser as OriginalRteHtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RteHtmlParser extends OriginalRteHtmlParser {

	/**
	 * Function that can clean up HTML content according to configuration given in the $tags array.
	 *
	 * Initializing the $tags array to allow a list of tags (in this case <B>,<I>,<U> and <A>), set it like this:         $tags = array_flip(explode(',','b,a,i,u'))
	 * If the value of the $tags[$tagname] entry is an array, advanced processing of the tags is initialized. These are the options:
	 *
	 * $tags[$tagname] = Array(
	 * 'overrideAttribs' => ''        If set, this string is preset as the attributes of the tag
	 * 'allowedAttribs' =>   '0' (zero) = no attributes allowed, '[commalist of attributes]' = only allowed attributes. If blank, all attributes are allowed.
	 * 'fixAttrib' => Array(
	 * '[attribute name]' => Array (
	 * 'set' => Force the attribute value to this value.
	 * 'unset' => Boolean: If set, the attribute is unset.
	 * 'default' =>     If no attribute exists by this name, this value is set as default value (if this value is not blank)
	 * 'always' =>     Boolean. If set, the attribute is always processed. Normally an attribute is processed only if it exists
	 * 'trim,intval,lower,upper' =>     All booleans. If any of these keys are set, the value is passed through the respective PHP-functions.
	 * 'range' => Array ('[low limit]','[high limit, optional]')        Setting integer range.
	 * 'list' => Array ('[value1/default]','[value2]','[value3]')        Attribute must be in this list. If not, the value is set to the first element.
	 * 'removeIfFalse' =>     Boolean/'blank'.    If set, then the attribute is removed if it is 'FALSE'. If this value is set to 'blank' then the value must be a blank string (that means a 'zero' value will not be removed)
	 * 'removeIfEquals' =>     [value]    If the attribute value matches the value set here, then it is removed.
	 * 'casesensitiveComp' => 1    If set, then the removeIfEquals and list comparisons will be case sensitive. Otherwise not.
	 * )
	 * ),
	 * 'protect' => '',    Boolean. If set, the tag <> is converted to &lt; and &gt;
	 * 'remap' => '',        String. If set, the tagname is remapped to this tagname
	 * 'rmTagIfNoAttrib' => '',    Boolean. If set, then the tag is removed if no attributes happend to be there.
	 * 'nesting' => '',    Boolean/'global'. If set TRUE, then this tag must have starting and ending tags in the correct order. Any tags not in this order will be discarded. Thus '</B><B><I></B></I></B>' will be converted to '<B><I></B></I>'. Is the value 'global' then true nesting in relation to other tags marked for 'global' nesting control is preserved. This means that if <B> and <I> are set for global nesting then this string '</B><B><I></B></I></B>' is converted to '<B></B>'
	 * )
	 *
	 * @param string $content Is the HTML-content being processed. This is also the result being returned.
	 * @param array $tags Is an array where each key is a tagname in lowercase. Only tags present as keys in this array are preserved. The value of the key can be an array with a vast number of options to configure.
	 * @param string $keepAll Boolean/'protect', if set, then all tags are kept regardless of tags present as keys in $tags-array. If 'protect' then the preserved tags have their <> converted to &lt; and &gt;
	 * @param int $hSC Values -1,0,1,2: Set to zero= disabled, set to 1 then the content BETWEEN tags is htmlspecialchar()'ed, set to -1 its the opposite and set to 2 the content will be HSC'ed BUT with preservation for real entities (eg. "&amp;" or "&#234;")
	 * @param array $addConfig Configuration array send along as $conf to the internal functions ->processContent() and ->processTag()
	 * @return string Processed HTML content
	 */
	public function HTMLcleaner($content, $tags = [], $keepAll = 0, $hSC = 0, $addConfig = []) {
		$newContent = [];
		$tokArr = explode('<', $content);
		$newContent[] = $this->processContent(current($tokArr), $hSC, $addConfig);
		// We skip the first element in foreach loop
		$tokArrSliced = array_slice($tokArr, 1, NULL, TRUE);
		$c = 1;
		$tagRegister = [];
		$tagStack = [];
		$inComment = FALSE;
		$inCdata = FALSE;
		$skipTag = FALSE;
		foreach ($tokArrSliced as $tok) {
			if ($inComment) {
				if (($eocPos = strpos($tok, '-->')) === FALSE) {
					// End of comment is not found in the token. Go further until end of comment is found in other tokens.
					$newContent[$c++] = '<' . $tok;
					continue;
				}
				// Comment ends in the middle of the token: add comment and proceed with rest of the token
				$newContent[$c++] = '<' . substr($tok, 0, ($eocPos + 3));
				$tok = substr($tok, $eocPos + 3);
				$inComment = FALSE;
				$skipTag = TRUE;
			} elseif ($inCdata) {
				if (($eocPos = strpos($tok, '/*]]>*/')) === FALSE) {
					// End of comment is not found in the token. Go futher until end of comment is found in other tokens.
					$newContent[$c++] = '<' . $tok;
					continue;
				}
				// Comment ends in the middle of the token: add comment and proceed with rest of the token
				$newContent[$c++] = '<' . substr($tok, 0, $eocPos + 10);
				$tok = substr($tok, $eocPos + 10);
				$inCdata = FALSE;
				$skipTag = TRUE;
			} elseif (substr($tok, 0, 3) == '!--') {
				if (($eocPos = strpos($tok, '-->')) === FALSE) {
					// Comment started in this token but it does end in the same token. Set a flag to skip till the end of comment
					$newContent[$c++] = '<' . $tok;
					$inComment = TRUE;
					continue;
				}
				// Start and end of comment are both in the current token. Add comment and proceed with rest of the token
				$newContent[$c++] = '<' . substr($tok, 0, ($eocPos + 3));
				$tok = substr($tok, $eocPos + 3);
				$skipTag = TRUE;
			} elseif (substr($tok, 0, 10) === '![CDATA[*/') {
				if (($eocPos = strpos($tok, '/*]]>*/')) === FALSE) {
					// Comment started in this token but it does end in the same token. Set a flag to skip till the end of comment
					$newContent[$c++] = '<' . $tok;
					$inCdata = TRUE;
					continue;
				}
				// Start and end of comment are both in the current token. Add comment and proceed with rest of the token
				$newContent[$c++] = '<' . substr($tok, 0, $eocPos + 10);
				$tok = substr($tok, $eocPos + 10);
				$skipTag = TRUE;
			}
			$firstChar = $tok[0];
			// It is a tag... (first char is a-z0-9 or /) (fixed 19/01 2004). This also avoids triggering on <?xml..> and <!DOCTYPE..>
			if (!$skipTag && preg_match('/[[:alnum:]\\/]/', $firstChar) == 1) {
				$tagEnd = strpos($tok, '>');
				// If there is and end-bracket...	tagEnd can't be 0 as the first character can't be a >
				if ($tagEnd) {
					$endTag = $firstChar == '/' ? 1 : 0;
					$tagContent = substr($tok, $endTag, $tagEnd - $endTag);
					$tagParts = preg_split('/\\s+/s', $tagContent, 2);
					$tagName = strtolower($tagParts[0]);
					$emptyTag = 0;
					if (isset($tags[$tagName])) {
						// If there is processing to do for the tag:
						if (is_array($tags[$tagName])) {
							if (preg_match('/^(' . self::VOID_ELEMENTS . ' )$/i', $tagName)) {
								$emptyTag = 1;
							}
							// If NOT an endtag, do attribute processing (added dec. 2003)
							if (!$endTag) {
								// Override attributes
								if ((string)$tags[$tagName]['overrideAttribs'] !== '') {
									$tagParts[1] = $tags[$tagName]['overrideAttribs'];
								}
								// Allowed tags
								if ((string)$tags[$tagName]['allowedAttribs'] !== '') {
									// No attribs allowed
									if ((string)$tags[$tagName]['allowedAttribs'] === '0') {
										$tagParts[1] = '';
									} elseif (trim($tagParts[1])) {
										$tagAttrib = $this->get_tag_attributes($tagParts[1]);
										$tagParts[1] = '';
										$newTagAttrib = [];
										if (!($tList = $tags[$tagName]['_allowedAttribs'])) {
											// Just explode attribts for tag once
											$tList = ($tags[$tagName]['_allowedAttribs'] = GeneralUtility::trimExplode(
												',', strtolower($tags[$tagName]['allowedAttribs']), TRUE
											));
										}
										foreach ($tList as $allowTag) {
											if (isset($tagAttrib[0][$allowTag])) {
												$newTagAttrib[$allowTag] = $tagAttrib[0][$allowTag];
											}
										}
										$tagParts[1] = $this->compileTagAttribs($newTagAttrib, $tagAttrib[1]);
									}
								}
								// Fixed attrib values
								if (is_array($tags[$tagName]['fixAttrib'])) {
									$tagAttrib = $this->get_tag_attributes($tagParts[1]);
									$tagParts[1] = '';
									foreach ($tags[$tagName]['fixAttrib'] as $attr => $params) {
										if (isset($params['set']) && $params['set'] !== '') {
											$tagAttrib[0][$attr] = $params['set'];
										}
										if (!empty($params['unset'])) {
											unset($tagAttrib[0][$attr]);
										}
										if (!isset($tagAttrib[0][$attr]) && (string)$params['default'] !== '') {
											$tagAttrib[0][$attr] = $params['default'];
										}
										if ($params['always'] || isset($tagAttrib[0][$attr])) {
											if ($params['trim']) {
												$tagAttrib[0][$attr] = trim($tagAttrib[0][$attr]);
											}
											if ($params['intval']) {
												$tagAttrib[0][$attr] = (int)$tagAttrib[0][$attr];
											}
											if ($params['lower']) {
												$tagAttrib[0][$attr] = strtolower($tagAttrib[0][$attr]);
											}
											if ($params['upper']) {
												$tagAttrib[0][$attr] = strtoupper($tagAttrib[0][$attr]);
											}
											if ($params['range']) {
												if (isset($params['range'][1])) {
													$tagAttrib[0][$attr] = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(
														$tagAttrib[0][$attr], (int)$params['range'][0], (int)$params['range'][1]
													);
												} else {
													$tagAttrib[0][$attr] = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange(
														$tagAttrib[0][$attr], (int)$params['range'][0]
													);
												}
											}
											if (is_array($params['list'])) {
												// For the class attribute, remove from the attribute value any class not in the list
												// Classes are case sensitive
												if ($attr == 'class') {
													$newClasses = [];
													$classes = GeneralUtility::trimExplode(' ', $tagAttrib[0][$attr], TRUE);
													foreach ($classes as $class) {
														if (in_array($class, $params['list'])) {
															$newClasses[] = $class;
														} else {
															foreach ($this->allowedClasses as $allowedClass) {
																if (strpos($allowedClass, '*') !== FALSE) {
																	$regexAllowedClass = '/' . str_replace(
																			'*', '.*', $allowedClass
																		) . '/';
																	if (preg_match($regexAllowedClass, $class) !== FALSE) {
																		$newClasses[] = $class;
																	}
																}
															}
														}
													}
													if (!empty($newClasses)) {
														$tagAttrib[0][$attr] = implode(' ', $newClasses);
													} else {
														$tagAttrib[0][$attr] = $params['list'][0];
													}
												} else {
													if (!in_array(
														$this->caseShift(
															$tagAttrib[0][$attr], $params['casesensitiveComp']
														),
														$this->caseShift($params['list'], $params['casesensitiveComp'], $tagName)
													)
													) {
														$tagAttrib[0][$attr] = $params['list'][0];
													}
												}
											}
											if ($params['removeIfFalse'] && $params['removeIfFalse'] != 'blank' && !$tagAttrib[0][$attr] || $params['removeIfFalse'] == 'blank' && (string)$tagAttrib[0][$attr] === '') {
												unset($tagAttrib[0][$attr]);
											}
											if ((string)$params['removeIfEquals'] !== '' && $this->caseShift(
													$tagAttrib[0][$attr], $params['casesensitiveComp']
												) === $this->caseShift(
													$params['removeIfEquals'], $params['casesensitiveComp']
												)
											) {
												unset($tagAttrib[0][$attr]);
											}
											if ($params['prefixLocalAnchors']) {
												if ($tagAttrib[0][$attr][0] === '#') {
													if ($params['prefixLocalAnchors'] == 2) {
														/** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObjectRenderer */
														$contentObjectRenderer = GeneralUtility::makeInstance(
															\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class
														);
														$prefix = $contentObjectRenderer->getUrlToCurrentLocation();
													} else {
														$prefix = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
													}
													$tagAttrib[0][$attr] = $prefix . $tagAttrib[0][$attr];
												}
											}
											if ($params['prefixRelPathWith']) {
												$urlParts = parse_url($tagAttrib[0][$attr]);
												if (!$urlParts['scheme'] && $urlParts['path'][0] !== '/') {
													// If it is NOT an absolute URL (by http: or starting "/")
													$tagAttrib[0][$attr] = $params['prefixRelPathWith'] . $tagAttrib[0][$attr];
												}
											}
											if ($params['userFunc']) {
												if (is_array($params['userFunc.'])) {
													$params['userFunc.']['attributeValue'] = $tagAttrib[0][$attr];
												} else {
													$params['userFunc.'] = $tagAttrib[0][$attr];
												}
												$tagAttrib[0][$attr] = GeneralUtility::callUserFunction(
													$params['userFunc'], $params['userFunc.'], $this
												);
											}
										}
									}
									$tagParts[1] = $this->compileTagAttribs($tagAttrib[0], $tagAttrib[1]);
								}
							} else {
								// If endTag, remove any possible attributes:
								$tagParts[1] = '';
							}
							// Protecting the tag by converting < and > to &lt; and &gt; ??
							if ($tags[$tagName]['protect']) {
								$lt = '&lt;';
								$gt = '&gt;';
							} else {
								$lt = '<';
								$gt = '>';
							}
							// Remapping tag name?
							if ($tags[$tagName]['remap']) {
								$tagParts[0] = $tags[$tagName]['remap'];
							}
							// rmTagIfNoAttrib
							if ($endTag || trim($tagParts[1]) || !$tags[$tagName]['rmTagIfNoAttrib']) {
								$setTag = 1;
								// Remove this closing tag if $tagName was among $TSconfig['removeTags']
								if ($endTag && $tags[$tagName]['allowedAttribs'] === 0 && $tags[$tagName]['rmTagIfNoAttrib'] === 1) {
									$setTag = 0;
								}
								if ($tags[$tagName]['nesting']) {
									if (!is_array($tagRegister[$tagName])) {
										$tagRegister[$tagName] = [];
									}
									if ($endTag) {
										$correctTag = 1;
										if ($tags[$tagName]['nesting'] == 'global') {
											$lastEl = end($tagStack);
											if ($tagName !== $lastEl) {
												if (in_array($tagName, $tagStack)) {
													while (!empty($tagStack) && $tagName !== $lastEl) {
														$elPos = end($tagRegister[$lastEl]);
														unset($newContent[$elPos]);
														array_pop($tagRegister[$lastEl]);
														array_pop($tagStack);
														$lastEl = end($tagStack);
													}
												} else {
													// In this case the
													$correctTag = 0;
												}
											}
										}
										if (empty($tagRegister[$tagName]) || !$correctTag) {
											$setTag = 0;
										} else {
											array_pop($tagRegister[$tagName]);
											if ($tags[$tagName]['nesting'] == 'global') {
												array_pop($tagStack);
											}
										}
									} else {
										array_push($tagRegister[$tagName], $c);
										if ($tags[$tagName]['nesting'] == 'global') {
											array_push($tagStack, $tagName);
										}
									}
								}
								if ($setTag) {
									// Setting the tag
									$newContent[$c++] = $this->processTag(
										$lt . ($endTag ? '/' : '') . trim(
											$tagParts[0] . ' ' . $tagParts[1]
										) . ($emptyTag ? ' /' : '') . $gt, $addConfig, $endTag, $lt == '&lt;'
									);
								}
							}
						} else {
							$newContent[$c++] = $this->processTag(
								'<' . ($endTag ? '/' : '') . $tagContent . '>', $addConfig, $endTag
							);
						}
					} elseif ($keepAll) {
						// This is if the tag was not defined in the array for processing:
						if ($keepAll === 'protect') {
							$lt = '&lt;';
							$gt = '&gt;';
						} else {
							$lt = '<';
							$gt = '>';
						}
						$newContent[$c++] = $this->processTag(
							$lt . ($endTag ? '/' : '') . $tagContent . $gt, $addConfig, $endTag, $lt == '&lt;'
						);
					}
					$newContent[$c++] = $this->processContent(substr($tok, $tagEnd + 1), $hSC, $addConfig);
				} else {
					$newContent[$c++] = $this->processContent('<' . $tok, $hSC, $addConfig);
				}
			} else {
				$newContent[$c++] = $this->processContent(($skipTag ? '' : '<') . $tok, $hSC, $addConfig);
				// It was not a tag anyways
				$skipTag = FALSE;
			}
		}
		// Unsetting tags:
		foreach ($tagRegister as $tag => $positions) {
			foreach ($positions as $pKey) {
				unset($newContent[$pKey]);
			}
		}
		$newContent = implode('', $newContent);
		$newContent = $this->stripEmptyTagsIfConfigured($newContent, $addConfig);

		return $newContent;
	}
}
