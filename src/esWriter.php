<?php

namespace DTE\esWriter;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Client as ESClient;

/**
 * Created by PhpStorm.
 * User: grzegorz
 * Date: 01.03.18
 * Time: 10:14
 */

/**
 * Consider changing name maybe ?
 * As what it really does is REpointing alias onto new index, and forgetting
 * any it was poining on before
 * @param \Elasticsearch\Client $client
 * @param                       $index
 * @param                       $alias
 */
function putAlias(ESClient $client, $index, $alias) {
	$params['body'] = [
		'actions' => [
			[
				'remove' => [
					'index' => '*',
					'alias' => $alias
				],
			],
			[	'add' => [
					'index' => $index,
					'alias' => $alias
				]
			]
		]
	];
	$client->indices()->updateAliases($params);
}


function repointAlias(ESClient $client, $old_index, $new_index, $alias) {
	$params['body'] = [
		'actions' => [
			[
				'remove' => [
					'index' => $old_index,
					'alias' => $alias
				],
			],
			[
				'add' => [
					'index' => $new_index,
					'alias' => $alias
				]
			]
		]
	];
	$client->indices()->updateAliases($params);
}

function addIndex(ESClient $client, $index) {
	$params = [
		'index' => $index,
		'body' => [
			'settings' => [
				// allow null entries globally for the index. We aren't sure what are we going to be facing
				'index.mapping.ignore_malformed' => true,

				// this is a setup infered from https://cpratt.co/how-many-shards-should-elasticsearch-indexes-have/
				'number_of_replicas' => 1, // lets create replicas while indexing, so that it doesn't have to process it afterwards
				'number_of_shards'   => 3
			]
		]
	];
	$client->indices()->create($params);
}

function getRawIndexName($indexName) {
	return $indexName.'_'.time().'_'.rand(0, 99999); // add a random stuff to avoid clashes, just a heuristic but should work well for us
}

function indexNameFromEntity($indexPrefix, $entityType, $entity = Null) {
	switch($entityType) {
		case 'node':
			return getRawIndexName(aliasNameFromEntity($indexPrefix, $entityType, $entity));
		case 'block':
			return getRawIndexName(aliasNameFromEntity($indexPrefix, $entityType, $entity));
	}
}

function aliasNameFromEntity($indexPrefix, $entityType, $entity = Null) {
	switch($entityType) {
		case 'node':
			$nodeType = $entity['type'];
			return $indexPrefix.''.$entityType.'_'.$nodeType;
		case 'block':
			return $indexPrefix.'blocks';
	}
}

function getNodeAliasName($indexPrefix, $nodeType) {
	return $indexPrefix.'node_'.$nodeType;
}

/**
 * This will ultimately be the powerhouse of the tool. It needs to tackle
 * all the possible edge cases and maintain index aliases etc.
 *
 * @param ESClient $client
 * @param          $aliasName
 * @param          $indexName
 * @param          $entities
 */
function writeInto(ESClient $client, $aliasName, $indexName, $entities) {
	$entitiesWrite = function() use($client, $indexName, $entities) {

		$params = [
			'index' => $indexName,
			'type'  => $indexName,
			'body'  => []
		];
		$params['body']  = [];
		for ($i = 0; $i < count($entities); $i++) {
			$params['body'][] = array(
				'index' => array(
					'_index' => $indexName,
					'_type'  => $indexName,
				)
			);
			$params['body'][] = $entities[$i];
		}
		return $client->bulk($params);
	};

	try {
		$params = ['index' => $indexName];

		$client->count($params);

		$response = $entitiesWrite();  // not first time we write to this index, just proceed
	} catch(Missing404Exception $e) {
		// first document in the index
		createIndexAndPutSaneDefaultsIntoIt($client, $indexName);
		$response = $entitiesWrite();

		// now need to create it's alias
		putAlias($client, $indexName, $aliasName);
	} catch(\Exception $e) {
		var_dump($e);
	}

	return $response;
}

function createIndexAndPutSaneDefaultsIntoIt(ESClient $client, $indexName) {

	$params = [
		'index' => $indexName,
		'body' => [
			'settings' => [
				// allow null entries globally for the index. We aren't sure what are we going to be facing
				'index.mapping.ignore_malformed' => true, // need to be very generous as to what we might expect
				'mapping.total_fields.limit' => 100000,
				// this is a setup infered from https://cpratt.co/how-many-shards-should-elasticsearch-indexes-have/
				// with it if we expand onto 3rd node (a likelly setup for kwiziq live once we grow)
				// we wont need to create any new shard (which needs reindexing normally), but could then just create
				// one more replica to make it greatly fail proof
				// the current setup consists of 2 physical node, each with 3 shards and 1 replica, so
				// in theory a failure of either node wont make the ES stop working
				'number_of_replicas' => 1, // lets create replicas while indexing, so that it doesn't have to process it afterwards
				'number_of_shards'   => 3
			]
		]
	];

	return $client->indices()->create($params);
}

function deleteByField(ESClient $client, $index, $fieldName, $fieldValue) {
	$esQuery = [
		'index' => $index,
		'body' => [
			'query' => [
				'match' => [
					$fieldName => $fieldValue
				]
			]
		]
	];

	return $client->deleteByQuery($esQuery);
}

function writeNodesLazily(ESClient $client, $batchSize, $nodeLazyLoader) {


	$writerFn = function($firstNode) use($client, $nodeLazyLoader, $batchSize) {

		$batchNo = 0;
		$indexName = indexNameFromEntity('', 'node', $firstNode);
		$aliasName = aliasNameFromEntity('', 'node', $firstNode);
		$totalProcessed = 0;
		while($nodes = $nodeLazyLoader($batchSize, $batchNo)) {

			$response = writeInto($client, $aliasName, $indexName, $nodes);

			if($response['errors']) {
				echo "\nErrors importing batch no. $batchNo:\n";
				var_dump($response);
			}
			$batchNo += 1;
			$totalProcessed += count($nodes);

			// on PHP5 with Drupal 6.x this baby will leak as s**t.
			// We want to at least monitor it here for entertainment...
			// Roughly speaking it leaks ~100MB per 1k nodes. So should be able to
			// import a moderately grown app with a PC-standard amount of memory
			// otherwise need to wait for a proper incremental update handler or buy more mem sticks :saddest_troll_face:
			echo "Type: ".$firstNode['type'].". Processing batch no. $batchNo, batch items processed total so far: ".$totalProcessed.", memory usage: ".(memory_get_peak_usage(true)/1024/1024)." MiB            \r";

		}
		echo "\n";
		return [['aliasName' => $aliasName, 'indexName' => $indexName, 'count' => $totalProcessed]];
	};

	// fist node is used to infer ES index naming
	$firstNodes = $nodeLazyLoader(1, 0);  // a list, either empty or with one element
	return array_map('array_pop', array_map($writerFn, $firstNodes));
}

function writeBlocks(ESClient $client, $blocks) {
	$indexName = indexNameFromEntity('', 'block');
	$aliasName = aliasNameFromEntity('', 'block');

	$response = writeInto($client, $aliasName, $indexName, $blocks);

	if($response['errors']) {
		echo "\nErrors importing blocks:\n";
		var_dump($response);
	}
	return [[['aliasName' => $aliasName, 'indexName' => $indexName, 'count' => count($blocks)]]]; // wrap side effects
}


// a set of helper methods for getting values from esWriter responses

function extractIndexName($row) {
	return $row['indexName'];
}

function extractAliasName($row) {
	return $row['aliasName'];
}

function extractCount($row) {
	return $row['count'];
}
