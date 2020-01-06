<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use codename\architect\dbdoc\task;
use codename\core\exception;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class foreign extends \codename\architect\dbdoc\plugin\sql\foreign {

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

    $db->query("PRAGMA foreign_key_list('{$this->adapter->schema}.{$this->adapter->model}')");

    $constraints = $db->getResult();

    return $constraints;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    // NOTE: Special implementation for MySQL
    // see: https://stackoverflow.com/questions/14122031/how-to-remove-constraints-from-my-mysql-table/14122155
    if($task->name == "REMOVE_FOREIGNKEY_CONSTRAINT") {

      $field = $task->data->get('field');
      $config = $task->data->get('config');

      $constraintName = $task->data->get('constraint_name');

      // drop the foreign key constraint itself
      $db->query(
       "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        DROP FOREIGN KEY {$constraintName};"
      );

      // drop the associated index
      $db->query(
       "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        DROP INDEX IF EXISTS {$constraintName};"
      );
      return;
    }
    parent::runTask($task);
  }
}
