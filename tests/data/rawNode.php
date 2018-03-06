<?php

/**
 * Just create a dummy node. We only create type of fields we are actually going
 * to be parsing with the tool.
 */


$raw_node = new stdClass();

// either key->string values
$raw_node->type     = 'stub_node';
$raw_node->property = 'propery_value';
$raw_node->changed  = '1506368800';

// CCK fields
$raw_node->field_multi_value = [  // no need having single valued separately
	0 => [
          'value' => 'Continent',
          'safe'  => 'Continent',
          'view'  => 'Continent'
	],
	1 => [
		'value' => 'Continent2',
		'safe'  => 'Continent2',
		'view'  => 'Continent2'
	]
];

$aTerm = new StdClass();
$aTerm->tid = '12345';
$aTerm->vid = '29';
$aTerm->name = 'All inclusive';
$aTerm->description = '';
$aTerm->weight = '0';

// or taxonomies
$raw_node->taxonomy = [
	12345 => $aTerm
];

// we want to keep the stuff above, and drop anything else, like the 'content' etc.
$raw_node->content = [
	'this should get' => 'dropped'
];

