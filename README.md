# drupal-to-elasticsearch
Lets migrate Drupal data into ElasticSearch


## Motivation

Due to the lack of serious ElasticSearch support for Drupal 6 we need a platform that will let us migrate specific 
entities onto ElasticSearch for the purpose of future reuse of those in a non-Drupal microservice or in bespoke migration tool
between Drupal versions/installations. 
Planned coverage of entities:
- nodes + all CCK fields
- taxonomies
- menus


## Premises

Migrating Drupal has never been an easy task. Not onto major upgraded version of itself nor onto different platforms.
With an interim layer of relations-less datastore (like ElasticSearch) one in theory could make his target platform completely
unaware of the initial Drupal schema and create a bespoke tool around the data as he pleases (or make an specific import tool for
a different CMS, wordpress and such).
ElasticSearch has been picked due to it's simplicity and speed, but also we think it's just as good a database as one 
can have. Especially given it resolves the need for having a separate search engine on top of anything one would use otherwise.
Which was exactly our case.


## Compatibility


#### Drupal
Our specific case requires integration with Drupal 6.x, so it's the only supported version for now. But really the only
part that really needs to know about what version of Drupal are we using is the connector logic, so it's fairly easy
to extended, should anyone wish for it.
 
 
#### ElasticSearch
Unfortunately we're constrained to PHP 5.6 (at the very most, and that sill with a pinch of doubt as to not breaking certain Drupal installations),
and as such we can only use ElasticSearch-php client and ES-server in versions 5.x. There are ways to overcome that but it's beyond this initial
release to try and attempt to.


## Installation

Just clone the project, update parameters in cron_handler.php (namely Drupala installation path and ES address) and invoke it with a simple `php -f cron_handler.php`

 
## Running / Sample output  

The tool has a basic sanity checking built-in by default. The simplest use case is to run it first from shel manually and see
if runs through OK. Then can plugin to cron. Need to extend it with more robust failure reporting, but that's for another sitting... 
````
> php -f cron_handler.php
Type: blog. Processing batch no. 2, batch items processed total so far: 50, memory usage: 70 MiB            
Type: poll. Processing batch no. 1, batch items processed total so far: 25, memory usage: 70 MiB            
Type: accommodations. Processing batch no. 47, batch items processed total so far: 1175, memory usage: 162.25 MiB            
[...]            
No. of rows processed in total: 6611
No. of rows that should get processed: 6611
List of indexes that got written: node_blog_1520343018_68454, node_poll_1520343019_12539, node_accommodations_1520343020_7665, [...]
List of aliases that got saved: node_blog, node_poll, node_accommodations, [...]
Total number of documents written to ES in the end: 6611

````

@see comments under `esWrite\writeNodesLazily` for more insights into RAM usage
## Tests

### Requirements

Need to have an ES instance under local port `127.0.0.1:9300`

You can invoke a set of Units (with coverage as long as you have xdebug installed) with
`./vendor/bin/phpunit --coverage-html ./tests/reports`



## Features (or a Roadmap if you like)

* __Able to index nodes__**_(DONE as the initial PoC)_**
* __In its core the tool should be cron-driven__**_(DONE)_**
* __Initially should just attempt to reindex all content everytime it's run, under new indexes and repoint aliases once this is done__**_(DONE)_**
* __Drupal/ES connector need to be performant enough to index thousands of nodes per cron run__**_(DONE)_**
* (Extra) Ability to index other drupal assest as required __(WIP)__
* (Extra) Then should be capable of idempotent subsequent runs, taking care of updating/adding new content. With exception of being able 
 to remove from ES stuff deleted on Drupal-end between runs. Not in the initial version at least. It was never
 meant to be a 1:1 synchronization between Drupal, but rather a way of marshaling data FROM-TO. But having said that, one can easily force
 full re-indexation of an entity class, which in practice would wipe out stalled content easily. Additionally making stuff `unpublish` instead
 of full frontal removal should do the job as well, just then such content need to be tackled on ES query level no to show up anywhere.
* (Extra, depends on the previous point to make any sense) Everytime entity schema gets updated, it will get reindexed to a new working index and it's alias updated behind the scene
* **Built with love using quasi-functional no-classes PHP, with as little side-effects as possible for all your Clojure/Haskell believers :)**

## Disclaimer / Known Issues

* You might need to tweak `drupalBootstrap` routine if you have specific setup in place. Like multisite etc. That is not covered by default 
* Parsing Drupal onto ES is not trivial task. The tool here has been forged based on a moderately big application with 30+ content types
and possibly hundreds of CCK fields, so should cover pretty wide variation of use cases already. In any other case the go-to place
is the `drupalReade\rawNodeToES` function, that tries to make best guess over what to give ES for his own mappings' auto-guesser
