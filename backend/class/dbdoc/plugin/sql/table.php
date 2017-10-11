<?php
namespace codename\architect\dbdoc\plugin\sql;
use \codename\architect\dbdoc\plugin;

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

    }

    return $tasks;
  }
}