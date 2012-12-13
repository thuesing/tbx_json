<?php

/*
 * @return array voc machine names for @params $bundle_name, $entity_type
 */
function _taxo_names_for_bundle($bundle_name, $entity_type = 'node') {

	// getting the taxonomy fields
	$instances = field_info_instances($entity_type,$bundle_name);
	$fields = array();
	$res = array();
	foreach($instances as &$field) :
	  $field_info = field_info_field($field['field_name']);
		if($field_info['module'] == 'taxonomy'){ 
		    $tmp = array(); // store multiple values   
			foreach($field_info['settings']['allowed_values'] as $v) {
				if(array_key_exists('vocabulary', $v)) {
				 $res[] = $v['vocabulary'];
				}
			}
		} 
	endforeach;
    return $res;

}

/*
 * @return array of hierarchical term items in simile exhibit format
 */
function _items_for_taxo($taxo_machine_name){

	$voc = taxonomy_vocabulary_machine_name_load($taxo_machine_name);
	// $voc->hierarchy 1/0
	$tree = taxonomy_get_tree($voc->vid);
	$tree = entity_key_array_by_property($tree, 'tid');
	$items = array();

	foreach ($tree as $term) {
	  foreach ($term->parents as $parent_id) {
	  	if($parent_id == 0) continue; // root item
	  	$parent = $tree[$parent_id];
	    $item = array();
	    $item['type'] = $voc->machine_name;
	    $item['label'] = $term->name;
	    $item['subtopicOf'] = $parent->name;
	    if(empty($item['subtopicOf'])) unset($item['subtopicOf']); 
	    $items[] = $item;
	  }
	}  

	return $items;

}

