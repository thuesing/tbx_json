 <?php
/*
* Hierarchical Taxo Parser
*/ 
error_reporting(E_ALL);
require_once 'exhibit-json-parser.inc.php';
require_once 'exhibit-taxo-parser.inc.php';

$for_item_name = 'taxonomies';  // filename, logger etc.
$bundles = array('model','expert'); // parse taxos for
$vocab_names = array();

foreach ($bundles as $bundle) {
	$vocab_names = array_merge(_taxo_names_for_bundle($bundle), $vocab_names);
}

$vocab_names = array_unique($vocab_names);

/* 
* items for taxos
*/

$items = array(); 
foreach($vocab_names as &$vname) :
  $items = array_merge(_items_for_taxo($vname), $items);
endforeach;

// print to file

$exhibit_json = exhibit_json($items, null, null);

save_and_backup_exhibit_db ($exhibit_json, $for_item_name, $pretty_print = FALSE);




  