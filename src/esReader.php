<?php

/**
 * Created by Grzegorz Nowak (strange3studio@gmail.com)
 * Date: 01.03.18
 * Time: 10:14
 */
namespace DTE\esReader;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Client as ESClient;
use DTE\esWriter;


function grabAliases(ESClient $client, $index) {
	$params = [ 'index' => $index ];
	return $client->indices()->getAliases($params);
}


function findAll(ESClient $client, $index) {
	$esQuery = [
		'index' => $index
	];
	return $client->search($esQuery);
}


function grabLastInsertedNode(ESClient $client, $indexPrefix, $type) {
	try {
		$aliasName = esWriter\getNodeAliasName($indexPrefix, $type);
		$client->indices()->refresh([
			'index' => $aliasName
		]);

		$esQuery = [
			'index' => $aliasName,
			'size'  => 1,
			'body' => [
				'sort'  => [
					'changed.keyword' => [
						'order' => 'desc'
					]
				]
			]

		];

		$r =  $client->search($esQuery);
		return $r['hits']['hits'][0]['_source'];
	} catch(Missing404Exception $e) {
		return Null;
	}

}