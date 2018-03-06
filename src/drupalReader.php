<?php

/**
 * Created by PhpStorm.
 * User: grzegorz
 * Date: 01.03.18
 * Time: 10:14
 */

namespace DTE\drupalReader;


function rawNodeToES($aRawNode) {
	$indexableFields = [
		'taxonomy'
	];

	$nodeArray = json_decode(json_encode($aRawNode), true);


	$isIndexableField = function($fieldValue, $fieldName) use($indexableFields) {
		return is_string($fieldValue) OR in_array($fieldName, $indexableFields) OR preg_match('/field_(.*)/', $fieldName);
	};

	$makeEverythingAString = function($aThing) use(&$makeEverythingAString) {
		if(is_array($aThing)) {
			if(count($aThing) == 1 AND isset($aThing[0]) AND $aThing[0] == null) {
				// just dont include this field as it will be an empty cck field structure
			} else {
				return array_map($makeEverythingAString, $aThing);
			}

		} else {
			$stringed = (string) $aThing;
			if($stringed === "") {
				// elastic doesn't like NULL, oh he doesn't!
				// returning an array instrad is an acceptable hack that
				// should fold out Drupal variations over fields etc.
				return  [];
			} else {
				return $stringed;
			}
		}
	};

	return array_map($makeEverythingAString, array_filter($nodeArray, $isIndexableField, $flag = ARRAY_FILTER_USE_BOTH));
}

