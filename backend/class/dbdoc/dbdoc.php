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
   * returns the current app
   * @return string
   */
  public function getApp() : string {
    return $this->app;
  }

  /**
   * [protected description]
   * @var string
   */
  protected $vendor;

  /**
   * returns the current vendor
   * @return string
   */
  public function getVendor() : string {
    return $this->vendor;
  }

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
          // 'driver' => 'dummy value',
          'config' => $modelConfig[0] // ??
        );
      }
    }

    $this->models = $modelList;

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
    foreach($this->models as &$m) {

      // skip models without connection
      /* if(empty($m['config']['connection'])) {
        continue;
      }*/
      /*
      $this->adapters[] = new \codename\architect\dbdoc\modeladapter\sql\mysql(
        $this,
        $m['schema'],
        $m['model'],
        new \codename\core\config($m['config']),
        new \codename\architect\config\environment($this->environment->get(), $prefixedEnvironmentName) // prefixed environment name: e.g. architect_dev, see above
      );*/

      $modelAdapter = $this->getModelAdapter(
        $m['schema'],
        $m['model'],
        $m['config'],
        new \codename\architect\config\environment($this->environment->get(), $prefixedEnvironmentName)
      );

      if($modelAdapter != null) {
        $this->adapters[] = $modelAdapter;
        $m['driver'] = get_class($modelAdapter);
      } else {
        // error?
      }
    }
  }

  /**
   * translate env config drivers to namespaced modeladapters
   * @var [type]
   */
  protected static $driverTranslation = array(
    'mysql' => 'sql\\mysql',
    'mysql' => 'sql\\mysql',
  );

  /**
   * [getModelAdapter description]
   * @param  string                                 $schema [description]
   * @param  string                                 $model  [description]
   * @param  array                                  $config [description]
   * @param  \codename\architect\config\environment     $env    [description]
   * @return \codename\architect\dbdoc\modeladapter         [description]
   */
  protected function getModelAdapter(string $schema, string $model, array $config, \codename\architect\config\environment $env) {

    // fallback adapter
    $driver = 'bare';

    if(!empty($config['connection'])) {
      // explicit connection.
      // we can identify the driver used
      $envDriver = $env->get('database>'.$config['connection'].'>driver');
      $driver = self::$driverTranslation[$envDriver] ?? null;
    }

    if($driver == null) {
      return null;
    }

    $class = '\\codename\\architect\\dbdoc\\modeladapter\\' . $driver;

    if(class_exists($class)) {
      return new $class(
        $this,
        $schema,
        $model,
        new \codename\core\config($config),
        $env // prefixed environment name: e.g. architect_dev, see above
      );
    } else {
      // unknown driver
    }

    return null;
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
  public function getAdapter(string $schema, string $model, string $app = '', string $vendor = '') {
    $app = ($app == '') ? $this->getApp() : $app;
    $vendor = ($vendor == '') ? $this->getVendor() : $vendor;

    if(($this->getApp() != $app) || ($this->getVendor() != $vendor)) {
      // get a foreign adapter
      // init a new dbdoc instance
      $foreignDbDoc = new self($app, $vendor);
      return $foreignDbDoc->getAdapter($schema, $model, $app, $vendor);
    }
    foreach($this->adapters as $adapter) {
      if($adapter->schema == $schema && $adapter->model == $model) {
        return $adapter;
      }
    }
    return null;
  }

  /**
   * stable usort function
   * @var [type]
   */
  protected static function stable_usort(array &$array, $value_compare_func)
	{
		$index = 0;
		foreach ($array as &$item) {
			$item = array($index++, $item);
		}
		$result = usort($array, function($a, $b) use($value_compare_func) {
			$result = call_user_func($value_compare_func, $a[1], $b[1]);
			return $result == 0 ? $a[0] - $b[0] : $result;
		});
		foreach ($array as &$item) {
			$item = $item[1];
		}
		return $result;
	}

  /**
   * [uasort description]
   * @param  array  $array              [description]
   * @param  [type] $value_compare_func [description]
   * @return [type]                     [description]
   */
  protected static function stable_uasort(array &$array, $value_compare_func)
	{
		$index = 0;
		foreach ($array as &$item) {
			$item = array($index++, $item);
		}
		$result = uasort($array, function($a, $b) use($value_compare_func) {
			$result = call_user_func($value_compare_func, $a[1], $b[1]);
			return $result == 0 ? $a[0] - $b[0] : $result;
		});
		foreach ($array as &$item) {
			$item = $item[1];
		}
		return $result;
	}

  /**
   * [run description]
   * @param  boolean  $exec       [execute the tasks]
   * @param  int[]    $exec_tasks [limit execution to specific task types. task::TASK_TYPE_...]
   * @return array                [some data]
   */
  public function run(bool $exec = false, array $exec_tasks = array( )) : array {

    $tasks = array();

    foreach($this->adapters as $dbdoc_ma) {

      $newTasks = $dbdoc_ma->runDiagnostics();
      $filteredTasks = array();

      // do some intelligent comparison between existing and to-be-merged tasks to cut out duplicates
      foreach($newTasks as $newTask) {
        $duplicate = false;
        if($newTask->identifier != null) {
          foreach($tasks as $existingTask) {
            if($newTask->identifier == $existingTask->identifier) {
              // mark as duplicate
              $duplicate = true;
              break;
            }
          }
        }
        if(!$duplicate) {
          $filteredTasks[] = $newTask;
        }
      }

      $tasks = array_merge($tasks, $filteredTasks);
    }

    // priority sorting, based on precededBy value
    $sort_success = self::stable_usort($tasks, function(task $taskA, task $taskB) {

      /*
      echo("<hr>");
      echo("<br>{$taskA->name} vs. {$taskB->name}");
      echo("<br>taskA: {$taskA->identifier}");
      echo("<br>taskB: {$taskB->identifier}");
      echo("<pre>TaskA.precededBy:\n". print_r($taskA->precededBy,true) . "\nTaskB.precededBy:\n". print_r($taskB->precededBy,true) ."</pre>");
      */

      if((count($taskA->precededBy) == 0) && (count($taskB->precededBy) == 0)) {
        // no precendence defined
        // echo("<br>{$taskA->name} == {$taskB->name}");
        return 0;
      }


      /*
      if(in_array($taskB->identifier, $taskA->precededBy)) {
        echo("<br>{$taskA->name} < {$taskB->name}");
        return -1;
      }
      */

      /* if(in_array($taskA->identifier, $taskB->precededBy)) {
        echo("<br>{$taskA->name} > {$taskB->name}");
        return 1;
      }*/

      // check if B requires A
      foreach($taskB->precededBy as $identifier) {
        // echo("<br> -- comparing {$identifier} ___ AND ___ {$taskA->identifier}");
        if( (strlen($taskA->identifier) >= strlen($identifier)) && strpos($taskA->identifier, $identifier) === 0) {
          /*
          echo("<br>");
          echo("<br>{$taskA->name} vs. {$taskB->name}");
          echo("<br>taskA: {$taskA->identifier}");
          echo("<br>taskB: {$taskA->identifier}");
          */
          // echo("<br> -- {$taskA->name} > {$taskB->name}");

          return -1;
        }
      }

      // check if A requires B
      foreach($taskA->precededBy as $identifier) {
        // echo("<br> -- comparing {$identifier} ___ AND ___ {$taskB->identifier}");
        if( (strlen($taskB->identifier) >= strlen($identifier)) && strpos($taskB->identifier, $identifier) === 0) {
          /*
          echo("<br>");
          echo("<br>{$taskA->name} vs. {$taskB->name}");
          echo("<br>taskA: {$taskA->identifier}");
          echo("<br>taskB: {$taskA->identifier}");
          */
          // echo("<br> -- {$taskA->name} < {$taskB->name}");
          return +1;
        }
      }

      /*
      echo("<br>");
      echo("<br>{$taskA->name} vs. {$taskB->name}");
      echo("<br>taskA: {$taskA->identifier}");
      echo("<br>taskB: {$taskB->identifier}");
      echo("<pre>TaskA.precededBy:\n". print_r($taskA->precededBy,true) . "\nTaskB.precededBy:\n". print_r($taskB->precededBy,true) ."</pre>");
      */

      // echo("<br> -- {$taskA->name} == {$taskB->name}");
      // echo("<br>{$taskA->name} == {$taskB->name} : " . var_export($taskA->precededBy,true) . var_export($taskB->precededBy,true));
      // echo("<br> -- equal (no precedence).");

      return +1; // was 0
    });

    if(!$sort_success) {
      echo("Sort unsuccessful!");
      die();
    }

    $availableTasks = array();
    $availableTaskTypes = array();
    $executedTasks = array();

    foreach($tasks as $t) {
      // if($t->type == task::TASK_TYPE_REQUIRED) {

      if($exec) {
        // validate the task type itself
        if(count($errors = app::getValidator('number_tasktype')->reset()->validate($t->type)) === 0) {
          $taskType = task::TASK_TYPES[$t->type];

          // check if requested
          if(in_array($t->type, $exec_tasks)) {
            // echo("<br>executing {$taskType} task ... ");
            $executedTasks[] = $t;

            // Run the task!
            $t->run();
          } else {
            // echo("<br>skipping {$taskType} task ... ");
            $availableTaskTypes[] = $t->type;
            $availableTasks[] = $t;
          }
        } else {
          // echo("<br>invalid {$taskType}, skipping ... ");
        }
      } else {
        $availableTaskTypes[] = $t->type;
        $availableTasks[] = $t;
      }

    }

    $availableTaskTypes = array_unique($availableTaskTypes);
    // translate:
    $availableTaskTypesAssoc = array();
    foreach($availableTaskTypes as $type) {
      $availableTaskTypesAssoc[task::TASK_TYPES[$type]] = $type;
    }

    return array(
      'tasks' => $tasks,
      'available_tasks' => $availableTasks,
      'available_task_types' => $availableTaskTypesAssoc,
      'executed_tasks' => $executedTasks,
      'executed_task_types' => $exec_tasks
    );

  }

}
