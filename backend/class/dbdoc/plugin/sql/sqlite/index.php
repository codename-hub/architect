<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
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
class index extends \codename\architect\dbdoc\plugin\sql\index {
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

    // $fieldsOnly = $this->parameter['fields_only'] ?? null;
    foreach($structure as $strucName => $struc) {

      // get ordered (?) column_names
      $indexColumnNames = array_map(
        function($spec) {
          return $spec['column_name'];
        }, $struc
      );

      // $tasks[] = $this->createTask(task::TASK_TYPE_INFO, "FOUND_INDEXES", array(
      //   'structure' => $structure
      // ));

      // if($fieldsOnly && !in_array($indexColumnNames, $fieldsOnly, true)) {
      //   echo "Skipping ".var_export($indexColumnNames, true)." because not contained in fieldsOnly ".var_export($fieldsOnly, true)."<br>";
      //   continue;
      // }
      // reduce to string, if only one element
      $indexColumnNames = count($indexColumnNames) == 1 ? $indexColumnNames[0] : $indexColumnNames;
      //
      // if($indexColumnNames === 'productpricing_product_id') {
      //   print_r($definition);
      //   print_r($struc);
      //   die();
      // }

      // if($strucName == 'index_5cda4449ac0c1f3b3455772af51d07ac' || $indexColumnNames == 'productpricing_product_id') {
      //   print_r($indexColumnNames);
      //   print_r($definition);
      //   die();
      // }

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
          'index_name' => $strucName,
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
      // print_r($d);
      $missing[] = $d;
    });

    foreach($missing as $def) {

      // if($fieldsOnly && !in_array($def, $fieldsOnly, true)) {
      //   echo "Skipping ".var_export($def, true)." because not contained in fieldsOnly ".var_export($fieldsOnly, true)."<br>";
      //   continue;
      // }

      $columns = is_array($def) ? implode(',', $def) : $def;
      $indexName = 'index_' . md5("{$this->adapter->schema}.{$this->adapter->model}-".$columns); // prepend schema+model

      // if($indexName == 'index_5cda4449ac0c1f3b3455772af51d07ac') {
      //   print_r($def);
      //   print_r($columns);
      //   print_r($definition);
      //   die();
      // }

      if(is_array($def)) {
        // multi-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", array(
          'index_name' => $indexName,
          'index_columns' => $def,
          // 'raw_columns' =>$columns
        ));
      } else {
        // single-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", array(
          'index_name' => $indexName,
          'index_columns' => $def,
          // 'raw_columns' =>$columns
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

    // $db->query(
    //   "SELECT DISTINCT tc.table_schema, tc.table_name, s.index_name, tc.constraint_name, s.column_name, s.seq_in_index
    //   FROM information_schema.statistics s
    //   LEFT OUTER JOIN information_schema.table_constraints tc
    //       ON tc.table_schema = s.table_schema
    //          AND tc.table_name = s.table_name
    //          AND s.index_name = tc.constraint_name
    //   WHERE 0 = 0
    //         AND s.index_name NOT IN ('PRIMARY')
    //         AND s.table_schema = '{$this->adapter->schema}'
    //         AND s.table_name = '{$this->adapter->model}'
    //         AND s.index_type != 'FULLTEXT'"
    // );
    $db->query("PRAGMA index_list('{$this->adapter->schema}.{$this->adapter->model}')");

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
      // Compat mapping to generic index plugin
      $index['index_name'] = $index['name'];

      // if($index['name'] == 'index_e7760f32be0d18cb84d382d6b705b5b1') {
      //   print_r($index);
      // }

      $db->query("PRAGMA index_info('{$index['index_name']}')");
      $indexInfoRes = $db->getResult();

      // $indexInfo = $indexInfoRes[0];
      // print_r($index);
      // print_r($indexInfo);
      // die();
      // if($index['name'] == 'index_e7760f32be0d18cb84d382d6b705b5b1') {
      //   print_r($indexInfoRes);
      // }

      foreach($indexInfoRes as $indexColumn) {
        $indexGroups[$index['index_name']][] = array_merge(
          $index,
          $indexColumn,
          [ 'column_name' => $indexColumn['name'] ]
        );
      }

      // print_r($indexInfo);

      // if(array_key_exists($index['index_name'], $indexGroups)) {
      //   // match to existing group
      //   foreach($indexGroups as $groupName => $group) {
      //     if($index['index_name'] == $groupName) {
      //       $indexGroups[$groupName][] = $index;
      //       break;
      //     }
      //   }
      // } else {
      //   // create new group
      //   $indexGroups[$index['index_name']][] = $index;
      // }
    }

    // print_r($indexGroups);
    // die();

    $sortedIndexGroups = [];
    // sort!
    foreach($indexGroups as $groupName => $group) {
      usort($group, function($left, $right) {
        return $left['seqno'] > $right['seqno'];
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
      $indexName = 'index_' . md5("{$this->adapter->schema}.{$this->adapter->model}-".$columns);// prepend schema+model

      $db->query(
       "CREATE INDEX {$indexName} ON '{$this->adapter->schema}.{$this->adapter->model}' ({$columns});"
      );
    }

    if($task->name == "REMOVE_INDEX") {

      // simply drop index by index_name
      $indexName = $task->data->get('index_name');


      $db->query(
       "DROP INDEX IF EXISTS {$indexName};" // ON '{$this->adapter->schema}.{$this->adapter->model}'
      );
    }
  }

}
