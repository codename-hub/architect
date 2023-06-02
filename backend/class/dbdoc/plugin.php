<?php

namespace codename\architect\dbdoc;

use codename\core\config;
use codename\core\event;

/**
 * abstract plugin class
 * @package architect
 */
abstract class plugin
{
    /**
     * event fired, if the comparison was successful
     * @var null|event
     */
    public ?event $onSuccess = null;
    /**
     * event fired, if the comparison failed
     * @var null|event
     */
    public ?event $onFail = null;
    /**
     * event fired, if the comparison was interrupted (!)
     * @var null|event
     */
    public ?event $onError = null;
    /**
     * the adapter
     * @var modeladapter
     */
    protected modeladapter $adapter;
    /**
     * [protected description]
     * @var array
     */
    protected array $parameter;
    /**
     * virtual mode
     * @var bool
     */
    protected bool $virtual = false;

    /**
     * @param modeladapter $adapter
     * @param array $parameter
     * @param bool $isVirtual
     */
    public function __construct(modeladapter $adapter, array $parameter = [], bool $isVirtual = false)
    {
        $this->initEvents();
        $this->virtual = $isVirtual;
        $this->parameter = $parameter;
        $this->adapter = $adapter;
    }

    /**
     * init events
     */
    private function initEvents(): void
    {
        $this->onSuccess = new event('PLUGIN_COMPARE_ON_SUCCESS');
        $this->onFail = new event('PLUGIN_COMPARE_ON_FAIL');
        $this->onError = new event('PLUGIN_COMPARE_ON_ERROR');
    }

    /**
     * gets the model definition data for this plugin
     */
    abstract public function getDefinition();

    /**
     * gets the current structure, retrieved via the adapter
     */
    abstract public function getStructure();

    /**
     * do the comparison job
     * @return task[]
     */
    public function Compare(): array
    {
        return [];
    }

    /**
     * Determines the plugin run type
     * virtual means, the plugin does not check the state
     * via getStructure.
     * This is useful for creating a complete run
     * without having to rely on re-runs,
     * because structures have to exist before
     * @return bool [description]
     */
    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    /**
     * [runTask description]
     * @param task $task
     * @return void [type]                            [description]
     */
    public function runTask(task $task): void
    {
    }

    /**
     * [createTask description]
     * @param int $taskType [task type ]
     * @param string $taskName [custom task name]
     * @param array $config [configuration]
     * @param array $precededBy
     * @return task [type]           [description]
     */
    protected function createTask(int $taskType, string $taskName, array $config = [], array $precededBy = []): task
    {
        $task = new task($taskType, $taskName, $this->adapter, $this->getPluginIdentifier(), new config($config));
        $task->precededBy = $precededBy;
        $task->identifier = "{$this->getTaskIdentifierPrefix()}_{$taskType}_{$taskName}_" . serialize($config);
        return $task;
    }

    /**
     * [getPluginIdentifier description]
     * @return string [description]
     */
    public function getPluginIdentifier(): string
    {
        return str_replace('\\', '_', str_replace('codename\\architect\\dbdoc\\plugin\\', '', get_class($this)));
    }

    /**
     * [getTaskIdentifierPrefix description]
     * @return string
     */
    protected function getTaskIdentifierPrefix(): string
    {
        return "{$this->adapter->dbdoc->getVendor()}_{$this->adapter->dbdoc->getApp()}_";
    }
}
