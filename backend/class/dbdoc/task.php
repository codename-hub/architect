<?php
namespace codename\architect\dbdoc;

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
    $plugin = $this->getPlugin();
    if($plugin != null) {
      $plugin->runTask($this);
    }
  }

  /**
   * [getPlugin description]
   * @return plugin [description]
   */
  protected function getPlugin() {
    $classname = "\\codename\\architect\\dbdoc\\plugin\\" . str_replace('_', '\\', $this->plugin);
    if(class_exists($classname) && !(new \ReflectionClass($classname))->isAbstract()) {
      $plugin = new $classname($this->adapter);
      return $plugin;
    }
    return null;
  }

}