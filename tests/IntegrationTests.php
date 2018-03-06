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


/**
 * Set of tricky tests, trying to see if our mainline communication between
 * Drupal and ES works fine.
 * In its current shape it assumes a working Drupal 6.x installation in the stubs/drupal6/ folder
 * Not a stub per-se but we can think of it as being one easily.
 * It's .gitignored so has to be manually added if you want to run integration test locally yourself
 *
 * Class IntegrationTests
 */
final class IntegrationTests extends TestCase {

	const TEST_ES_HOST    = '127.0.0.1';
	const TEST_INDEX_NAME = 'test-index-dte';
	const DRUPAL_PATH     = 'stubs/drupal6';

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

	/**
	 * just so phpunit doesn't complain for now
	 */
	public function testLiterallyNothing() {
		$this->assertTrue(true);
	}


}



