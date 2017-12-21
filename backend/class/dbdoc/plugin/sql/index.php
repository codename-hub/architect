<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;

/**
 * we may add some kind of loading prevention, if some classes are not loaded/undefined
 * as we're using a filename that is the same as standard php scripts loaded for directories
 * if none is given
 */

/**
 * plugin for providing and comparing index / indices field config in a model
 * @package architect
 */
class index extends \codename\architect\dbdoc\plugin\index {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();

    $definition = $this->getDefinition();

    // virtual = assume empty structure
    $structure = $this->virtual ? array() : $this->getStructure();

    $valid = array();
    $missing = array();
    $toomuch = array();

    foreach($structure as $strucName => $struc) {

      // get ordered (?) column_names
      $indexColumnNames = array_map(
        function($spec) {
          return $spec['column_name'];
        }, $struc
      );

      // reduce to string, if only one element
      $indexColumnNames = count($indexColumnNames) == 1 ? $indexColumnNames[0] : $indexColumnNames;

      // compare!
      if(in_array($indexColumnNames, $definition)) {
        // constraint exists and is correct
        $valid[] = $indexColumnNames;
      } else {
        // $toomuch = $constraintColumnNames;
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_INDEX", array(
          'index_name' => $strucName
        ));
      }
    }

    // determine missing constraints
    array_walk($definition, function($d) use ($valid, &$missing) {
      foreach($valid as $v) {
        if(gettype($v) == gettype($d)) {
          if($d == $v) {
            return;
          }
        } else {
          continue;
        }
      }
      $missing[] = $d;
    });

    foreach($missing as $def) {
      if(is_array($def)) {
        // multi-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", array(
          'index_columns' => $def
        ));
      } else {
        // single-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", array(
          'index_columns' => $def
        ));
      }
    }

    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    /*
     we may use some query like this:
     // @see: https://stackoverflow.com/questions/5213339/how-to-see-indexes-for-a-database-or-table

     SELECT (DISTINCT?) s.*
     FROM INFORMATION_SCHEMA.STATISTICS s
     LEFT OUTER JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
         ON t.TABLE_SCHEMA = s.TABLE_SCHEMA
            AND t.TABLE_NAME = s.TABLE_NAME
            AND s.INDEX_NAME = t.CONSTRAINT_NAME
     WHERE 0 = 0
           AND t.CONSTRAINT_NAME IS NULL
           AND s.TABLE_SCHEMA = 'YOUR_SCHEMA_SAMPLE';


      *** removal:
      DROP INDEX `indexname` ON `tablename`


     */

    $db = $this->getSqlAdapter()->db;

    $db->query(
      "SELECT DISTINCT tc.table_schema, tc.table_name, s.index_name, s.column_name, s.seq_in_index
      FROM information_schema.statistics s
      LEFT OUTER JOIN information_schema.table_constraints tc
          ON tc.table_schema = s.table_schema
             AND tc.table_name = s.table_name
             AND s.index_name = tc.constraint_name
      WHERE 0 = 0
            AND tc.constraint_name IS NULL
            AND s.table_schema = '{$this->adapter->schema}'
            AND s.table_name = '{$this->adapter->model}'"
    );

    $allIndices = $db->getResult();

    $indexGroups = [];

    // perform grouping
    foreach($allIndices as $index) {
      if(array_key_exists($index['index_name'], $indexGroups)) {
        // match to existing group
        foreach($indexGroups as $groupName => $group) {
          if($index['index_name'] == $groupName) {
            $indexGroups[$groupName][] = $index;
            break;
          }
        }
      } else {
        // create new group
        $indexGroups[$index['index_name']][] = $index;
      }
    }

    $sortedIndexGroups = [];
    // sort!
    foreach($indexGroups as $groupName => $group) {
      usort($group, function($left, $right) {
        return $left['seq_in_index'] > $right['seq_in_index'];
      });
      $sortedIndexGroups[$groupName] = $group;
    }

    return $sortedIndexGroups;
  }



  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    if($task->name == "ADD_INDEX") {
      /*
      CREATE INDEX <indexname> ON <table> (<single or comma-delimited multi-column>);
      */

      $indexColumns = $task->data->get('index_columns');
      $columns = is_array($indexColumns) ? implode(',', $indexColumns) : $indexColumns;
      $indexName = 'index_' . md5($columns);

      $db->query(
       "CREATE INDEX {$indexName} ON {$this->adapter->schema}.{$this->adapter->model} ({$columns});"
      );
    }

    if($task->name == "REMOVE_INDEX") {

      // simply drop index by index_name
      $indexName = $task->data->get('index_name');

      $db->query(
       "DROP INDEX {$indexName} ON {$this->adapter->schema}.{$this->adapter->model};"
      );
    }
  }

}