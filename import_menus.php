<?php

/**
 * Imports custom blocks from the core block module
 */
use DTE\drupalConnector;
use DTE\esValidation;
use DTE\esWriter;
use DTE\esReader;
use Elasticsearch\ClientBuilder;


function import_menus($es_host, $drupal_path) {
	drupalConnector\grabMenus($drupal_path);
}

