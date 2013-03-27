 <?php
 
error_reporting(E_ALL);

/*
 * Simile JSON output parser
 *
 */

// TODO types and properties 
// refactor , see call from script, wie sollen die types and props uebergeben werden

function exhibit_parse_bundle($bundle_name, $exhibit_types, $properties) {
  $start_time = time();
  $nodes = exhibit_get_nodes($bundle_name);  // Drupal
  $items = exhibit_items_for($nodes); // Exhibit

  // parse json
  $json = exhibit_json($items, $exhibit_types, $properties);

  // print to file, backup, log
   #save_and_backup_exhibit_db ($exhibit_json, $bundle_name , $pretty_print) ;
  // wd logger
  #$time_ago =  time() - $start_time ; // log time
  // TODO refactor log msg see 51, 58
  #watchdog('tbx_json', $bundle_name . ' JSON processed  in ' . $time_ago . ' ms');

  return $json;
}

/*
* @param exhibit_json @see func exhibit_json
*/

function save_and_backup_exhibit_db ($exhibit_json, $for_item_name, $pretty_print = FALSE) {

  $bundle = $for_item_name ;

  $filepath = variable_get('file_public_path', conf_path() . '/files'); 
  $realpath = realpath("."); 
  $json_file_dir = $realpath .'/' .$filepath .'/';
  $json_file_extension = '.exhibit.json'; 

  $start_time = time();
  $timestamp = date("Y-m-d-h:i:s",time());


  $exhibit_db_backup = $json_file_dir .'bak/' . $timestamp . '.' . $bundle . $json_file_extension ;// backup previous json db
  $exhibit_db = $json_file_dir . $bundle . $json_file_extension;// serve this file as exhibit db



  // TODO refactor see copy
  json_to_file($exhibit_json, $exhibit_db_backup , $pretty_print);

  // wd logger
  $time_ago = time() - $start_time ; // log time
  $start_time = time();

  if ( @copy ( $exhibit_db_backup, $exhibit_db ) )   {
     $time_ago = time() - $start_time ; // log time 
     watchdog('tbx_json', $bundle  .': jsondb copied to ' . $exhibit_db);
  } else {
    // possibly write restrictions!!
    throw new Exception($bundle  .': could not copy jsondb to ' . $exhibit_db . ". Check for write access!", 1);
    
  }


}

/*
* @return Array of exhibit items
* @param nodes : Drupal nodes
*/
function exhibit_items_for($nodes) {
    $items = array();
    foreach ($nodes as $node) {
      $items[] = exhibit_get_item($node);
    }
    return $items;
}

function exhibit_json($items, $types = NULL, $properties = NULL) {

   $json = array(
     'items'      => $items,
     'types'      => $types,
     'properties' => $properties,
   );   

   return drupal_json_encode($json);
        
 }

 
 function json_to_file($exhibit_json, $file, $pretty_print = FALSE) {
 
 
    //print_r($file);
 
    if($pretty_print) {
      $exhibit_json = indent($exhibit_json); // pretty print
    }
    //print_r($json_string);
    if($fp = @fopen($file, 'w')) {
      fputs ($fp, $exhibit_json);
      fclose ($fp);
      watchdog('tbx_json', 'JSON Db successfully backed up to %1', array('%1' => $file ) , WATCHDOG_INFO);
    } else {
      throw new Exception("Error Open $file for writing", 1);      
    } 

  } 
 

/* 
 * get published nodes for type
 */ 

function exhibit_get_nodes($for_type) {
 
  $query = new EntityFieldQuery();

  $result = $query
    ->entityCondition('entity_type', 'node', '=')
    ->propertyCondition('status', 1, '=')
    ->propertyCondition('type', $for_type)->execute();

  $nodes = node_load_multiple(array_keys($result['node']));

 //  $test = array();
 //  $test[] = $nodes[886];
 //  return $test;
  
 return $nodes;
  
}

// D6 style query, @deprecated
function exhibit_get_nodes_d6($for_type) {
   $result = db_query('SELECT n.nid FROM {node} n WHERE n.type = :type AND n.status = 1 ORDER BY n.nid', array(':type' => $for_type));
   $nodes = array();
   foreach($result as $res) { 
     $node = node_load($res->nid);
     $nodes[] = $node; 
   }   
   return $nodes; 
 }

// D7 query 
function exhibit_get_item($node) {

  define('EXHIBIT_DATE_FORMAT', '%Y-%m-%d %H:%M:%S');
  
  $node_field_names = _get_field_names_for($node->type);  

  $row = array(); // for JSON

  $row['name'] = $row['label'] = $node->title;
  $row['type'] = $node->type;
  $row['author'] = 'user/' . $node->uid;
  $row['created'] = gmstrftime(EXHIBIT_DATE_FORMAT, $node->created);
  $row['changed'] = gmstrftime(EXHIBIT_DATE_FORMAT, $node->changed);
  $row['url']     = url('node/' . $node->nid, array('absolute' => FALSE));

  if ($node->uid != $node->revision_uid) {
    // Let's get the THEMED name of the last editor.
    $user = user_load($node->revision_uid);
    $row['editor'] = 'user/' . $node->revision_uid;
  } 

  foreach($node_field_names as $field_name) {

    // http://api.drupal.org/api/drupal/modules%21field%21field.info.inc/function/field_info_field/7
    $field_info = field_info_field($field_name);
    $field_instance = field_info_instance('node', $field_name, $node->type);
    $field_items = field_get_items('node', $node, $field_name); 
    
    if(empty($field_items)) continue;
    
    $field_value = null;
    $type = $field_info['module'] ;
    
    if ($type == 'text') {
       $field_value = $field_items[0]['value'];
       $field_value = utf8_encode ( $field_value );     //  http://www.php.net/manual/de/function.utf8-encode.php
       $row[$field_name] = $field_value;  
    } elseif(($type == 'list')) { // boolean
       $field_value = ($field_items[0]['value'] == 0) ? 'yes' : 'no';
       $row[$field_name] = $field_value;
    } elseif(($type == 'taxonomy')) { 
      // parse terms
         $terms = _get_terms_for_field_items($field_items);         
         $term_names = array_keys($terms);     
         $field_value = $term_names;
         $row[$field_name] = $field_value;         
    } else { // parse generic
        $field_value = array();
        // collect field values
        foreach($field_items as $val) :
          if(empty($val)) {
            continue; 
          } else {
            $field_value[] = $val;  
          }        
        endforeach;
        
        if(sizeof($res) == 1) { // json output as value
          $row[$field_name] = $field_value[0];
        } elseif(sizeof($res) > 1) { // output as array
          $row[$field_name] = $field_value;
        }

    }

    // get rid of field prefix
    /*
    if( (!empty($row[$field_name])) AND (substr($field_name, 0, 6) == 'field_') ) {
       $new_key = substr ( $field_name, 6 );
       $row[$new_key] = $row[$field_name];
       unset($row[$field_name]); 
    }*/
    // unset null values
    if (empty($row[$field_name])) {
      unset($row[$field_name]); 
    } 
   
  } // foreach($node_field_names as $field_name)
  
  return $row;
}

/*
 * return an array of taxonomy field names for bundle
 * module is the module, the field is stored by
 */
 
function _get_field_names_for($bundle_name, $module = null) {
 
    // http://api.drupal.org/api/drupal/modules%21field%21field.info.inc/group/field_info/7
    $instances = field_info_instances('node', $bundle_name);
    $fields = array();
    foreach($instances as &$field) :
      $field_info = field_info_field($field['field_name']);
      //print_r($field_info);
      if($module){
        // echo $field_info['module'] . "\n";
        if($field_info['module'] == $module){    
          $fields[] = $field['field_name'];
        } 
      } else { // no module filter
        $fields[] = $field['field_name'];
      }      
    endforeach;
    return  $fields;

}
 
/*
 * return an hash term_name => term_object  
 */
 
function _get_terms_for_field_items($field_items) { 
    //if(empty($field_items); 
    $terms = array();
    foreach($field_items as $val) :
  
      $term = taxonomy_term_load($val['tid']);
      //$terms[] = $term->name;     
      if(empty($term->name) || empty($term)) {
       continue;
      }      
      $terms[$term->name] = $term;
      
    endforeach;
    return  $terms;

} 

/**
 * Indents a flat JSON string to make it more human-readable.
 *
 * @param string $json The original JSON string to process.
 *
 * @return string Indented version of the original JSON string.
 *
 * http://recursive-design.com/blog/2008/03/11/format-json-with-php/
 */
function indent($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
        
        // If this character is the end of an element, 
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element, 
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }
            
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        $prevChar = $char;
    }

    return $result;
}



 function _taxo_get_tree_by_name($name) {
   $voc = taxonomy_vocabulary_machine_name_load($name);   
   $tree = taxonomy_get_tree($voc->vid); 
   return $tree;
 }





