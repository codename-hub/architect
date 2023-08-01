<?php

namespace codename\architect\dbdoc;

use codename\architect\app;
use codename\architect\config\environment;
use codename\core\catchableException;
use codename\core\config;
use codename\core\config\json;
use codename\core\errorstack;
use codename\core\exception;
use ReflectionException;

/**
 * dbdoc
 * @package architect
 */
class dbdoc
{
    /**
     * Prefix used for getting the right environment configuration
     * suitable for 'architecting' the app stuff.
     *
     * Information:
     * As we'd like to make systems as secure as possible,
     * we're providing two types of global system environment configuration sets.
     * The first one is simply the real-world-production-state-config.
     *
     * You'd call it ... *surprise, surprise* ... 'production'.
     * While this configuration should only provide _basic_ access to resources (like the database)
     * e.g. only SELECT, UPDATE, JOIN, ... , DELETE (if, at all!)
     * You have to have another set of credentials to be used for the deployment state
     * of the application.
     *
     * Therefore, you __have__ to provide a second configuration.
     * For example, you could either provide the same credentials, if you're using
     * ROOT access in production systems for standard DB access.
     *
     * Nah, you really shouldn't do that.
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
     * You don't have to create the credentials defined in the un-prefixed configs
     * As the architect does this for you.
     *
     * Enjoy.
     *
     * @var string
     */
    protected const ARCHITECT_ENV_PREFIX = 'architect_';
    /**
     * Exception thrown if we're missing a specific env config key (with the deployment-mode prefix)
     * @var string
     */
    public const EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG = 'EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG';
    /**
     * translate env config drivers to namespaced modeladapter
     * @var array
     */
    protected static array $driverTranslation = [
      'mysql' => 'sql\\mysql',
      'sqlite' => 'sql\\sqlite',
    ];
    /**
     * [model configurations loaded]
     * @var array
     */
    public array $models;
    /**
     * [protected description]
     * @var string
     */
    protected string $app;
    /**
     * [protected description]
     * @var string
     */
    protected string $vendor;
    /**
     * [protected description]
     * @var string
     */
    protected string $env;
    /**
     * [protected description]
     * @var null|config
     */
    protected ?config $environment;
    /**
     * [protected description]
     * @var array
     */
    protected array $adapters = [];
    /**
     * [protected description]
     * @var null|errorstack
     */
    protected ?errorstack $errorstack = null;

    /**
     * @param string $app
     * @param string $vendor
     * @param string|null $env [override environment by name]
     * @param config|null $envConfig [override environment by config]
     * @throws ReflectionException
     * @throws exception
     */
    public function __construct(string $app, string $vendor, ?string $env = null, ?config $envConfig = null)
    {
        $this->errorstack = new errorstack('DBDOC');
        $this->app = $app;
        $this->vendor = $vendor;
        $this->env = $env ?? app::getEnv();
        $this->environment = $envConfig ?? null;
        $this->init();
    }

    /**
     * [getEnv description]
     * @return string [description]
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * [init description]
     * @return void [type] [description]
     * @throws ReflectionException
     * @throws exception
     */
    public function init(): void
    {
        // should init empty model array!
        $this->models = [];

        $foreignAppstack = app::makeForeignAppstack($this->vendor, $this->app);
        $modelConfigurations = app::getModelConfigurations($this->vendor, $this->app, '', $foreignAppstack);
        $modelList = [];

        foreach ($modelConfigurations as $schema => $models) {
            foreach ($models as $modelname => $modelConfig) {
                $modelList[] = [
                  'identifier' => $schema . '_' . $modelname,
                  'model' => $modelname,
                  'vendor' => $this->vendor,
                  'app' => $this->app,
                  'schema' => $schema,
                  'config' => $modelConfig[0],
                ];
            }
        }

        $this->models = $modelList;

        // Load this file by default - plus inheritance
        // 'config/environment.json'
        $this->environment = $this->environment ?? new json('config/environment.json', true, true, $foreignAppstack);

        // construct the prefixed environment config (used for deployment)
        $prefixedEnvironmentName = $this->getPrefixedEnvironmentName();

        // check for existence
        if (!$this->environment->exists($prefixedEnvironmentName)) {
            // this is needed.
            // warn user/admin we're missing an important configuration part.
            // throw new exception(self::EXCEPTION_ARCHITECT_MISSING_PREFIXED_ENVIRONMENT_CONFIG, exception::$ERRORLEVEL_FATAL, $prefixedEnvironmentName);
        }

        // initialize model adapters
        foreach ($this->models as &$m) {
            $modelAdapter = $this->getModelAdapter(
                $m['schema'],
                $m['model'],
                $m['config'],
                new environment($this->environment->get(), $prefixedEnvironmentName)
            );

            if ($modelAdapter != null) {
                $this->adapters[] = $modelAdapter;
                $m['driver'] = get_class($modelAdapter);
            }
        }

        // display errors on need!

        if (count($errors = $this->errorstack->getErrors()) > 0) {
            throw new exception('DBDOC_ERRORS', exception::$ERRORLEVEL_FATAL, $errors);
        }
    }

    /**
     * [getPrefixedEnvironmentName description]
     * @return string [description]
     */
    protected function getPrefixedEnvironmentName(): string
    {
        return self::ARCHITECT_ENV_PREFIX . $this->env;
    }

    /**
     * [getModelAdapter description]
     * @param string $schema [description]
     * @param string $model [description]
     * @param array $config [description]
     * @param environment $env [description]
     * @return null|modeladapter         [description]
     * @throws ReflectionException
     * @throws exception
     */
    public function getModelAdapter(string $schema, string $model, array $config, environment $env): ?modeladapter
    {
        // validate model configuration
        if (count($errors = app::getValidator('structure_config_model')->reset()->validate($config)) > 0) {
            $this->errorstack->addErrors($errors);
            return null;
        }

        // fallback adapter
        $driver = 'bare';

        if (!empty($config['connection'])) {
            // explicit connection.
            // we can identify the driver used
            $envDriver = $env->get('database>' . $config['connection'] . '>driver');
            $driver = self::$driverTranslation[$envDriver] ?? null;
        }

        if ($driver == null) {
            return null;
        }

        $class = '\\codename\\architect\\dbdoc\\modeladapter\\' . $driver;

        if (class_exists($class)) {
            return new $class(
                $this,
                $schema,
                $model,
                new config($config),
                $env // prefixed environment name: e.g. architect_dev, see above
            );
        }

        return null;
    }

    /**
     * [uasort description]
     * @param array $array [description]
     * @param  [type] $value_compare_func [description]
     * @return bool [type]                     [description]
     */
    protected static function stable_uasort(array &$array, $value_compare_func): bool
    {
        $index = 0;
        foreach ($array as &$item) {
            $item = [$index++, $item];
        }
        $result = uasort($array, function ($a, $b) use ($value_compare_func) {
            $result = call_user_func($value_compare_func, $a[1], $b[1]);
            return $result == 0 ? $a[0] - $b[0] : $result;
        });
        foreach ($array as &$item) {
            $item = $item[1];
        }
        return $result;
    }

    /**
     * [getAdapter description]
     * @param string $schema [description]
     * @param string $model [description]
     * @param string $app [description]
     * @param string $vendor [description]
     * @return modeladapter         [description]
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function getAdapter(string $schema, string $model, string $app = '', string $vendor = ''): modeladapter
    {
        $app = ($app == '') ? $this->getApp() : $app;
        $vendor = ($vendor == '') ? $this->getVendor() : $vendor;

        if (($this->getApp() != $app) || ($this->getVendor() != $vendor)) {
            // get a foreign adapter
            // init a new dbdoc instance
            $foreignDbDoc = new self($app, $vendor);
            return $foreignDbDoc->getAdapter($schema, $model, $app, $vendor);
        }
        foreach ($this->adapters as $adapter) {
            if ($adapter->schema == $schema && $adapter->model == $model) {
                return $adapter;
            }
        }

        throw new catchableException('DBDOC_GETADAPTER_NOTFOUND', catchableException::$ERRORLEVEL_ERROR, [$schema, $model, $app, $vendor]);
    }

    /**
     * returns the current app
     * @return string
     */
    public function getApp(): string
    {
        return $this->app;
    }

    /**
     * returns the current vendor
     * @return string
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * [run description]
     * @param bool $exec [execute the tasks]
     * @param int[] $exec_tasks [limit execution to specific task types. task::TASK_TYPE_...]
     * @return array                [some data]
     * @throws ReflectionException
     * @throws exception
     */
    public function run(bool $exec = false, array $exec_tasks = []): array
    {
        $tasks = [];

        foreach ($this->adapters as $dbdoc_ma) {
            $newTasks = $dbdoc_ma->runDiagnostics();
            $filteredTasks = [];

            // do some intelligent comparison between existing and to-be-merged tasks to cut out duplicates
            foreach ($newTasks as $newTask) {
                $duplicate = false;
                if ($newTask->identifier != null) {
                    foreach ($tasks as $existingTask) {
                        if ($newTask->identifier == $existingTask->identifier) {
                            // mark as duplicate
                            $duplicate = true;
                            break;
                        }
                    }
                }
                if (!$duplicate) {
                    $filteredTasks[] = $newTask;
                }
            }

            $tasks = array_merge($tasks, $filteredTasks);
        }

        // priority sorting, based on precededBy value
        $sort_success = self::stable_usort($tasks, function (task $taskA, task $taskB) {
            if ((count($taskA->precededBy) == 0) && (count($taskB->precededBy) == 0)) {
                // no precedence defined
                return 0;
            }

            // check if B requires A
            foreach ($taskB->precededBy as $identifier) {
                if ((strlen($taskA->identifier) >= strlen($identifier)) && str_starts_with($taskA->identifier, $identifier)) {
                    return -1;
                }
            }

            // check if A requires B
            foreach ($taskA->precededBy as $identifier) {
                if ((strlen($taskB->identifier) >= strlen($identifier)) && str_starts_with($taskB->identifier, $identifier)) {
                    return +1;
                }
            }

            return 0; // was 0 // was +1
        });

        if (!$sort_success) {
            echo("Sort unsuccessful!");
            die();
        }

        $availableTasks = [];
        $availableTaskTypes = [];
        $executedTasks = [];

        foreach ($tasks as $t) {
            if ($exec) {
                // validate the task type itself
                if (count(app::getValidator('number_tasktype')->reset()->validate($t->type)) === 0) {
                    // check if requested
                    if (in_array($t->type, $exec_tasks)) {
                        $executedTasks[] = $t;

                        // Run the task!
                        $t->run();
                    } else {
                        $availableTaskTypes[] = $t->type;
                        $availableTasks[] = $t;
                    }
                }
            } else {
                $availableTaskTypes[] = $t->type;
                $availableTasks[] = $t;
            }
        }

        $availableTaskTypes = array_unique($availableTaskTypes);
        // translate:
        $availableTaskTypesAssoc = [];
        foreach ($availableTaskTypes as $type) {
            $availableTaskTypesAssoc[task::TASK_TYPES[$type]] = $type;
        }

        return [
          'tasks' => $tasks,
          'available_tasks' => $availableTasks,
          'available_task_types' => $availableTaskTypesAssoc,
          'executed_tasks' => $executedTasks,
          'executed_task_types' => $exec_tasks,
        ];
    }

    /**
     * stable usort function
     * @param array $array
     * @param $value_compare_func
     * @return bool
     */
    protected static function stable_usort(array &$array, $value_compare_func): bool
    {
        $index = 0;
        foreach ($array as &$item) {
            $item = [$index++, $item];
        }
        $result = usort($array, function ($a, $b) use ($value_compare_func) {
            $result = call_user_func($value_compare_func, $a[1], $b[1]);
            return $result == 0 ? $a[0] - $b[0] : $result;
        });
        foreach ($array as &$item) {
            $item = $item[1];
        }
        return $result;
    }
}
