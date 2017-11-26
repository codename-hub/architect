<?php
namespace codename\architect\dbdoc;
use \codename\architect\app;
use \codename\core\exception;

/**
 * dbdoc
 * @package architect
 */
class dbdoc  {

  /**
   * Prefix used for getting the right environment configuration
   * suitable for 'architecting' the app stuff.
   *
   * Information:
   * As we'd like to make systems as secure as possible,
   * we're providing two types of global system environment configuration sets.
   * The first one is simply the real-world-production-state-config.
   *
   * You'd call it ... *suprise, surprise* ... 'production'.
   * While this configuration should only provide _basic_ access to resources (like the database)
   * e.g. only SELECT, UPDATE, JOIN, ... , DELETE (if, at all!)
   * You have to have another set of credentials to be used for the deployment state
   * of the application.
   *
   * Therefore, you __have__ to provide a second configuration.
   * For example, you could either provide the same credentials, if you're using
   * ROOT access in production systems for standard DB access.
   *
   * Nah, you really shouln't do that.
   *
   * Instead, you supply those limited configs in production
   * and root credentials needed for some structural stuff
   * during deployment.
   *
   * We'd call it
   *
   * architect_production
   *
   * To sum it up, modify your environment.json to supply one more prefixed key
   * for each env-key used. We assume you're using "dev" and "production".
   * Therefore, you need to supply "architect_dev" and "architect_production".
   *
   * IMPORTANT HINT:
   * Simply supply root credentials in architect_-prefixed configs.
   * You don't have to create the credentials defined in the unprefixed configs
   * As the architect does this for you.
   *
   * Enjoy.
   *
   * @var string
   */
  protected const ARCHITECT_ENV_PREFIX = 'architect_';

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
  protected $adapters = array();

  /**
   * [init description]
   * @return [type] [description]
   */
  public function init() {

    // should init empty model array!
    $this->models = array();

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
    $this->environment = new \codename\core\config\json('config/environment.json', true, true, $foreignAppstack);;

    // construct the prefixed environment config (used for deployment)
    $prefixedEnvironmentName = self::ARCHITECT_ENV_PREFIX . app::getEnv();

    // check for existance
    if(!$this->environment->exists($prefixedEnvironmentName)) {
      // this is needed.
      // warn user/admin we're missing an important configuration part.
    	throw new exception(self::EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG, exception::$ERRORLEVEL_FATAL, $prefixedEnvironmentName);
    }

    // initialize model adapters
    foreach($this->modelList as $m) {
      $this->adapters[] = new \codename\architect\dbdoc\modeladapter\sql\mysql(
        $this,
        $m['schema'],
        $m['model'],
        new \codename\core\config($m['config']),
        new \codename\architect\config\environment($this->environment->get(), $prefixedEnvironmentName) // prefixed environment name: e.g. architect_dev, see above
      );
    }
  }

  /**
   * Exception thrown if we're missing a specific env config key (with the deployment-mode prefix)
   * @var string
   */
  const EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG = 'EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG';

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
