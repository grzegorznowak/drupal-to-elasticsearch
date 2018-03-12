<?php

/**
 * Imports custom blocks from the core block module
 */
use DTE\drupalConnector;
use DTE\esValidation;
use DTE\esWriter;
use DTE\esReader;
use Elasticsearch\ClientBuilder;


function import_blocks($es_host, $drupal_path) {

	echo "\nImporting blocks from $drupal_path using es host: $es_host\n";

	$clientBuilder = ClientBuilder::create();   // Instantiate a new ClientBuilder
	$clientBuilder->setHosts([$es_host]);       // Set the hosts
	$esClient = $clientBuilder->build();        // Build the client object

	$blocks  = drupalConnector\grabCustomBlocks($drupal_path);

	echo "No. of rows that should get processed: ".count($blocks)."\n";

	esValidation\confirmIndexAndAliasMatch($esClient, esWriter\writeBlocks($esClient, $blocks));

	echo "DONE importing BLOCKS \n";
}

