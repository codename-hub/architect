<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
class field extends \codename\architect\dbdoc\plugin\sql\field {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $definition = parent::getDefinition();
    // TODO: check if this is the correct behaviour
    // the base class sql\field may already set db_data_type, e.g. if it's a primary key

    // field is a virtual field (collection)
    if($definition['collection']) {
      return $definition;
    }

    if($definition['datatype'] == 'virtual') {
      return $definition;
    }

    /*
    if(!isset($definition['db_data_type'])) {
      $definition['db_data_type'] = $this->adapter->config->get('db_data_type>'.$this->parameter['field']) ?? $this->convertModelDataTypeToDbDataType($this->adapter->config->get('datatype>'.$this->parameter['field']));
    }
    if(!isset($definition['db_column_type'])) {
      $definition['db_column_type'] = $this->adapter->config->get('db_column_type>'.$this->parameter['field']) ?? $this->convertDbDataTypeToDbColumnTypeDefault($definition['db_data_type']);
    }*/

    // $definition['options']['db_column_type'] = $this->convertFieldConfigurationToDbColumnType($definition);

    if(!is_array($definition['field'])) {
      $definition['options'] =  array_replace($definition['options'], $this->convertFieldConfigurationToDbColumnType($definition));
    }
    return $definition;
  }

  /**
   * [protected description]
   * @var [type]
   */
  protected $defaultsConversionTable = array(
    'bigint' => 'bigint(20)',
    'integer' => 'int(11)'
  );

  /**
   * @inheritDoc
   */
  public function getDbDataTypeDefaultsTable(): array {
    return $this->defaultsConversionTable;
  }





}