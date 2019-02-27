<?php
namespace codename\architect\dbdoc\plugin\sql;
use \codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model table data
 * @package architect
 */
class table extends plugin\table {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query(
        "SELECT exists(select 1 FROM information_schema.tables WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}') as result;"
    );
    return $db->getResult()[0]['result'];
  }

  /**
   * @inheritDoc
   */
  protected function getCheckStructure($tasks) {
    $db = $this->getSqlAdapter()->db;
    $db->query(
        "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}';"
    );
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
  public function Compare() : array
  {
    $tasks = array();
    $definition = $this->getDefinition();

    // if virtual, simulate nonexisting structure
    $structure = $this->virtual ? false : $this->getStructure();

    // structure doesn't exist
    if(!$structure) {
      // table does not exist
      // create table
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_TABLE", array(
        'table' => $definition
      ));
    }

    $tasks = $this->getCheckStructure($tasks);

    // either run sub-plugins virtually or the 'hard' way

    // execute plugin for indices
    $plugin = $this->adapter->getPluginInstance('index', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // execute plugin for unique constraints
    $plugin = $this->adapter->getPluginInstance('unique', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // collection key plugin
    $plugin = $this->adapter->getPluginInstance('collection', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // foreign key plugin
    $plugin = $this->adapter->getPluginInstance('foreign', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    //N fieldlist
    $plugin = $this->adapter->getPluginInstance('fieldlist', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // pkey first
    $plugin = $this->adapter->getPluginInstance('primary', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    return $tasks;
  }

}