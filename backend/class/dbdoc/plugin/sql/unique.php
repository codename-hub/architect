<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing unique field config in a model
 * @package architect
 */
class unique extends \codename\architect\dbdoc\plugin\unique {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query(
      "SELECT table_schema, table_name, constraint_name
      FROM information_schema.table_constraints
      WHERE constraint_type='UNIQUE'
      AND table_schema = '{$this->adapter->schema}'
      AND table_name = '{$this->adapter->model}';"
    );
    $constraints = $db->getResult();

    foreach($constraints as &$constraint) {
      $db->query(
        "SELECT table_schema, table_name, constraint_name, column_name
        FROM information_schema.key_column_usage
        WHERE constraint_name = '{$constraint['constraint_name']}'
        AND table_schema = '{$this->adapter->schema}'
        AND table_name = '{$this->adapter->model}'
        ORDER BY constraint_name;"
      );
      $constraintColumns = $db->getResult();
      $constraint['constraint_columns'] = $constraintColumns;
    }

    return $constraints;
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

    foreach($structure as $struc) {

      // get ordered (?) column_names
      $constraintColumnNames = array_map(
        function($spec) {
          return $spec['column_name'];
        }, $struc['constraint_columns']
      );

      // reduce to string, if only one element
      $constraintColumnNames = count($constraintColumnNames) == 1 ? $constraintColumnNames[0] : $constraintColumnNames;

      // compare!
      if(in_array($constraintColumnNames, $definition)) {
        // constraint exists and is correct
        $valid[] = $constraintColumnNames;
      } else {
        // $toomuch = $constraintColumnNames;
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_UNIQUE_CONSTRAINT", array(
          'constraint_name' => $struc['constraint_name']
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
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "ADD_UNIQUE_CONSTRAINT", array(
          'constraint_columns' => $def
        ));
      } else {
        // single-column constraint
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "ADD_UNIQUE_CONSTRAINT", array(
          'constraint_columns' => $def
        ));
      }
    }

    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;
    if($task->name == "ADD_UNIQUE_CONSTRAINT") {
      /*
      ALTER TABLE <table>
      ADD UNIQUE (<single or comma-delimited multi-column>);
      */

      $constraintColumns = $task->data->get('constraint_columns');
      $columns = is_array($constraintColumns) ? implode(',', $constraintColumns) : $constraintColumns;

      $db->query(
       "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
       ADD UNIQUE ({$columns});"
      );
    }
  }

}