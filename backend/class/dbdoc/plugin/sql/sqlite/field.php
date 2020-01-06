<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
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

    // required fields for SQL database adapters:
    // $definition['options']['db_data_type'] = $definition['options']['db_data_type'] ?? null;
    // $definition['options']['db_column_type'] = $definition['options']['db_column_type'] ?? null;
    // TODO
    $definition['options']['db_data_type'] = null;
    $definition['options']['db_column_type'] = $this->convertModelDataTypeToDbDataType($definition['datatype']);
    return $definition;
  }


  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    // get some column specifications
    $db = $this->getSqlAdapter()->db;
    // $db->query(
    //   "SELECT column_name, column_type, data_type, is_nullable, column_default
    //   FROM information_schema.columns
    //   WHERE table_schema = '{$this->adapter->schema}'
    //   AND table_name = '{$this->adapter->model}'
    //   AND column_name = '{$this->parameter['field']}';"
    // );
    // $db->query(
    //   "PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');"
    // );
    $db->query(
      "SELECT *
        FROM pragma_table_info('{$this->adapter->schema}.{$this->adapter->model}')
        WHERE
        name = '{$this->parameter['field']}'
        ;"
      // "PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');"
      // WHERE table_schema = '{$this->adapter->schema}'
      // AND table_name = '{$this->adapter->model}'
    );

    $res = $db->getResult();

    if(count($res) === 1) {
      return $res[0];
    }
    return null;
  }

  /**
   * array of default datatypes (note the difference to the column type!)
   * @var [type]
   */
  protected $defaultsConversionTable = array(
    'bigint'  => 'INTEGER',
    'integer' => 'INTEGER',
    'text'    => 'TEXT',
    'date'    => 'TEXT',
    'datetime' => 'TIMESTAMP'
  );

  protected $conversionTable = array(
      'text'            => [ 'text', 'mediumtext', 'longtext' ],
      'text_timestamp'  => [ 'datetime' ],
      'text_date'       => [ 'date' ],
      'number'          => [ 'numeric', 'decimal' ], // was integer
      'number_natural'  => [ 'INTEGER', 'int', 'bigint' ],
      'boolean'         => [ 'boolean' ],
      'structure'       => [ 'text', 'mediumtext', 'longtext' ],
      'mixed'           => [ 'text' ],
      'virtual'         => [ null ]
      // 'collection'
  );

  /**
   * @inheritDoc
   */
  public function getDbDataTypeDefaultsTable(): array {
    return $this->defaultsConversionTable;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    $definition = $this->getDefinition();

    if($task->name == "CREATE_COLUMN") {

      $attributes = array();

      if($definition['notnull'] && isset($definition['default'])) {
        $attributes[] = "NOT NULL";
      }

      if(isset($definition['default'])) {
        //
        // Special case: field is timestamp && default is CURRENT_TIMESTAMP
        //
        if($definition['datatype'] === 'text_timestamp' && $definition['default'] == 'current_timestamp()') {
          // $attributes[] = 'DEFAULT CURRENT_TIMESTAMP'; // '(DATETIME(\'now\'))'; // WORKAROUND! // $definition['default'];
        } else {
          $attributes[] = "DEFAULT ".json_encode($definition['default']);
        }
      }

      /*
      // not allowed on normal fields? some requirements have to be met?
      if($definition['auto_increment']) {
        $attributes[] = "AUTO_INCREMENT";
      }*/

      // TODO: add unique
      // TODO: add index

      $add = implode(' ', $attributes);

      // fallback from specific column types to a more generous type
      $columnType = $definition['options']['db_column_type'][0] ?? $definition['options']['db_data_type'][0];

      $db->query(
        "ALTER TABLE '{$this->adapter->schema}.{$this->adapter->model}' ADD COLUMN {$definition['field']} {$columnType} {$add};"
      );

    }

    if($task->name == "MODIFY_COLUMN_TYPE" || $task->name == "MODIFY_DATA_TYPE" || $task->name == "MODIFY_NOTNULL" || $task->name == "MODIFY_DEFAULT") {
      // ALTER TABLE tablename MODIFY columnname INTEGER;
      $columnType = $definition['options']['db_column_type'][0] ?? $definition['options']['db_data_type'][0];
      $nullable = $definition['notnull'] ? 'NOT NULL' : '' ; // 'NULL';

      if(array_key_exists('default', $definition) && $definition['datatype'] === 'text_timestamp' && $definition['default'] == 'current_timestamp()') {
        $defaultValue = 'DEFAULT CURRENT_TIMESTAMP';
      } else {
        $defaultValue = json_encode($definition['default'] ?? null);
      }

      //
      // we should update the existing dataset
      // if it's NOT nullable
      //
      if($definition['notnull'] && $definition['default'] != null) {
        $defaultValue = (is_bool($definition['default']) ? ($definition['default'] ? 1 : 0) : $defaultValue);
        $db->query(
          "UPDATE '{$this->adapter->schema}.{$this->adapter->model}' SET {$definition['field']} = {$defaultValue} WHERE {$definition['field']} IS NULL;"
        );
      }

      $default = isset($definition['default']) ? 'DEFAULT ' . $defaultValue.'' : '';

      // NOT SUPPORTED ON SQLITE
      // $db->query(
      //   "ALTER TABLE '{$this->adapter->schema}.{$this->adapter->model}' MODIFY COLUMN {$definition['field']} {$columnType} {$nullable} {$default};"
      // );
    }

  }

}
