<?php

require "vendor/autoload.php";
require "import_node.php";


// those are just WIP paths/configs. Will read those from ARGS list ultimately
const BATCH_SIZE  = 20;  // hey sorry IDK why by MYSQL tends to break on `25` value, kek
const DRUPAL_PATH = '/path/to/drupal/without/trailing/slash';
const ES_HOST     = 'https://user:pass@es.domain:9200';

import_node(ES_HOST, DRUPAL_PATH);