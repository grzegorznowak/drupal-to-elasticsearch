<?php


require "vendor/autoload.php";

use DTE\drupalConnector;
use DTE\esWriter;
use DTE\esReader;
use Elasticsearch\ClientBuilder;


// those are just WIP paths/configs. Will read those from ARGS list ultimately
const BATCH_SIZE  = 25;
const DRUPAL_PATH = '/path/to/drupal';
const ES_HOST     = '127.0.0.1';

$clientBuilder = ClientBuilder::create();     // Instantiate a new ClientBuilder
$clientBuilder->setHosts([ES_HOST]);     // Set the hosts
$esClient = $clientBuilder->build();          // Build the client object

$contentTypes = drupalConnector\grabContentTypes(DRUPAL_PATH);

$totalsThatShouldGetImported = array_map(function($contentType) {
	return [$contentType, 'count' => drupalConnector\countImportableNodes(DRUPAL_PATH, $contentType)];
}, $contentTypes);

$results = array_map(function($contentType) use($esClient) {
	return esWriter\writeNodesLazily($esClient, BATCH_SIZE, drupalConnector\createNodeReaders(DRUPAL_PATH, $contentType));
}, $contentTypes);

$writtenIndexes = array_filter(array_map(extractIndexName, array_map(array_pop, $results)));
$writtenAliases = array_filter(array_map(extractAliasName, array_map(array_pop, $results)));


$documentsWrittenCounts = array_map(function($indexName, $aliasName) use($esClient) {
	$esClient->indices()->refresh([
		'index' => $indexName
	]);
	$count1 = esReader\findAll($esClient, $indexName)['hits']['total'];
	$count2 = esReader\findAll($esClient, $aliasName)['hits']['total'];

	// the same number should show for both
	if($count1 !== $count2) {
		die("Number of documents between alias {$aliasName} ({$count2})  and index {$indexName} ({$count1}) don't match!");
	}
	return $count1;
	}, $writtenIndexes, $writtenAliases);


echo "No. of rows processed in total: ".array_sum(array_map(extractCount, array_map(array_pop, $results)))."\n";
echo "No. of rows that should get processed: ".array_sum(array_map(extractCount, $totalsThatShouldGetImported))."\n";

echo "List of indexes that got written: ".implode(', ', $writtenIndexes)."\n";
echo "List of aliases that got saved: ".implode(', ', $writtenAliases)."\n";
echo "Total number of documents written to ES in the end: ".array_sum($documentsWrittenCounts)."\n";


function extractIndexName($row) {
	return $row['indexName'];
}

function extractAliasName($row) {
	return $row['aliasName'];
}

function extractCount($row) {
	return $row['count'];
}