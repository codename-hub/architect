<?php

namespace codename\architect\deploy;

use codename\core\config;

/**
 * deployment task base class
 */
abstract class task
{
    /**
     * the task's configuration
     * @var null|config
     */
    protected ?config $config = null;

    /**
     * the current calling deployment instance
     * @var null|deployment
     */
    protected ?deployment $deploymentInstance = null;

    /**
     * Initialize a new, pre-configured task object
     * @param deployment $deploymentInstance [current deployment instance (parent)]
     * @param string $name [tasks's real configuration]
     * @param config $config [tasks configuration ('config' key)]
     */
    public function __construct(deployment $deploymentInstance, string $name, config $config)
    {
        $this->deploymentInstance = $deploymentInstance;
        $this->config = $config;
        $this->handleConfig();
    }

    /**
     * [handleConfig description]
     * @return void [type] [description]
     */
    protected function handleConfig(): void
    {
        // Do stuff with me. Override me.
    }

    /**
     * returns the current deployment instance
     * @return deployment [description]
     */
    public function getDeploymentInstance(): deployment
    {
        return $this->deploymentInstance;
    }

    /**
     * [run description]
     * @return taskresult [description]
     */
    abstract public function run(): taskresult;
}
