<?php

require "vendor/autoload.php";
require "import_nodes.php";
require "import_blocks.php";

// those are just WIP paths/configs. Will read those from ARGS list ultimately
const BATCH_SIZE  = 20;  // hey sorry IDK why by MYSQL tends to break on `25` value, kek
const DRUPAL_PATH = '/path/to/drupal/without/trailing/slash';
const ES_HOST     = 'https://user:pass@es.domain:9200';

import_nodes(ES_HOST, DRUPAL_PATH);
import_blocks(ES_HOST, DRUPAL_PATH);

