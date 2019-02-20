<?php
namespace codename\architect\dbdoc;

/**
 * dynamic dbdoc model adapter
 * @package architect
 */
abstract class modeladapter  {

  /**
   * get compatible driver name
   * @return string
   */
  public abstract function getDriverCompat() : string;

  /**
   * get compatible plugin namespaces
   * @return string[]
   */
  public abstract function getPluginCompat() : array;

  /**
   * model configuration
   * @var \codename\core\config
   */
  public $config = null;

  /**
   * environment configuration
   * @var \codename\architect\config\environment
   */
  public $environment = null;

  /**
   * model schema
   * @var string
   */
  public $schema = null;

  /**
   * model name
   * @var string
   */
  public $model = null;

  /**
   * at the moment, this just puts out the identifier
   * (model)
   * @return string [description]
   */
  public function getIdentifier() : string {
    return "{$this->schema}.{$this->model}";
  }

  /**
   * [getPlugins description]
   * @return string[]
   */
  public function getPlugins() : array {
    return array();
  }

  /**
   * plugin execution queue
   * @var \codename\architect\dbdoc\plugin[]
   */
  protected $executionQueue = array();

  /**
   * [addToQueue description]
   * @param \codename\architect\dbdoc\plugin $plugin [description]
   */
  public function addToQueue(\codename\architect\dbdoc\plugin $plugin, bool $insertAtBeginning = false) {
    if($insertAtBeginning) {
      array_unshift($this->executionQueue, $plugin);
    } else {
      $this->executionQueue[] = $plugin;
    }
  }

  /**
   * [getNextQueuedPlugin description]
   * @return \codename\architect\dbdoc\plugin [description]
   */
  protected function getNextQueuedPlugin() {
    return array_shift($this->executionQueue);
  }

  /**
   * Creates a new structural model for DDL
   */
  public function __construct(\codename\architect\dbdoc\dbdoc $dbdocInstance, string $schema, string $model, \codename\core\config $config, \codename\architect\config\environment $environment)
  {
    $this->dbdoc = $dbdocInstance;
    $this->schema = $schema;
    $this->model = $model;
    $this->config = $config;
    $this->environment = $environment;
  }

  /**
   * [parent dbdoc instance]
   * @var dbdoc
   */
  public $dbdoc;

  /**
   * [getPluginInstance description]
   * @param  string $pluginIdentifier [description]
   * @param  array  $parameter        [description]
   * @return \codename\architect\dbdoc\plugin                  [description]
   */
  public function getPluginInstance(string $pluginIdentifier, array $parameter = array(), bool $isVirtual = false) {
    foreach($this->getPluginCompat() as $compat) {
      $classname = "\\codename\\architect\\dbdoc\\plugin\\" . str_replace('_', '\\', $compat . '_' . $pluginIdentifier);
      if(class_exists($classname) && !(new \ReflectionClass($classname))->isAbstract()) {
        return new $classname($this, $parameter, $isVirtual);
      }
    }
    return null;
  }


  /**
   * [runDiagnostics description]
   * @return task[] [description]
   */
  public function runDiagnostics() : array{

    // load plugins
    foreach($this->getPlugins() as $pluginIdentifier) {
      $plugin = $this->getPluginInstance($pluginIdentifier, array());
      if($plugin != null) {
        $this->addToQueue($plugin);
      }
    }

    $tasks = array();

    // loop through unshift
    $plugin = $this->getNextQueuedPlugin();

    while($plugin != null) {
      $tasks = array_merge($tasks, $plugin->Compare());
      $plugin = $this->getNextQueuedPlugin();
    }

    /*
    foreach($tasks as $t) {
      $taskType = task::TASK_TYPES[$t->type];
      echo("<br> Task [{$taskType}] [id:{$t->identifier}] <em>{$t->plugin}</em>::<strong>{$t->name}</strong> " . var_export($t->data, true));
    }*/

    return $tasks;
  }

  /**
   * [getStructure description]
   * @return codename\architect\dbdoc\structure [description]
   */
  // public function getStructure() : \codename\architect\dbdoc\structure;

}
