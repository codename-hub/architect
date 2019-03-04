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
  public function getDefinition()
  {
    // "index" specified in model definition
    $definition = parent::getDefinition();

    //
    // NOTE: Bad issue on 2019-02-20:
    // Index Plugin wants to remove Indexes created
    // for Foreign & Unique Keys, as well as Primary Keys
    // after the change in structure retrieval (constraint_name is null)
    // therefore, we have to check those keys, too.
    //
    //
    // for mysql/sql, merge in foreign keys!
    $foreignPlugin = $this->adapter->getPluginInstance('foreign', array(), true);
    $foreignKeys = $foreignPlugin->getDefinition();
    foreach($foreignKeys as $fkey => $fkeyConfig) {
      if(is_array($fkeyConfig['key'])) {
        // multi-component foreign key - $fkey is NOT a field name, use 'key'-keys
        $definition[] = array_keys($fkeyConfig['key']);
      } else {
        // just use the foreign key definition name (this is the current table's key to be used)
        $definition[] = $fkey;
      }
    }

    // for mysql/sql, merge in unique keys!
    $uniquePlugin = $this->adapter->getPluginInstance('unique', array(), true);
    $uniqueKeys = $uniquePlugin->getDefinition();
    foreach($uniqueKeys as $i => $uniqueKey) {
      $definition[] = $uniqueKey;
    }

    //
    // make unique!
    // otherwise, we may get duplicates
    // NOTE:
    // this may cause a problem, when creating a foreign key at the same time?
    //
    $definition = array_values(array_unique($definition, SORT_REGULAR));

    // print_r($definition);
    // echo("<br>");

    return $definition;
  }

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

    // $fieldsOnly = $this->parameter['fields_only'] ?? null;
    foreach($structure as $strucName => $struc) {

      // get ordered (?) column_names
      $indexColumnNames = array_map(
        function($spec) {
          return $spec['column_name'];
        }, $struc
      );

      // if($fieldsOnly && !in_array($indexColumnNames, $fieldsOnly, true)) {
      //   echo "Skipping ".var_export($indexColumnNames, true)." because not contained in fieldsOnly ".var_export($fieldsOnly, true)."<br>";
      //   continue;
      // }
      // reduce to string, if only one element
      $indexColumnNames = count($indexColumnNames) == 1 ? $indexColumnNames[0] : $indexColumnNames;

      // compare!
      if(in_array($indexColumnNames, $definition)) {
        // constraint exists and is correct
        $valid[] = $indexColumnNames;
      } else {
        // $toomuch = $constraintColumnNames;

        // echo("<pre>");
        // print_r([
        //   $definition,
        //   $indexColumnNames
        // ]);
        // echo("</pre>");

        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_INDEX", array(
          'index_name' => $strucName
        ));
      }
    }

    //
    // NOTE: see note above
    // echo("<pre>Foreign Definition for model [{$this->adapter->model}]".chr(10));
    // print_r([
    //   'definition' => $definition,
    //   'valid'      => $valid,
    //   'structure' => $structure
    // ]);
    // echo("</pre>");

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

      // if($fieldsOnly && !in_array($def, $fieldsOnly, true)) {
      //   echo "Skipping ".var_export($def, true)." because not contained in fieldsOnly ".var_export($fieldsOnly, true)."<br>";
      //   continue;
      // }

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
            AND s.index_type != 'FULLTEXT'"
    );

    //
    // NOTE: we removed the following WHERE-component:
    // AND tc.constraint_name IS NULL
    // and replaced it with a check for just != PRIMARY
    // because we may have constraints attached (foreign keys!)
    // So, this plugin now handles ALL indexes,
    // - explicit indexes (via "index" key in model config)
    // - implicit indexes (unique & foreign keys)
    //

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
       "DROP INDEX IF EXISTS {$indexName} ON {$this->adapter->schema}.{$this->adapter->model};"
      );
    }
  }

}
