<?php
namespace codename\architect\dbdoc;
use \codename\architect\app;

/**
 * dbdoc
 * @package architect
 */
class dbdoc  {

  /**
   * @param string $app
   * @param string $vendor
   */
  public function __construct(string $app, string $vendor)
  {
    $this->app = $app;
    $this->vendor = $vendor;
    $this->init();
  }

  /**
   * [protected description]
   * @var string
   */
  protected $app;

  /**
   * [protected description]
   * @var string
   */
  protected $vendor;

  /**
   * [model configurations loaded]
   * @var array
   */
  public $models;

  /**
   * [protected description]
   * @var \codename\core\config
   */
  protected $environment;

  /**
   * [protected description]
   * @var \codename\architect\dbdoc\modeladapter
   */
  protected $adapters;

  /**
   * [init description]
   * @return [type] [description]
   */
  public function init() {

    $foreignAppstack = app::makeForeignAppstack($this->vendor, $this->app);
    $modelConfigurations = app::getModelConfigurations($this->vendor, $this->app, '', $foreignAppstack);
    $modelList = array();

    foreach($modelConfigurations as $schema => $models) {
      foreach($models as $modelname => $modelConfig) {
        $modelList[] = array(
          'identifier' => "{$schema}_{$modelname}",
          'model' => $modelname,
          'vendor' => $this->vendor,
          'app' => $this->app,
          'schema' => $schema,
          'driver' => 'dummy value',
          'config' => $modelConfig[0] // ??
        );
      }
    }

    $this->modelList = $modelList;

    // Load this file by default - plus inheritance
    // 'config/environment.json'
    $this->environment = (new \codename\core\config\json('config/environment.json', true, true, $foreignAppstack))->get();

    // NOTE:
    // We're using architect_ prefix by default!

    foreach($this->modelList as $m) {

      $this->adapters[] = new \codename\architect\dbdoc\modeladapter\sql\mysql(
        $this,
        $m['schema'],
        $m['model'],
        new \codename\core\config($m['config']),
        new \codename\architect\config\environment($this->environment, 'architect_' . app::getEnv())
      );

    }
  }


  /**
   * [getAdapter description]
   * @param  string                                 $schema [description]
   * @param  string                                 $model  [description]
   * @return \codename\architect\dbdoc\modeladapter         [description]
   */
  public function getAdapter(string $schema, string $model) : \codename\architect\dbdoc\modeladapter {
    foreach($this->adapters as $adapter) {
      if($adapter->schema == $schema && $adapter->model == $model) {
        return $adapter;
      }
    }
    return null;
  }

  /**
   * [run description]
   * @param  boolean $exec [description]
   * @return [type]        [description]
   */
  public function run(bool $exec = false) {

    foreach($this->adapters as $dbdoc_ma) {
      $tasks = $dbdoc_ma->runDiagnostics();

      if($exec) {
        foreach($tasks as $t) {
          echo("executing task ... ");
          $t->run();
        }
      }
    }

  }

}