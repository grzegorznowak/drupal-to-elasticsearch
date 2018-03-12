<?php

use DTE\drupalConnector;
use DTE\esWriter;
use DTE\esValidation;
use DTE\esReader;
use Elasticsearch\ClientBuilder;


function import_node($es_host, $drupal_path) {

	echo "\nImporting nodes from $drupal_path using es host: $es_host\n";

	$clientBuilder = ClientBuilder::create();   // Instantiate a new ClientBuilder
	$clientBuilder->setHosts([$es_host]);       // Set the hosts
	$esClient = $clientBuilder->build();        // Build the client object

	$contentTypes = drupalConnector\grabContentTypes($drupal_path);

	$totalsThatShouldGetImported = array_map(function($contentType) {
		return [$contentType, 'count' => drupalConnector\countImportableNodes(DRUPAL_PATH, $contentType)];
	}, $contentTypes);

	$results = array_map(function($contentType) use($esClient) {
		return esWriter\writeNodesLazily($esClient, BATCH_SIZE, drupalConnector\createNodeReaders(DRUPAL_PATH, $contentType));
	}, $contentTypes);

	echo "No. of rows processed in total: ".array_sum(array_map('DTE\esWriter\extractCount', array_map('array_pop',$results)))."\n";
	echo "No. of rows that should get processed: ".array_sum(array_map('DTE\esWriter\extractCount', $totalsThatShouldGetImported))."\n";

	esValidation\confirmIndexAndAliasMatch($esClient, $results);

	echo "DONE importing NODES \n";
}
