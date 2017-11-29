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
  public function __construct(modeladapter $adapter, array $parameter = array(), bool $isVirtual = false)
  {
    $this->initEvents();
    $this->virtual = $isVirtual;
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
   * Determines the plugin run type
   * virtual means, the plugin does not check the state
   * via getStructure.
   * This is useful for creating a complete run
   * without having to rely on re-runs,
   * because structures have to exist before
   * @return bool [description]
   */
  public function isVirtual() : bool {
    return $this->virtual;
  }

  /**
   * virtual mode
   * @var bool
   */
  protected $virtual = false;

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
    $task = new \codename\architect\dbdoc\task($taskType, $taskName, $this->adapter, $this->getPluginIdentifier(), new \codename\core\config($config));
    $task->identifier = "{$this->getTaskIdentifierPrefix()}_{$taskType}_{$taskName}_". serialize($config);
    return $task;
  }

  /**
   * [getTaskIdentifierPrefix description]
   * @return string
   */
  protected function getTaskIdentifierPrefix() : string {
    return "{$this->adapter->dbdoc->getVendor()}_{$this->adapter->dbdoc->getApp()}_";
  }

  /**
   * init events
   */
  private function initEvents() {
    $this->onSuccess = new \codename\core\event('PLUGIN_COMPARE_ON_SUCCESS');
    $this->onFail = new \codename\core\event('PLUGIN_COMPARE_ON_FAIL');
    $this->onError = new \codename\core\event('PLUGIN_COMPARE_ON_ERROR');
  }

  /**
   * event fired, if the comparison was successful
   * @var \codename\core\event
   */
  public $onSuccess = null; // new \codename\core\event('PLUGIN_COMPARE_ON_SUCCESS');

  /**
   * event fired, if the comparison failed
   * @var \codename\core\event
   */
  public $onFail = null; // new \codename\core\event('PLUGIN_COMPARE_ON_FAIL');

  /**
   * event fired, if the comparison was interrupted (!)
   * @var \codename\core\event
   */
  public $onError = null; // new \codename\core\event('PLUGIN_COMPARE_ON_ERROR');

}