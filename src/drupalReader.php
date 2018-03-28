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

	$treatEverythingAString = function($aThing) use(&$treatEverythingAString) {
		if(is_array($aThing)) {
			if(count($aThing) == 1 AND isset($aThing[0]) AND $aThing[0] == null) {
				// just dont include this field as it will be an empty cck field structure
			} else {
				return array_map($treatEverythingAString, $aThing);
			}

		} else {
			$stringed = (string) $aThing;
			if($stringed === "") {
				// elastic doesn't like NULL, oh he doesn't!
				// returning an array instead is an acceptable hack that
				// should fold out Drupal variations over fields etc.
				return  [];
			} else {
				return $stringed;
			}
		}
	};

	// we want to attach inverted taxonomies mapping alongside nodes for way easier/faster ES querying later on
	$inverseTaxonomies = function($node) {
		if(isset($node['taxonomy']) && is_array($node['taxonomy'])) {
			// sorry I needed to prototype this quickly, thus the foreach
			$node['taxonomy_by_vid'] = [];
			foreach($node['taxonomy'] as $tid => $taxonomy) {
				if(!isset($node['taxonomy_by_vid'][$taxonomy['vid']])) {
					$node['taxonomy_by_vid'][$taxonomy['vid']] = [
						$taxonomy
					];
				} else {
					$node['taxonomy_by_vid'][$taxonomy['vid']][] = $taxonomy;
				}
			}
		}
		return $node;
	};

	return array_map($inverseTaxonomies, array_map($treatEverythingAString, array_filter($nodeArray, $isIndexableField, $flag = ARRAY_FILTER_USE_BOTH)));
}

