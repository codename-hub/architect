<?php
namespace codename\architect\dbdoc\plugin\sql;
use \codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model schema data
 * @package architect
 */
class schema extends plugin\schema {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query(
        "SELECT exists(select 1 FROM information_schema.schemata WHERE schema_name = '{$this->adapter->schema}') as result;"
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
      // schema/database exists
      return array();
    } else {
      // schema/database does not exist
      return array(
        $this->createTask(   )
      );
    }
  }

}