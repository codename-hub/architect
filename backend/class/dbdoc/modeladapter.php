<?php

namespace codename\architect\dbdoc;

use codename\architect\config\environment;
use codename\core\config;
use ReflectionClass;

/**
 * dynamic dbdoc model adapter
 * @package architect
 */
abstract class modeladapter
{
    /**
     * model configuration
     * @var config
     */
    public config $config;
    /**
     * environment configuration
     * @var environment
     */
    public environment $environment;
    /**
     * model schema
     * @var string
     */
    public string $schema;
    /**
     * model name
     * @var string
     */
    public string $model;
    /**
     * [parent dbdoc instance]
     * @var dbdoc
     */
    public dbdoc $dbdoc;
    /**
     * plugin execution queue
     * @var null|array
     */
    protected ?array $executionQueue = [];

    /**
     * Creates a new structural model for DDL
     */
    public function __construct(dbdoc $dbdocInstance, string $schema, string $model, config $config, environment $environment)
    {
        $this->dbdoc = $dbdocInstance;
        $this->schema = $schema;
        $this->model = $model;
        $this->config = $config;
        $this->environment = $environment;
    }

    /**
     * get compatible driver name
     * @return string
     */
    abstract public function getDriverCompat(): string;

    /**
     * at the moment, this just puts out the identifier
     * (model)
     * @return string [description]
     */
    public function getIdentifier(): string
    {
        return "$this->schema.$this->model";
    }

    /**
     * [runDiagnostics description]
     * @return task[] [description]
     */
    public function runDiagnostics(): array
    {
        // load plugins
        foreach ($this->getPlugins() as $pluginIdentifier) {
            $plugin = $this->getPluginInstance($pluginIdentifier);
            if ($plugin != null) {
                $this->addToQueue($plugin);
            }
        }

        $tasks = [];

        // loop through unshift
        $plugin = $this->getNextQueuedPlugin();

        while ($plugin != null) {
            $tasks = array_merge($tasks, $plugin->Compare());
            $plugin = $this->getNextQueuedPlugin();
        }

        return $tasks;
    }

    /**
     * [getPlugins description]
     * @return string[]
     */
    public function getPlugins(): array
    {
        return [];
    }

    /**
     * [getPluginInstance description]
     * @param string $pluginIdentifier [description]
     * @param array $parameter [description]
     * @param bool $isVirtual
     * @return plugin|null [description]
     */
    public function getPluginInstance(string $pluginIdentifier, array $parameter = [], bool $isVirtual = false): ?plugin
    {
        foreach ($this->getPluginCompat() as $compat) {
            $classname = "\\codename\\architect\\dbdoc\\plugin\\" . str_replace('_', '\\', $compat . '_' . $pluginIdentifier);
            if (class_exists($classname) && !(new ReflectionClass($classname))->isAbstract()) {
                return new $classname($this, $parameter, $isVirtual);
            }
        }
        return null;
    }

    /**
     * get compatible plugin namespaces
     * @return string[]
     */
    abstract public function getPluginCompat(): array;

    /**
     * @param plugin $plugin
     * @param bool $insertAtBeginning
     * @return void
     */
    public function addToQueue(plugin $plugin, bool $insertAtBeginning = false): void
    {
        if ($insertAtBeginning) {
            array_unshift($this->executionQueue, $plugin);
        } else {
            $this->executionQueue[] = $plugin;
        }
    }

    /**
     * [getNextQueuedPlugin description]
     * @return mixed [description]
     */
    protected function getNextQueuedPlugin(): mixed
    {
        return array_shift($this->executionQueue);
    }

    /**
     * [getStructure description]
     * @return structure [description]
     */
    // public function getStructure() : structure;
}
