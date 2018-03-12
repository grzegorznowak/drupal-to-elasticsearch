<?php

namespace DTE\esValidation;

use Elasticsearch\Client as ESClient;
use DTE\esWriter;
use DTE\esReader;

function confirmIndexAndAliasMatch(ESClient $esClient, $esWriterResults) {
	$unpackedValues = array_map('array_pop', $esWriterResults);
	$writtenIndexes = array_filter(array_map('DTE\esWriter\extractIndexName', $unpackedValues));
	$writtenAliases = array_filter(array_map('DTE\esWriter\extractIndexName', $unpackedValues));
	echo "List of indexes that got written: ".implode(', ', $writtenIndexes)."\n";
	echo "List of aliases that got saved: ".implode(', ', $writtenAliases)."\n";

	$total =  array_sum(array_map(function($indexName, $aliasName) use($esClient) {
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
	}, $writtenIndexes, $writtenAliases));

	echo "Total number of documents written to ES in the end: ".$total."\n";

}