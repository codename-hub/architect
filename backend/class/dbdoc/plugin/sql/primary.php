<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
abstract class primary extends \codename\architect\dbdoc\plugin\primary {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    // get some column specifications
    $db = $this->getSqlAdapter()->db;
    $db->query(
      "SELECT column_name, column_type, data_type
      FROM information_schema.columns
      WHERE table_schema = '{$this->adapter->schema}'
      AND table_name = '{$this->adapter->model}'
      AND column_key = 'PRI';"
    );

    $res = $db->getResult();
    if(count($res) === 1) {
      return $res[0];
    }
    return null;
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();
    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    if($structure != null) {
      print_r($definition);
      print_r($structure);

      // set task for PKEY creation
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_PRIMARYKEY", array(
          'field' => $definition
      ));

    } else {

      // we just got the primary key of the table.
      // check for column equality
      if($definition == $structure['column_name']) {

        // we got the right column, compare properties
        $this->checkPrimaryKeyAttributes($structure);

      } else {
        // primary key set on wrong column/field !
        // task? info? error? modify?


      }
    }
    return $tasks;
  }

  /**
   * this function checks a given structure information
   * for correctness and returns an array of tasks needed for completion
   * @param  [type] $structure [description]
   * @return task[]            [description]
   */
  protected abstract function checkPrimaryKeyAttributes(array $structure) : array;

}
