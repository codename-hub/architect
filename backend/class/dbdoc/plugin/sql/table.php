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
    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    if($structure) {
      // table exists, do nothing;
      return array();
    } else {
      // table does not exist
      return array(
        $this->createTask(   )
      );
    }
  }
}