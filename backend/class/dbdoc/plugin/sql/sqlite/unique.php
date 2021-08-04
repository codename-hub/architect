<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use codename\architect\dbdoc\task;
use codename\core\exception;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class unique extends \codename\architect\dbdoc\plugin\sql\unique
  implements \codename\architect\dbdoc\plugin\sql\sqlite\partialStatementInterface {

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;

    // $db->query(
    //   "SELECT tc.table_schema, tc.table_name, constraint_name, column_name, referenced_table_schema, referenced_table_name, referenced_column_name
    //     FROM information_schema.table_constraints tc
    //     INNER JOIN information_schema.key_column_usage kcu
    //     USING (constraint_catalog, constraint_schema, constraint_name)
    //     WHERE constraint_type = 'FOREIGN KEY'
    //     AND tc.table_schema = '{$this->adapter->schema}'
    //     AND tc.table_name = '{$this->adapter->model}';");

    $db->query($q = "SELECT name AS constraint_name, `unique` FROM pragma_index_list('{$this->adapter->schema}.{$this->adapter->model}')");
    $res = $db->getResult();

    // if(count($res) > 0) {
    //   echo("<pre>");
    //   print_r($q);
    //   print_r($res);
    //   echo("</pre>");
    // }

    $constraints = [];
    foreach($res as $c) {
      if($c['unique']) {
        $db->query($q = "SELECT name as column_name FROM pragma_index_info('{$c['constraint_name']}')");
        // echo($q);
        // $res2 = $db->getResult();
        $constraint = $c;
        $constraintColumns = $db->getResult(); // array_column($res2, 'column_name');
        $constraint['constraint_columns'] = $constraintColumns;
        $constraints[] = $constraint;
      }
    }

    // if(count($res) > 0) {
    //   echo("<pre>");
    //   print_r($res);
    //   print_r($constraints);
    //   echo("</pre>");
    // }
    return $constraints;
  }

  /**
   * @inheritDoc
   */
  public function getPartialStatement()
  {
    $definition = $this->getDefinition();

    $uniqueStatements = [];
    foreach($definition as $def) {
      $constraintColumns = $def;
      $columns = is_array($constraintColumns) ? implode(',', $constraintColumns) : $constraintColumns;
      $constraintName = "unique_" . md5("{$this->adapter->schema}_{$this->adapter->model}_{$columns}");
      $uniqueStatements[] = "CONSTRAINT {$constraintName} UNIQUE ({$columns})";
    }

    return $uniqueStatements;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    // Disabled, NOTE:
    // SQLite's unique constraint handling is faulty
    // those have to be created during CREATE TABLE
    // as CREATE UNIQUE INDEX ... afterwards does not produce
    // a working constraint
    return;

    $db = $this->getSqlAdapter()->db;
    if($task->name == "ADD_UNIQUE_CONSTRAINT") {
      /*
      ALTER TABLE <table>
      ADD UNIQUE (<single or comma-delimited multi-column>);
      */

      $constraintColumns = $task->data->get('constraint_columns');
      $columns = is_array($constraintColumns) ? implode(',', $constraintColumns) : $constraintColumns;

      $constraintName = "unique_" . md5("{$this->adapter->schema}_{$this->adapter->model}_{$columns}");

      $db->query(
       "CREATE UNIQUE INDEX {$constraintName}
         ON '{$this->adapter->schema}.{$this->adapter->model}' ({$columns});"
      );
    } else if($task->name == "REMOVE_UNIQUE_CONSTRAINT") {
      $constraintName = $task->data->get('constraint_name');

      $db->query(
       "DROP INDEX {$constraintName} ON '{$this->adapter->schema}.{$this->adapter->model}';"
      );
    }
  }
}
