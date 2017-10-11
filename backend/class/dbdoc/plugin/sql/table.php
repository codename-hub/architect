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
    $structure = $this->getStructure();

    if($structure) {
      // table exists, start submodules

      // foreign key plugin
      if($this->adapter->config->get('foreign') != null) {
        $plugin = $this->adapter->getPluginInstance('foreign');
        if($plugin != null) {
          $this->adapter->addToQueue($plugin, true);
        }
      }

      // if unique key constraints exist
      if($this->adapter->config->get('unique') != null) {
        $plugin = $this->adapter->getPluginInstance('unique');
        if($plugin != null) {
          $this->adapter->addToQueue($plugin, true);
        }
      }

      $plugin = $this->adapter->getPluginInstance('fieldlist');
      if($plugin != null) {
        $this->adapter->addToQueue($plugin, true);
      }

      // pkey first
      $plugin = $this->adapter->getPluginInstance('primary');
      if($plugin != null) {
        $this->adapter->addToQueue($plugin, true);
      }

    } else {
      // table does not exist
      // create table
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_TABLE", array(
        'table' => $definition
      ));
    }

    return $tasks;
  }

}