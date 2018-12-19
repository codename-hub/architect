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
  protected function getModelInstance() : \codename\core\model {
    $useAppstack = $this->getDeploymentInstance()->getAppstack();
    $modelconfig = (new \codename\architect\config\json\virtualAppstack("config/model/" . $this->schema . '_' . $this->model . '.json', true, true, $useAppstack))->get();
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
    $model->setConfig(null, $this->schema, $this->model);
    return $model;
  }

}
