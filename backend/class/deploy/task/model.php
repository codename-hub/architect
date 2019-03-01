<?php
namespace codename\architect\deploy\task;

use codename\architect\app;

use codename\architect\deploy\taskresult;

/**
 * base class for doing model-specific tasks
 */
abstract class model extends \codename\architect\deploy\task {

  /**
   * the model name
   * @var string
   */
  protected $model;

  /**
   * the schema name
   * @var string
   */
  protected $schema;

  /**
   * @inheritDoc
   */
  protected function handleConfig()
  {
    parent::handleConfig();
    $this->model = $this->config->get('model');
    $this->schema = $this->config->get('schema');
  }

  /**
   * [getModelInstance description]
   * @return \codename\core\model [description]
   */
  /**
   * [getModelInstance description]
   * @param  string|null               $schemaName [description]
   * @param  string|null               $modelName  [description]
   * @return \codename\core\model         [description]
   */
  protected function getModelInstance(string $schemaName = null, string $modelName = null) : \codename\core\model {
    if(!$schemaName) {
      $schemaName = $this->schema;
    }
    if(!$modelName) {
      $modelName = $this->model;
    }
    $useAppstack = $this->getDeploymentInstance()->getAppstack();
    $modelconfig = (new \codename\architect\config\json\virtualAppstack("config/model/" . $schemaName . '_' . $modelName . '.json', true, true, $useAppstack))->get();
    $modelconfig['appstack'] = $useAppstack;
    $model = new \codename\architect\model\schematic\sql\dynamic($modelconfig, function(string $connection, bool $storeConnection = false) {
      $dbValueObjecttype = new \codename\core\value\text\objecttype('database');
      $dbValueObjectidentifier = new \codename\core\value\text\objectidentifier($connection);
      return app::getForeignClient(
        $this->getDeploymentInstance()->getVirtualEnvironment(),
        $dbValueObjecttype,
        $dbValueObjectidentifier,
        $storeConnection);
    });
    $model->setConfig(null, $schemaName, $modelName);
    return $model;
  }

}
