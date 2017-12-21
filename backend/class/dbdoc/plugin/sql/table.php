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

    // either run sub-plugins virtually or the 'hard' way

    // foreign key plugin
    $plugin = $this->adapter->getPluginInstance('foreign', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // execute plugin for unique constraints
    $plugin = $this->adapter->getPluginInstance('unique', array(), $this->virtual);
    if($plugin != null) {
      $this->adapter->addToQueue($plugin, true);
    }

    // execute plugin for indices
    $plugin = $this->adapter->getPluginInstance('index', array(), $this->virtual);
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