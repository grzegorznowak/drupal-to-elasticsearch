<?php
/**
 * Created by PhpStorm.
 * User: strange3studio@gmail.com
 * Date: 01.03.18
 * Time: 08:34
 */


require_once 'tests/data/drupalStubs.php';

use PHPUnit\Framework\TestCase;
use DTE\drupalReader;
use DTE\Tests\drupalStubs;


/**
 * A Drupal-testing suite that doesn't actually connect to any Drupal whatsoever.
 * We just test it against stubs where it make sense (there is no reason testing
 * Drupal API itself)
 *
 * Class DrupalTests
 */
final class DrupalTests extends TestCase {

	protected function setUp() {

	}

	protected function tearDown() {

	}

//	public function testRetrievalOfDrupalEntities() {
//		$nodes = drupalReader\grabNodes($limit = 1);
//		$this->assertTrue(is_array($nodes) && count($nodes));
//		array_map(array($this, 'assertIsDrupalNode'), $nodes);
//
//		$taxonomies = drupalReader\grabTaxonomies($limit = 1);
//		$this->assertTrue(is_array($taxonomies) && count($taxonomies));
//		array_map(array($this, 'assertIsDrupalTaxonomy'), $taxonomies);
//
//		$menus = drupalReader\grabMenus($limit = 1);
//		$this->assertTrue(is_array($menus) && count($menus));
//		array_map(array($this, 'assertIsDrupalMenu'), $menus);
//	}

	public function testConvertingNodeToPlainESArray() {
		$expecting = [
			'type' => 'stub_node',
			'property' => 'propery_value',
			'changed'  => '1506368800',
			'field_multi_value' => [
				[
					'value' => 'Continent',
					'safe'  => 'Continent',
					'view'  => 'Continent'
				],
				[
					'value' => 'Continent2',
					'safe'  => 'Continent2',
					'view'  => 'Continent2'
				]
			],
			'taxonomy' => [
				12345 => [
					'tid' => '12345',
					'vid' => '29',
					'name' => 'All inclusive',
					'description' => [], // altered by the process for ES purposes
					'weight' => '0'
				]
			]
		];
		$this->assertEquals(drupalReader\rawNodeToES(drupalStubs\stubRawNode()), $expecting);
	}


}



