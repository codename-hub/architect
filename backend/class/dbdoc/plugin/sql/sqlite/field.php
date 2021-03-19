<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
class field extends \codename\architect\dbdoc\plugin\sql\field
  implements partialStatementInterface {

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


    // Override regular SQL-style to match SQLite's requirements
    // at least for datetime-fields with a default value
    if($definition['datatype'] == 'text_timestamp' && $definition['default'] == 'current_timestamp()') {
      $definition['default'] = 'CURRENT_TIMESTAMP';
    }

    return $definition;
  }


  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    // get some column specifications
    $db = $this->getSqlAdapter()->db;

    $db->query(
      "SELECT *
        FROM pragma_table_info('{$this->adapter->schema}.{$this->adapter->model}')
        WHERE
        name = '{$this->parameter['field']}'
        ;"
    );

    $res = $db->getResult();

    if(count($res) === 1) {
      // Change sqlite pragma's resultset
      // and map type to column_type (which is handled in generic field plugin)
      $r = $res[0];
      $r['column_type'] = $r['type'];
      $r['column_default'] = $r['dflt_value'];
      return $r;
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
   * [getPartialStatement description]
   * @return [type] [description]
   */
  public function getPartialStatement() {
    $definition = $this->getDefinition();

    $attributes = array();

    if($definition['primary']) {
      // support for single-column PKEYs
      $attributes[] = 'PRIMARY KEY';
    }

    if($definition['auto_increment']) {
      // support for single-column PKEYs
      $attributes[] = 'AUTOINCREMENT';
    }

    if($definition['notnull'] ?? false) {
      $attributes[] = "NOT NULL";
    }

    if(isset($definition['default'])) {
      //
      // Special case: field is timestamp && default is CURRENT_TIMESTAMP
      //
      if($definition['datatype'] === 'text_timestamp' && $definition['default'] == 'CURRENT_TIMESTAMP') {
        $attributes[] = 'DEFAULT CURRENT_TIMESTAMP'; // '(DATETIME(\'now\'))'; // WORKAROUND! // $definition['default'];
      } else {
        if(is_bool($definition['default'])) {
          $attributes[] = "DEFAULT ".(int)$definition['default'];
        } else {
          $attributes[] = "DEFAULT ".json_encode($definition['default']);
        }
      }
    }

    // TODO: add unique
    // TODO: add index

    $add = implode(' ', $attributes);

    // fallback from specific column types to a more generous type
    $columnType = $definition['options']['db_column_type'][0] ?? $definition['options']['db_data_type'][0];

    return "{$definition['field']} {$columnType} {$add}";
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
        if($definition['datatype'] === 'text_timestamp' && $definition['default'] == 'CURRENT_TIMESTAMP') {
          $attributes[] = 'DEFAULT CURRENT_TIMESTAMP'; // '(DATETIME(\'now\'))'; // WORKAROUND! // $definition['default'];
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
