<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use \codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model table data
 * @package architect
 */
class table extends plugin\sql\table {

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    // $db->query(
    //     "SELECT exists(select 1 FROM information_schema.tables WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}') as result;"
    // );
    $db->query(
        "SELECT exists(select 1 FROM sqlite_master WHERE type = 'table' AND tbl_name = '{$this->adapter->schema}.{$this->adapter->model}') as result;"
    );
    return $db->getResult()[0]['result'];
  }

  /**
   * @inheritDoc
   */
  protected function getCheckStructure($tasks) {
    $db = $this->getSqlAdapter()->db;
    // $db->query(
    //     "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}';"
    // );
    // $db->query(
    //     "PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');"
    // );
    $db->query(
        "SELECT name as COLUMN_NAME FROM pragma_table_info('{$this->adapter->schema}.{$this->adapter->model}');"
    );

    ;
    $columns = $db->getResult();

    if ($columns ?? false) {
      $fields = $this->adapter->config->get()['field'] ?? [];

      foreach($columns as $column) {
        if (!in_array($column['COLUMN_NAME'], $fields)) {
          $tasks[] = $this->createTask(task::TASK_TYPE_OPTIONAL, "DELETE_COLUMN", array(
            'table' => $this->adapter->model,
            'field' => $column['COLUMN_NAME']
          ));
        }
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
    if($task->name == 'CREATE_TABLE') {

      // get pkey creation info
      $pkeyPlugin = $this->adapter->getPluginInstance('primary');
      $field = $pkeyPlugin->getDefinition();

      // $fieldPlugin = $this->adapter->getPluginInstance('field', array('field' => $primarykey));
      // $field = $fieldPlugin->getDefinition();

      $attributes = array();


      // workaround
      $attributes[] = 'PRIMARY KEY';

      if($field['auto_increment']) {
        $attributes[] = "AUTOINCREMENT";
      }

      if($field['notnull']) {
        $attributes[] = "NOT NULL";
      }

      $add = implode(' ', $attributes);

      // for mysql, we have to create the table with at least ONE COLUMN
      $db->query(
        "CREATE TABLE '{$this->adapter->schema}.{$this->adapter->model}' (
          {$field['field']} INTEGER {$add}
        );"
        // PRIMARY KEY({$field['field']})
        // ENGINE=InnoDB CHARACTER SET=utf8 COLLATE utf8_general_ci;"
      );

    }
    if($task->name == 'DELETE_COLUMN') {
      $db->query(
        "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} DROP COLUMN IF EXISTS {$task->data->get('field')};"
      );
    }
  }
}
