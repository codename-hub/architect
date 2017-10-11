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
  public function getDefinition()
  {
    $primarykey = parent::getDefinition();
    $field = $primarykey;
    return array(
      'field' => $field,
      'auto_increment' => true,
      'notnull' => true,
      'primary' => true,
      'datatype' => $this->adapter->config->get('datatype>' . $field),
      // 'db_column_type' => $this->adapter->config->get('datatype_override>' . $field)
    );
  }

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

    if($structure == null) {

      // set task for PKEY creation
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_PRIMARYKEY", array(
          'field' => $definition
      ));

    } else {

      // we just got the primary key of the table.
      // check for column equality
      if($definition['field'] == $structure['column_name']) {

        // we got the right column, compare properties
        $this->checkPrimaryKeyAttributes($definition, $structure);

      } else {
        // primary key set on wrong column/field !
        // task? info? error? modify?
        $tasks[] = $this->createTask(task::TASK_TYPE_ERROR, "PRIMARYKEY_WRONG_COLUMN", array(
            'field' => $definition['field'],
            'column' => $structure['column_name']
        ));

      }
    }
    return $tasks;
  }

  /**
   * this function checks a given structure information
   * for correctness and returns an array of tasks needed for completion
   * @param  [type] $definition [description]
   * @param  [type] $structure  [description]
   * @return task[]             [description]
   */
  protected abstract function checkPrimaryKeyAttributes(array $definition, array $structure) : array;

}
