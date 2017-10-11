<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
class primary extends \codename\architect\dbdoc\plugin\sql\primary {

  /**
   * @inheritDoc
   */
  protected function checkPrimaryKeyAttributes(array $structure) : array
  {
    $tasks = array();

    if($structure['data_type'] != 'bigint') {
      // suggest column data_type modification
      $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_DATATYPE", array(
        'field' => $structure['column_name'],
        'db_datatype' => 'bigint'
      ));
    } else {
      if($structure['column_type'] != 'bigint(20)') {
        // suggest column column_type modification
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_COLUMNTYPE", array(
          'field' => $structure['column_name'],
          'db_datatype' => 'bigint',
          'db_columntype' => 'bigint(20)'
        ));
      }
    }

    return $tasks;
  }

}