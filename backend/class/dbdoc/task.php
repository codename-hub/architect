<?php
namespace codename\architect\dbdoc;
use codename\core\exception;
use codename\core\catchableException;

/**
 * task class
 * @package architect
 */
class task {

  /**
   * [TASK_TYPE_ERROR description]
   * @var integer
   */
  public const TASK_TYPE_ERROR = -1;

  /**
   * [TASK_TYPE_INFO description]
   * @var integer
   */
  public const TASK_TYPE_INFO = 0;

  /**
   * [TASK_TYPE_REQUIRED description]
   * @var integer
   */
  public const TASK_TYPE_REQUIRED = 1;

  /**
   * [TASK_TYPE_SUGGESTED description]
   * @var integer
   */
  public const TASK_TYPE_SUGGESTED = 2;

  /**
   * [TASK_TYPE_OPTIONAL description]
   * @var integer
   */
  public const TASK_TYPE_OPTIONAL = 3;

  /**
   * [public description]
   * @var array
   */
  public const TASK_TYPES = array(
    self::TASK_TYPE_ERROR       => 'TASK_TYPE_ERROR',
    self::TASK_TYPE_INFO        => 'TASK_TYPE_INFO',
    self::TASK_TYPE_REQUIRED    => 'TASK_TYPE_REQUIRED',
    self::TASK_TYPE_SUGGESTED   => 'TASK_TYPE_SUGGESTED',
    self::TASK_TYPE_OPTIONAL    => 'TASK_TYPE_OPTIONAL',
  );

  /**
   * the adapter
   * @var string
   */
  public $plugin;

  /**
   * [protected description]
   * @var \codename\core\config
   */
  public $data;

  /**
   * [public description]
   * @var int
   */
  public $type;

  /**
   * [public description]
   * @var string
   */
  public $name;

  /**
   * [public description]
   * @var string
   */
  public $identifier = null;

  /**
   * task identifier prefixes
   * that should precede this task
   * @var string[]
   */
  public $precededBy = array();

  /**
   *
   */
  public function __construct(int $taskType, string $taskName, modeladapter $adapter, string $plugin, \codename\core\config $data)
  {
    $this->type = $taskType;
    $this->name = $taskName;
    $this->adapter = $adapter;
    $this->plugin = $plugin;
    $this->data = $data;
  }

  /**
   * [run description]
   * @return [type] [description]
   */
  public function run() {
    $plugin = $this->getPlugin($this->data->get() ?? array());
    if($plugin != null) {
      $plugin->runTask($this);
    } else {
      throw new exception(self::EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND, exception::$ERRORLEVEL_FATAL, $this);
    }
  }

  /**
   * [public description]
   * @var string
   */
  public const EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND = "EXCEPTION_DBDOC_TASK_RUN_PLUGIN_NOT_FOUND";

  /**
   * [getPlugin description]
   * @return plugin [description]
   */
  protected function getPlugin(array $data = array()) {
    $classname = "\\codename\\architect\\dbdoc\\plugin\\" . str_replace('_', '\\', $this->plugin);
    if(class_exists($classname) && !(new \ReflectionClass($classname))->isAbstract()) {
      $plugin = new $classname($this->adapter, $data);
      return $plugin;
    }
    return null;
  }

  /**
   * [getTaskTypeName description]
   * @return string [description]
   */
  public function getTaskTypeName() : string {
    return self::TASK_TYPES[$this->type];
  }

}