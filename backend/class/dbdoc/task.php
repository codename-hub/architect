<?php
namespace codename\architect\dbdoc;

/**
 * task class
 * @package architect
 */
class task {

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
   *
   */
  public function __construct(modeladapter $adapter, string $plugin, \codename\core\config $data)
  {
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