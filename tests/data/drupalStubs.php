<?php

namespace DTE\Tests\drupalStubs;

/**
 * Based on a real world node from Drupal 6.x
 */
function stubRawNode() {
	require 'rawNode.php';
	return $raw_node;  // from the includecd file above
}