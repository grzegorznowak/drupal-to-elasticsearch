<?php

/**
 * This file should probably be licenced under the bloody GPLv2 ?
 * What about other codes that depend on it ?
 * Should we make the cron_handler file and this one separate from the project ?
 * Let's shelf this rubbish for now, but probably worth picking up at some point
 */

namespace DTE\drupalConnector;

use DTE\drupalReader;

// one huge side-effect, let's just accept its presence...
function maybeBootstrapDrupal($drupalPath) {

	static $bootstraped = false;

	if(!$bootstraped) {

		if(!chdir($drupalPath)) {
			throw new Exception("Unable to chdir to the drupal path");
		}
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		require ('cli/CliCommandRunner.php');
		require_once './includes/bootstrap.inc';
		drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
		error_reporting(E_ALL);
		ini_set('display_errors', TRUE);
		ini_set('display_startup_errors', TRUE);
		restore_error_handler(); // we don't want to use Drupals error handler
		$bootstraped = true;

	}
}

function grabContentTypes($drupalPath) {
	maybeBootstrapDrupal($drupalPath);
	return array_keys(node_get_types($op = 'types', $node = Null, $reset = True));
}

function grabCustomBlocks($drupalPath) {
	maybeBootstrapDrupal($drupalPath);
	$query  = db_query('SELECT * FROM {boxes}');
	$blocks = [];
	while ($block = db_fetch_array($query)) {
		$blocks[] = $block;
	}
	return $blocks;
}

/**
 * Lazy node readers
 * @param $drupalPath
 * @param $contentType
 *
 * @return \Closure
 */
function createNodeReaders($drupalPath, $contentType) {
	$nodeLoader = function($batchSize, $batchNo = 0) use ($contentType, $drupalPath) {
		maybeBootstrapDrupal($drupalPath);
		$result = db_query("SELECT nid FROM node WHERE type = '%s' AND status = 1 LIMIT %d OFFSET %d ", $contentType, $batchSize, $batchNo * $batchSize);
		$nodes  = array();
		while ($obj = db_fetch_object($result)) {
			$nodes[] = drupalReader\rawNodeToES(node_load($obj->nid, $revision = NULL, $reset = True));
		}
		return $nodes;
	};
	return $nodeLoader;
}

function countImportableNodes($drupalPath, $contentType) {
	maybeBootstrapDrupal($drupalPath);
	$result = db_query("SELECT count(nid) as c FROM node WHERE type = '%s' AND status = 1", $contentType);
	while ($obj = db_fetch_object($result)) {
		return $obj->c;
	}
	return 0;
}