<?php

namespace codename\architect\deploy;

use codename\architect\app;
use codename\architect\config\environment;
use codename\core\config;
use codename\core\config\json;
use codename\core\exception;
use DateTime;
use ReflectionException;

/**
 * deployment handler
 * runs deployment tasks
 */
class deployment
{
    /**
     * name of this deployment (e.g. file basename)
     * @var null|string
     */
    protected ?string $name = null;

    /**
     * vendor name of app
     * @var null|string
     */
    protected ?string $vendor = null;

    /**
     * app's name
     * @var null|string
     */
    protected ?string $app = null;

    /**
     * This instance's deployment configuration
     * @var null|config
     */
    protected ?config $config = null;
    /**
     * appstack of the foreign app
     * @var null|array
     */
    protected ?array $foreignAppstack = null;
    /**
     * environment config of foreign app
     * @var null|config
     */
    protected ?config $environment = null;
    /**
     * virtualized environment config of foreign app
     * @var null|environment
     */
    protected ?environment $virtualEnvironment = null;
    /**
     * task instances
     * @var task[]
     */
    protected array $tasks = [];

    /**
     * Initialize a new deployment instance
     * @param string $vendor [description]
     * @param string $app [description]
     * @param string $name [description]
     * @param config $config [description]
     * @throws ReflectionException
     * @throws exception
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
     * creates the list of task instances for the deployment
     * @return void [type] [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function createDeploymentTasks(): void
    {
        if (!$this->config->exists('tasks')) {
            throw new exception('DEPLOYMENT_CONFIGURATION_TASKS_KEY_MISSING', exception::$ERRORLEVEL_ERROR, [
              'app' => $this->app,
              'vendor' => $this->vendor,
              'name' => $this->name,
            ]);
        }
        foreach ($this->config->get('tasks') as $taskName => $taskConfig) {
            $this->tasks[$taskName] = $this->createTaskInstance($taskName, $taskConfig);
        }
    }

    /**
     * creates a task instance using a given name & config
     * @param string $name [description]
     * @param array $task [description]
     * @return task           [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function createTaskInstance(string $name, array $task): task
    {
        $class = app::getInheritedClass('deploy_task_' . $task['type']);
        $config = new config($task['config']);
        return new $class($this, $name, $config);
    }

    /**
     * Creates a new deployment instance from a given config file
     * @param string $vendor
     * @param string $app
     * @param string $deploymentName
     * @return deployment
     * @throws ReflectionException
     * @throws exception
     */
    public static function createFromConfig(string $vendor, string $app, string $deploymentName): deployment
    {
        // get app's homedir
        $appdir = app::getHomedir($vendor, $app);
        $fs = app::getFilesystem();

        $dir = $appdir . 'config/deployment';

        //
        // Stop, if deployment config directory doesn't exist
        //
        if (!$fs->dirAvailable($dir)) {
            throw new exception('DEPLOYMENT_CONFIG_DIRECTORY_DOES_NOT_EXIST', exception::$ERRORLEVEL_FATAL, $dir);
        }

        $file = $dir . '/' . $deploymentName . '.json';

        if (!$fs->fileAvailable($file)) {
            throw new exception('DEPLOYMENT_CONFIG_FILE_DOES_NOT_EXIST', exception::$ERRORLEVEL_FATAL, $file);
        }

        $config = new json($file);
        return new self($vendor, $app, $deploymentName, $config);
    }

    /**
     * returns the current virtual environment config
     * of the app being deployed
     * using the ENV the architect is running in
     * @return environment [description]
     * @throws ReflectionException
     * @throws exception
     */
    public function getVirtualEnvironment(): environment
    {
        if (!$this->virtualEnvironment) {
            $this->virtualEnvironment = new environment($this->getEnvironment()->get(), \codename\core\app::getEnv());
        }
        return $this->virtualEnvironment;
    }

    /**
     * returns the complete environment config
     * of the app being deployed
     * @return config [description]
     * @throws ReflectionException
     * @throws exception
     */
    public function getEnvironment(): config
    {
        if (!$this->environment) {
            // TODO/CHECK: should we really inherit? Yes, we should.
            $this->environment = new json('config/environment.json', true, true, $this->getAppstack());
        }
        return $this->environment;
    }

    /**
     * get app's appstack
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function getAppstack(): array
    {
        if (!$this->foreignAppstack) {
            $this->foreignAppstack = app::makeForeignAppstack($this->getVendor(), $this->getApp());
        }
        return $this->foreignAppstack;
    }

    /**
     * current vendor
     * @return string [description]
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * current app
     * @return string [description]
     */
    public function getApp(): string
    {
        return $this->app;
    }

    /**
     * runs the deployment process
     * @return deploymentresult [description]
     */
    public function run(): deploymentresult
    {
        $deploymentResultData = [
          'date' => new DateTime('now'),
          'taskresult' => [],
        ];

        foreach ($this->tasks as $taskName => $task) {
            $result = $task->run();
            $deploymentResultData['taskresult'][$taskName] = $result;
        }

        return new deploymentresult($deploymentResultData);
    }
}
