<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;

/**
 * we may add some kind of loading prevention, if some classes are not loaded/undefined
 * as we're using a filename that is the same as standard php scripts loaded for directories
 * if none is given
 */

/**
 * plugin for providing and comparing fulltext / indices field config in a model
 * @package architect
 */
class fulltext extends \codename\architect\dbdoc\plugin\fulltext {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getDefinition() {
    // "fulltext" specified in model definition
    $definition = parent::getDefinition();

    // print_r($definition);
    // echo("<br>");

    return $definition;
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array {
    $tasks = array();

    // return $tasks;

    $definition = $this->getDefinition();

    // virtual = assume empty structure
    $structure = $this->virtual ? array() : $this->getStructure();

    $valid = array();
    $missing = array();
    $toomuch = array();

    foreach($structure as $strucName => $struc) {

      // get ordered (?) column_names
      $fulltextColumnNames = array_map(
        function($spec) {
          return $spec['column_name'];
        }, $struc
      );

      // reduce to string, if only one element
      $fulltextColumnNames = count($fulltextColumnNames) == 1 ? $fulltextColumnNames[0] : $fulltextColumnNames;

      // compare!
      if(in_array($fulltextColumnNames, $definition)) {
        // constraint exists and is correct
        $valid[] = $fulltextColumnNames;
      } else {
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_FULLTEXT", array(
          'fulltext_name' => $strucName
        ));
      }
    }

    // determine missing constraints
    array_walk($definition, function($d) use ($valid, &$missing) {
      foreach($valid as $v) {
        // DEBUG
        // echo("-- Compare ".var_export($d,true)." ".gettype($d)." <=> ".var_export($v, true)." ".gettype($v)." <br>".chr(10));
        if(gettype($v) == gettype($d)) {
          if($d == $v) {
            // DEBUG
            // echo("-- => valid/equal, skipping.<br>");
            return;
          }
        } else {
          // DEBUG
          // echo("-- => unequal types, skipping.<br>");
          continue;
        }
      }

      // DEBUG
      // echo("-- => invalid/unequal, add to missing.<br>");
      $missing[] = $d;
    });

    foreach($missing as $def) {

      if(is_array($def)) {
        // multi-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_FULLTEXT", array(
          'fulltext_columns' => $def
        ));
      } else {
        // single-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_FULLTEXT", array(
          'fulltext_columns' => $def
        ));
      }
    }

    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function getStructure() {

    $db = $this->getSqlAdapter()->db;

    $db->query(
      "SELECT DISTINCT tc.table_schema, tc.table_name, s.index_name, tc.constraint_name, s.column_name, s.seq_in_index
      FROM information_schema.statistics s
      LEFT OUTER JOIN information_schema.table_constraints tc
          ON tc.table_schema = s.table_schema
             AND tc.table_name = s.table_name
             AND s.index_name = tc.constraint_name
      WHERE 0 = 0
            AND s.index_name NOT IN ('PRIMARY')
            AND s.table_schema = '{$this->adapter->schema}'
            AND s.table_name = '{$this->adapter->model}'
            AND s.index_type = 'FULLTEXT'"
    );

    $allFulltext = $db->getResult();

    // echo '<pre>';
    // print_r($allFulltext);
    // echo '</pre>';

    $fulltextGroups = [];

    // perform grouping
    foreach($allFulltext as $fulltext) {
      if(array_key_exists($fulltext['index_name'], $fulltextGroups)) {
        // match to existing group
        foreach($fulltextGroups as $groupName => $group) {
          if($fulltext['index_name'] == $groupName) {
            $fulltextGroups[$groupName][] = $fulltext;
            break;
          }
        }
      } else {
        // create new group
        $fulltextGroups[$fulltext['index_name']][] = $fulltext;
      }
    }

    $sortedfulltextGroups = [];
    // sort!
    foreach($fulltextGroups as $groupName => $group) {
      usort($group, function($left, $right) {
        return $left['seq_in_index'] > $right['seq_in_index'];
      });
      $sortedfulltextGroups[$groupName] = $group;
    }

    return $sortedfulltextGroups;
  }



  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    if($task->name == "ADD_FULLTEXT") {

      $fulltextColumns = $task->data->get('fulltext_columns');
      $columns = is_array($fulltextColumns) ? implode(',', $fulltextColumns) : $fulltextColumns;
      $fulltextName = 'fulltext_' . md5($columns);

      $db->query(
       "CREATE FULLTEXT INDEX {$fulltextName} ON {$this->adapter->schema}.{$this->adapter->model} ({$columns}) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;"
      );
    }

    if($task->name == "REMOVE_FULLTEXT") {

      // simply drop fulltext by fulltext_name
      $fulltextName = $task->data->get('fulltext_name');

      $db->query(
        "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} DROP INDEX {$fulltextName};"
      );
    }
  }

}
