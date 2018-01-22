<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
class primary extends \codename\architect\dbdoc\plugin\sql\primary {

  /**
   * default column data type for primary keys on mysql
   * @var string
   */
  public const DB_DEFAULT_DATA_TYPE = 'bigint';

  /**
   * default column type for primary keys on mysql
   * @var string
   */
  public const DB_DEFAULT_COLUMN_TYPE = 'bigint(20)';

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $definition = parent::getDefinition();
    $definition['options'] = $this->adapter->config->get('options>'.$definition['field']) ?? [];
    $definition['options']['db_data_type'] = $definition['options']['db_data_type'] ?? self::DB_DEFAULT_DATA_TYPE;
    $definition['options']['db_column_type'] = $definition['options']['db_column_type'] ?? self::DB_DEFAULT_COLUMN_TYPE;
    return $definition;
  }

  /**
   * @inheritDoc
   */
  protected function checkPrimaryKeyAttributes(array $definition, array $structure) : array
  {
    $tasks = array();

    if($structure['data_type'] != self::DB_DEFAULT_DATA_TYPE) {
      // suggest column data_type modification
      $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_DATATYPE", array(
        'field' => $structure['column_name'],
        'db_data_type' => self::DB_DEFAULT_DATA_TYPE
      ));
    } else {
      if($structure['column_type'] != self::DB_DEFAULT_COLUMN_TYPE) {
        // suggest column column_type modification
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_COLUMNTYPE", array(
          'field' => $structure['column_name'],
          'db_data_type' => self::DB_DEFAULT_DATA_TYPE,
          'db_column_type' => self::DB_DEFAULT_COLUMN_TYPE
        ));
      }
    }

    return $tasks;
  }

}