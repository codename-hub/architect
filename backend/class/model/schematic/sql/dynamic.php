<?php
namespace codename\architect\model\schematic\sql;

/**
 * dynamic SQL model
 */
class dynamic extends \codename\core\model\schematic\sql {

  /**
   * @inheritDoc
   */
  public function __CONSTRUCT(array $modeldata = array(), callable $getDbCallback)
  {
    $this->getDbCallback = $getDbCallback;
    parent::__CONSTRUCT($modeldata);
  }

  /**
   * loads a new config file (uncached)
   * @return \codename\core\config
   */
  protected function loadConfig() : \codename\core\config {
    if($this->modeldata->exists('appstack')) {
      return new \codename\core\config\json('config/model/' . $this->schema . '_' . $this->table . '.json', true, false, $this->modeldata->get('appstack'));
    } else {
      return new \codename\core\config\json('config/model/' . $this->schema . '_' . $this->table . '.json', true);
    }
  }

  /**
   * @inheritDoc
   */
  public function setConfig(string $connection = null, string $schema, string $table) : \codename\core\model {

    $this->schema = $schema;
    $this->table = $table;

    if(!$this->config) {
      $this->config = $this->loadConfig();
    }

    // Connection now defined in model .json
    if($this->config->exists("connection")) {
      $connection = $this->config->get("connection");
    } else {
      $connection = 'default';
    }

    $getDbCallback = $this->getDbCallback;
    $this->db = $getDbCallback($connection, $this->storeConnection);

    return $this;
  }

  /**
   * workaround to get db from another appstack
   * @var callable
   */
  protected $getDbCallback = null;

  /**
   * @inheritDoc
   */
  protected function getType() : string
  {
    // TODO: make dynamic, based on ENV setting!
    return 'mysql';
  }

}
