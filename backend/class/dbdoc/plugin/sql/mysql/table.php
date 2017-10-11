<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
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

      if($field['notnull']) {
        $attributes[] = "NOT NULL";
      }

      if($field['auto_increment']) {
        $attributes[] = "AUTO_INCREMENT";
      }

      $add = implode(' ', $attributes);

      // for mysql, we have to create the table with at least ONE COLUMN
      $db->query(
        "CREATE TABLE {$this->adapter->schema}.{$this->adapter->model} (
          {$field['field']} {$field['db_column_type']} {$add},
          PRIMARY KEY({$field['field']})
        );"
      );

    }
  }
}