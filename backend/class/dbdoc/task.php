<?php

namespace codename\architect\dbdoc;

use codename\core\config;
use codename\core\exception;
use ReflectionClass;

/**
 * task class
 * @package architect
 */
class task
{
    /**
     * [TASK_TYPE_ERROR description]
     * @var int
     */
    public const TASK_TYPE_ERROR = -1;

    /**
     * [TASK_TYPE_INFO description]
     * @var int
     */
    public const TASK_TYPE_INFO = 0;

    /**
     * [TASK_TYPE_REQUIRED description]
     * @var int
     */
    public const TASK_TYPE_REQUIRED = 1;

    /**
     * [TASK_TYPE_SUGGESTED description]
     * @var int
     */
    public const TASK_TYPE_SUGGESTED = 2;

    /**
     * [TASK_TYPE_OPTIONAL description]
     * @var int
     */
    public const TASK_TYPE_OPTIONAL = 3;

    /**
     * [public description]
     * @var array
     */
    public const TASK_TYPES = [
      self::TASK_TYPE_ERROR => 'TASK_TYPE_ERROR',
      self::TASK_TYPE_INFO => 'TASK_TYPE_INFO',
      self::TASK_TYPE_REQUIRED => 'TASK_TYPE_REQUIRED',
      self::TASK_TYPE_SUGGESTED => 'TASK_TYPE_SUGGESTED',
      self::TASK_TYPE_OPTIONAL => 'TASK_TYPE_OPTIONAL',
    ];
    /**
     * [public description]
     * @var string
     */
    public const EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND = "EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND";
    /**
     * the adapter
     * @var string
     */
    public string $plugin;
    /**
     * [protected description]
     * @var config
     */
    public config $data;
    /**
     * [public description]
     * @var int
     */
    public int $type;
    /**
     * [public description]
     * @var string
     */
    public string $name;
    /**
     * [public description]
     * @var null|string
     */
    public ?string $identifier = null;
    /**
     * task identifier prefixes
     * that should precede this task
     * @var array
     */
    public array $precededBy = [];
    /**
     * @var modeladapter
     */
    private modeladapter $adapter;

    /**
     *
     */
    public function __construct(int $taskType, string $taskName, modeladapter $adapter, string $plugin, config $data)
    {
        $this->type = $taskType;
        $this->name = $taskName;
        $this->adapter = $adapter;
        $this->plugin = $plugin;
        $this->data = $data;
    }

    /**
     * [run description]
     * @return void [type] [description]
     * @throws exception
     */
    public function run(): void
    {
        $plugin = $this->getPlugin($this->data->get() ?? []);
        if ($plugin != null) {
            $plugin->runTask($this);
        } else {
            throw new exception(self::EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND, exception::$ERRORLEVEL_FATAL, $this);
        }
    }

    /**
     * [getPlugin description]
     * @param array $data
     * @return plugin|null [description]
     */
    protected function getPlugin(array $data = []): ?plugin
    {
        $classname = "\\codename\\architect\\dbdoc\\plugin\\" . str_replace('_', '\\', $this->plugin);
        if (class_exists($classname) && !(new ReflectionClass($classname))->isAbstract()) {
            return new $classname($this->adapter, $data);
        }
        return null;
    }

    /**
     * [getTaskTypeName description]
     * @return string [description]
     */
    public function getTaskTypeName(): string
    {
        return self::TASK_TYPES[$this->type];
    }
}
