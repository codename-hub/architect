<?php
namespace codename\architect\dbdoc\plugin\sql;
use \codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model schema data
 * @package architect
 */
class schema extends plugin\schema {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query(
        "SELECT exists(select 1 FROM information_schema.schemata WHERE schema_name = '{$this->adapter->schema}') as result;"
    );
    return $db->getResult()[0]['result'];
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();
    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    if($structure) {

      // schema/database exists
      // start subroutine plugins
      $plugin = $this->adapter->getPluginInstance('table');
      if($plugin != null) {
        // add this plugin to the first
        $this->adapter->addToQueue($plugin, true);
      }

    } else {
      // schema/database does not exist
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_SCHEMA", array(
        'schema' => $definition
      ));
    }

    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    if($task->name == 'CREATE_SCHEMA') {
      // CREATE SCHEMA
      $db->query("CREATE SCHEMA {$this->adapter->schema};");
    }

  }

}