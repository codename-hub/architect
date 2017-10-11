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
    $definition['db_data_type'] = $this->adapter->config->get('db_data_type>'.$this->parameter['field']) ?? $this->convertModelDataTypeToDbDataType($this->adapter->config->get('datatype>'.$this->parameter['field']));
    $definition['db_column_type'] = $this->adapter->config->get('db_column_type>'.$this->parameter['field']) ?? $this->convertDbDataTypeToDbColumnTypeDefault($definition['db_data_type']);
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