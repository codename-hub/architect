<?php
namespace codename\architect\dbdoc;

/**
 * abstract plugin class
 * @package architect
 */
abstract class plugin {

  /**
   * the adapter
   * @var modeladapter
   */
  protected $adapter;

  /**
   * [protected description]
   * @var array
   */
  protected $parameter;

  /**
   *
   */
  public function __construct(modeladapter $adapter, array $parameter = array())
  {
    $this->parameter = $parameter;
    $this->adapter = $adapter;
  }

  /**
   * gets the model definition data for this plugin
   */
  public abstract function getDefinition();

  /**
   * gets the current structure, retrieved via the adapter
   */
  public abstract function getStructure();

  /**
   * do the comparison job
   * @return task[]
   */
  public function Compare() : array{
    return array();
  }

  /**
   * [runTask description]
   * @param  \codename\core\config $taskConfig [description]
   * @return [type]                            [description]
   */
  public function runTask(task $task) {

  }

  /**
   * [getPluginIdentifier description]
   * @return string [description]
   */
  public function getPluginIdentifier() : string {
    return str_replace('\\', '_', str_replace('codename\\architect\\dbdoc\\plugin\\', '', get_class($this)));
  }

  /**
   * [createTask description]
   * @param  [type] $taskType [description]
   * @param  string $taskName [description]
   * @param  array  $config   [description]
   * @return [type]           [description]
   */
  protected function createTask(int $taskType = task::TASK_TYPE_INFO, string $taskName, array $config = array()) {
    return new \codename\architect\dbdoc\task($taskType, $taskName, $this->adapter, $this->getPluginIdentifier(), new \codename\core\config($config));
  }

}