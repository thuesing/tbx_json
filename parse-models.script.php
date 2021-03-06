 <?php
 
error_reporting(E_ALL);
require_once 'exhibit-json-parser.inc.php';
require_once 'exhibit-taxo-parser.inc.php';

/*
 * configuration
 *
 */


$bundle = 'model';
$exhibit_types  = array(
		'model' => array( 
			'pluralLabel' => 'models'
			)
	);

$properties = array(
 'author'     => array('valueType' => 'item'),
 'created'    => array('valueType' => 'date'),
 'changed'    => array('valueType' => 'date'),
 'url'        => array('valueType' => 'url'),
);

$pretty_print = TRUE;

$json = exhibit_parse_bundle($bundle, $exhibit_types, $properties);

save_and_backup_exhibit_db ($json, $bundle , $pretty_print) ;






