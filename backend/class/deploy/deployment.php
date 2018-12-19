<?php
namespace codename\architect\deploy;

use codename\architect\app;

use codename\core\config;
use codename\core\exception;

/**
 * deployment handler
 * runs deployment tasks
 */
class deployment {


  /**
  * name of this deployment (e.g. file basename)
  * @var string
  */
  protected $name = null;

  /**
  * vendor name of app
  * @var string
  */
  protected $vendor = null;

  /**
  * app's name
  * @var string
  */
  protected $app = null;

  /**
  * This instance's deployment configuration
  * @var config
  */
  protected $config = null;

  /**
   * Initialize a new deployment instance
   * @param string $vendor [description]
   * @param string $app    [description]
   * @param string $name   [description]
   * @param config $config [description]
   */
  public function __construct(string $vendor, string $app, string $name, config $config)
  {
    $this->vendor = $vendor;
    $this->app = $app;
    $this->name = $name;
    $this->config = $config;
    $this->createDeploymentTasks();
  }

  /**
   * current app
   * @return string [description]
   */
  public function getApp() : string {
    return $this->app;
  }

  /**
   * current vendor
   * @return string [description]
   */
  public function getVendor() : string {
    return $this->vendor;
  }

  /**
   * [protected description]
   * @var [type]
   */
  protected $foreignAppstack = null;

  /**
   * get app's appstack
   * @return array
   */
  public function getAppstack() : array {
    if(!$this->foreignAppstack) {
      $this->foreignAppstack = app::makeForeignAppstack($this->getVendor(), $this->getApp());
    }
    return $this->foreignAppstack;
  }

  /**
   * environment config of foreign app
   * @var \codename\core\config
   */
  protected $environment = null;

  /**
   * [getEnvironment description]
   * @return \codename\core\config [description]
   */
  public function getEnvironment() : \codename\core\config {
    if(!$this->environment) {
      // TODO/CHECK: should we really inherit? Yes, we should.
      $this->environment = new \codename\core\config\json('config/environment.json', true, true, $this->getAppstack());
    }
    return $this->environment;
  }

  /**
   * virtualized environment config of foreign app
   * @var \codename\architect\config\environment
   */
  protected $virtualEnvironment = null;

  /**
   * [getVirtualEnvironment description]
   * @return \codename\architect\config\environment [description]
   */
  public function getVirtualEnvironment() : \codename\architect\config\environment {
    if(!$this->virtualEnvironment) {
      $this->virtualEnvironment = new \codename\architect\config\environment($this->getEnvironment()->get(), \codename\core\app::getEnv());
    }
    return $this->virtualEnvironment;
  }

  /**
  * task instances
  * @var task[]
  */
  protected $tasks = [];


  /**
   * runs the deployment process
   * @return deploymentresult [description]
   */
  public function run() : deploymentresult {
    $deploymentResultData = [
      'date' => new \DateTime('now'),
      'taskresult' => []
    ];

    foreach($this->tasks as $taskName => $task) {
      $result = $task->run();
      $deploymentResultData['taskresult'][$taskName] = $result;
    }

    return new deploymentresult($deploymentResultData);
  }

  /**
   * [createDeploymentTasks description]
   * @return [type] [description]
   */
  protected function createDeploymentTasks() {
    if(!$this->config->exists('tasks')) {
      throw new exception('DEPLOYMENT_CONFIGURATION_TASKS_KEY_MISSING', exception::$ERRORLEVEL_ERROR, [
        'app'     => $this->app,
        'vendor'  => $this->vendor,
        'name'    => $this->name
      ]);
    }
    foreach($this->config->get('tasks') as $taskName => $taskConfig) {
      $this->tasks[$taskName] = $this->createTaskInstance($taskName, $taskConfig);
    }
  }

  /**
   * [createTaskInstance description]
   * @param  string $name   [description]
   * @param  array  $task   [description]
   * @return task           [description]
   */
  protected function createTaskInstance(string $name, array $task) : task {
    $class = app::getInheritedClass('deploy_task_'.$task['type']);
    $config = new \codename\core\config($task['config']);
    return new $class($this, $name, $config);
  }

  /**
   * Creates a new deployment instance from a given config file
   * @param  string     $vendor
   * @param  string     $app
   * @param  string     $deploymentName
   * @return deployment
   */
  public static function createFromConfig(string $vendor, string $app, string $deploymentName) : deployment {

    // get app's homedir
    $appdir = app::getHomedir($vendor, $app);
    $fs = app::getFilesystem();

    $dir = $appdir . 'config/deployment';

    //
    // Stop, if deployment config directory doesn't exist
    //
    if(!$fs->dirAvailable($dir)) {
      throw new exception('DEPLOYMENT_CONFIG_DIRECTORY_DOES_NOT_EXIST', exception::$ERRORLEVEL_FATAL, $dir);
    }

    $file = $dir.'/'.$deploymentName.'.json';

    if(!$fs->fileAvailable($file)) {
      throw new exception('DEPLOYMENT_CONFIG_FILE_DOES_NOT_EXIST', exception::$ERRORLEVEL_FATAL, $file);
    }

    $config = new \codename\core\config\json($file);
    return new self($vendor, $app, $deploymentName, $config);


    // // get all deployment files available
    // $files = app::getFilesystem()->dirList( $dir );
    //
    // $file = $dir . '/' . $f;
    //
    // // check for .json extension
    // $fileInfo = new \SplFileInfo($file);
    // if($fileInfo->getExtension() === 'json') {
    //   // get the model filename w/o extension
    //   $deploymentBasename = $fileInfo->getBasename('.json');
    //   $config = new \codename\core\config\json($fileInfo->getRealPath());
    //   return new self($config);
    // }

  }


}
