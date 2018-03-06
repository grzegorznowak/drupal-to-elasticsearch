<?php
/**
 * Created by PhpStorm.
 * User: strange3studio@gmail.com
 * Date: 01.03.18
 * Time: 08:34
 */

require_once 'tests/data/drupalStubs.php';

use Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use DTE\esReader;
use DTE\esWriter;
use DTE\drupalReader;
use DTE\Tests\drupalStubs;

/**
 * Please note it will assume a 6.x ES instance running on 127.0.0.1
 * Thanks to index prefixing built-in there is really low chance it would
 * conflict with anything existing on a ES already, but consider yourself warned!
 * Class ESTests
 */
final class ESTests extends TestCase {

	const TEST_ES_HOST    = '127.0.0.1';
	const TEST_INDEX_NAME = 'test-index-dte';

	protected $esClient = null;

	protected function setUp() {
		$clientBuilder = ClientBuilder::create();           // Instantiate a new ClientBuilder
		$clientBuilder->setHosts([self::TEST_ES_HOST]);     // Set the hosts
		$this->esClient = $clientBuilder->build();          // Build the client object

	}

	protected function tearDown() {
		// drop test index (try to not be too clunky as the server could have stuff indexed already)
		$params = ['index' => self::TEST_INDEX_NAME.'*'];
		$this->esClient->indices()->delete($params);
	}

	public function testCreatingIndexAliases() {
		esWriter\addIndex($this->esClient, self::TEST_INDEX_NAME);
		$existingAliases = esReader\grabAliases($this->esClient, self::TEST_INDEX_NAME);
		$this->assertTrue(count($existingAliases[self::TEST_INDEX_NAME]['aliases']) == 0);

		esWriter\putAlias($this->esClient, self::TEST_INDEX_NAME, self::TEST_INDEX_NAME.'_alias');
		$updatedAliases = esReader\grabAliases($this->esClient, self::TEST_INDEX_NAME);
		$this->assertTrue(count($updatedAliases[self::TEST_INDEX_NAME]['aliases']) == 1);
	}

	public function testIndexCanBeGrabbedUsingItsAlias() {
		$index_name = esWriter\getRawIndexName(self::TEST_INDEX_NAME);
		esWriter\addIndex($this->esClient, $index_name);

		// we shouldn't be able to query by index name directly (as no alias is set at this point
		$query_for_index = ['index' => self::TEST_INDEX_NAME];

		try {
			$this->esClient->indices()->getSettings($query_for_index);
			$this->throwException("It shouldn't get here!");
		} catch(Elasticsearch\Common\Exceptions\Missing404Exception $e) {
			// works as expected!
		}

		esWriter\putAlias($this->esClient, $index_name, self::TEST_INDEX_NAME);

		// but now is a different story!
		$this->esClient->indices()->getSettings($query_for_index);

		// $nextVersion = esWriter\reindexToNextVersion($this->esClient, self::TEST_INDEX_NAME);
	}

	/**
	 * Beware that this does not test reindexing itself, just hot-swapping of indexes with their aliases
	 */
	public function testAddingNextIndexForChangedMapping() {
		$index_name = esWriter\getRawIndexName(self::TEST_INDEX_NAME);
		esWriter\addIndex($this->esClient, $index_name);
		esWriter\putAlias($this->esClient, $index_name, self::TEST_INDEX_NAME);


		$index_name_2 = esWriter\getRawIndexName(self::TEST_INDEX_NAME);
		esWriter\addIndex($this->esClient, $index_name_2);
		esWriter\repointAlias($this->esClient, $index_name, $index_name_2, self::TEST_INDEX_NAME);

		$params = ['index' => $index_name];
		$this->esClient->indices()->delete($params);

		$query_for_index = ['index' => self::TEST_INDEX_NAME];
		$this->esClient->indices()->getSettings($query_for_index);
	}

	/**
	 * When we change something in CCK module on Drupal end, we end up with a node
	 * that has a different set of fields than the one stored, need to handle that
	 */
	public function testAppendingANodeWithUpdatedFields() {

	}

	/**
	 * no data has been indexed so far for the given asset, need to create index and import first data
	 */
	public function testAddingAFirstNode() {
		$aNode     = drupalReader\rawNodeToES(drupalStubs\stubRawNode());
		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);
		$alias = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, $entityType = 'node', $aNode);

		//force refresh
		$this->esClient->indices()->refresh([
			'index' => $alias
		]);

		// has document been added to index ?
		$params = ['index' => $alias];
		$response = $this->esClient->count($params);
		$this->assertEquals(1, $response['count']);

		// and has index alias been added to ES
		$params = ['index' => esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode)];
		$response = $this->esClient->count($params);
		$this->assertEquals(1, $response['count']);
	}

	public function testGrabbingLastInsertedNode() {

		$aNode  = drupalReader\rawNodeToES(drupalStubs\stubRawNode());

		$nullNode = esReader\grabLastInsertedNode($this->esClient, self::TEST_INDEX_NAME, $aNode['type']);
		$this->assertNull($nullNode);

		$aNode2 = drupalReader\rawNodeToES(drupalStubs\stubRawNode());

		$aNode2['property'] = 'property 2';
		$aNode2['changed']  = '99999999999';

		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);
		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode2]);

		$lastNode = esReader\grabLastInsertedNode($this->esClient, self::TEST_INDEX_NAME, $aNode['type']);
		$this->assertEquals($aNode2, $lastNode);
	}

	//public function test

	/**
	 * OTOH when we just add a note, and haven't changed its fields before,
	 * need to only append that item in ES
	 */
	public function testAppendingANode() {
		$aNode = drupalReader\rawNodeToES(drupalStubs\stubRawNode());
		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);
		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);

		$alias = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, $entityType = 'node', $aNode);
		//force refresh
		$this->esClient->indices()->refresh([
			'index' => $alias
		]);

		$params = ['index' => $alias];
		$response = $this->esClient->count($params);
		$this->assertTrue($response['count'] === 2);
	}

	/**
	 * writing 10k nodes into ES over 1,5s is an acceptable result
	 * @slowThreshold 1500
	 */
	public function testThatWeCanWriteALotOfStuffWithoutIssues() {
		$noOfItems = 10000;
		$aNode = drupalReader\rawNodeToES(drupalStubs\stubRawNode());

		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		$nodes = array_map(function($idx) {return drupalReader\rawNodeToES(drupalStubs\stubRawNode()); }, range(0, $noOfItems-1));
		esWriter\writeInto($this->esClient, $aliasName, $indexName, $nodes);

		$this->esClient->indices()->refresh([
			'index' => $aliasName
		]);

		$params = ['index' => $aliasName];
		$response = $this->esClient->count($params);
		$this->assertEquals($response['count'], $noOfItems);
	}

// AN EXTRAS
//	public function testUpdatingANode() {
//		$this->fail();
//	}


// AN EXTRAS
	/**
	 * We can't just update it in place
	 * if it has new structure - that won't work and instead we need
	 * to reindex with the new mapping
	 */
//	public function testUpdatingANodeWithChangedFields() {
//		$this->fail();
//	}


	/**
	 * Deletions don't need to check for fields etc. They just remove the field
	 * from the index
	 */
	public function testDeletingAnEntity() {
		$aNode = drupalReader\rawNodeToES(drupalStubs\stubRawNode());

		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);

		$alias = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, $entityType = 'node', $aNode);

		$this->esClient->indices()->refresh([
			'index' => $alias
		]);

		esWriter\deleteByField($this->esClient, $alias, 'type', 'stub_node');
		$this->esClient->indices()->refresh([
			'index' => $alias
		]);

		$params = ['index' => $alias];
		$response = $this->esClient->count($params);
		$this->assertTrue($response['count'] === 0);
	}

	public function testIfEntityHasProperFieldsAfterIndexing() {
		$aNode = drupalReader\rawNodeToES(drupalStubs\stubRawNode());

		$indexName = esWriter\indexNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);
		$aliasName = esWriter\aliasNameFromEntity(self::TEST_INDEX_NAME, 'node', $aNode);

		esWriter\writeInto($this->esClient, $aliasName, $indexName, [$aNode]);

		$this->esClient->indices()->refresh([
			'index' => $aliasName
		]);

		$all = esReader\findAll($this->esClient, $aliasName);
		$this->assertEquals($aNode, $all['hits']['hits'][0]['_source']);
	}

}



