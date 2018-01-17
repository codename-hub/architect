<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;
use codename\core\exception;
use codename\core\catchableException;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
abstract class field extends \codename\architect\dbdoc\plugin\field {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $definition = parent::getDefinition();
    if($definition['primary']) {
      $plugin = $this->adapter->getPluginInstance('primary', array(), $this->virtual);
      $definition = array_replace($definition, $plugin->getDefinition());
    }
    if($definition['foreign']) {
      // we have to get field information from a different model (!)
      // , $def['app'] ?? '', $def['vendor'] ?? ''
      $foreignAdapter = $this->adapter->dbdoc->getAdapter(
        $definition['foreign']['schema'],
        $definition['foreign']['model'],
        $definition['foreign']['app'] ?? '',
        $definition['foreign']['vendor'] ?? ''
      );
      $plugin = $foreignAdapter->getPluginInstance('field', array('field' => $definition['foreign']['key']));
      if($plugin != null) {
        $foreignDefinition = $plugin->getDefinition();

        // equalize datatypes
        // both the referenced column and this one have to be of the same type
        $definition['db_data_type'] = $foreignDefinition['db_data_type'];
        $definition['db_column_type'] = $foreignDefinition['db_column_type'];

        // TODO: we may warn, if there's a configurational difference!
      }
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
      "SELECT column_name, column_type, data_type, is_nullable, column_default
      FROM information_schema.columns
      WHERE table_schema = '{$this->adapter->schema}'
      AND table_name = '{$this->adapter->model}'
      AND column_name = '{$this->parameter['field']}';"
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

    // override with definition from primary plugin
    if($definition['primary']) {
      $plugin = $this->adapter->getPluginInstance('primary', array(), $this->virtual);
      if($plugin != null) {
        $definition = $plugin->getDefinition();
      }
    }

    $structure = $this->virtual ? null : $this->getStructure();

    if($structure != null) {
      /*
      echo("<pre>");
      print_r($definition);
      echo("</pre>");

      echo("<pre>");
      print_r($structure);
      echo("</pre>");
      */
      // TODO: check field properties

      // compare db_data_type
      // compare db_column_type

      // echo("<br>{$definition['db_column_type']} <=> {$structure['column_type']}");

      $checkDataType = true;

      if($definition['db_column_type'] != null && $definition['db_column_type'] != $structure['column_type']) {
        // different column type!
        // echo(" -- unequal?");
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_COLUMN_TYPE", $definition);

      } else {
        $checkDataType = false;
      }

      if($checkDataType) {
        // echo("<br>{$definition['db_data_type']} <=> {$structure['data_type']}");
        if($definition['db_data_type'] != null && $definition['db_data_type'] != $structure['data_type']) {
          // different data type!
          // echo(" -- unequal?");
          $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DATA_TYPE", $definition);
        }
      }

      // mysql uses a varchar(3) for storing is_nullable (yes / no)
      if($definition['notnull'] && $structure['is_nullable'] == 'YES') {
        // make not nullable!
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_NOTNULL", $definition);
      }


      if(isset($definition['default'])) {
        // set default column value

        if(is_bool($definition['default'])) {
          if($definition['default'] != boolval($structure['column_default'])) {
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
          }
        } else if(is_int($definition['default'])) {
          if($definition['default'] != intval($structure['column_default'])) {
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
          }
        } else if(is_string($definition['default'])) {
          if($definition['default'] != $structure['column_default']) {
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
          }
        } // TODO: DEFAULT ARRAY VALUE
        /* else if(is_array($definition['default'])) {
          if(json_encode($definition['default']) != $structure['column_default']) {
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
          }
        }*/

      }


    } else {
      // some error !
      // print_r($definition);
      // print_r($structure);

      // only create, if not primary
      // if it is, it is created in the table plugin (at least for mysql)
      if(!$definition['primary']) {
        // create create-field task
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_COLUMN", array(
          'field' => $definition['field'],
          // 'def' => $definition
          // 'datatype' => $definition['datatype'],
          // 'datatype_override' => $definition['datatype_override'],
          // 'db_datatype' => $definition['datatype_override'] ?? $this->convertModelDataTypeToDbType($definition['datatype']) // first item == default?
        ));
      }
    }

    return $tasks;
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

      if($definition['notnull']) {
        $attributes[] = "NOT NULL";
      }

      if(isset($definition['default'])) {
        $attributes[] = "DEFAULT ".json_encode($definition['default']);
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
      $columnType = $definition['db_column_type'] ?? $definition['db_data_type'];
      $db->query(
        "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} ADD COLUMN {$definition['field']} {$columnType} {$add};"
      );

    }

    if($task->name == "MODIFY_COLUMN_TYPE" || $task->name == "MODIFY_DATA_TYPE" || $task->name == "MODIFY_NOTNULL" || $task->name == "MODIFY_DEFAULT") {
      // ALTER TABLE tablename MODIFY columnname INTEGER;
      $columnType = $definition['db_column_type'] ?? $definition['db_data_type'];
      $nullable = $definition['notnull'] ? 'NOT NULL' : 'NULL';
      $default = isset($definition['default']) ? 'DEFAULT ' . json_encode($definition['default']).'' : '';
      $db->query(
        "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} MODIFY COLUMN {$definition['field']} {$columnType} {$nullable} {$default};"
      );
    }

  }


  /**
   * basic conversion table between sql defaults and core framework
   * @var [type]
   */
  protected $conversionTable = array(
      'text' => array('text', 'mediumtext'),
      'text_timestamp' => 'datetime',
      'text_date' => 'date',
      'number' => 'numeric', // was integer
      'number_natural' => array('integer', 'int', 'bigint'),
      'boolean' => 'boolean',
      'structure' => array('text', 'mediumtext'),
      'mixed' => array('text')
  );

  /**
   * [getDatatypeConversionTable description]
   * @return array [description]
   */
  public function getDatatypeConversionTable(): array
  {
    return $this->conversionTable;
  }

  /**
   * [convertModelDataTypeToDbDataType description]
   * @param  [type] $t [description]
   * @return string    [db data type from conversion table]
   */
  public function convertModelDataTypeToDbDataType($t) {

    if($t == null) {
      throw new exception("EXCEPTION_DBDOC_PLUGIN_SQL_FIELD_MODEL_DATATYPE_NULL", exception::$ERRORLEVEL_ERROR, $this->parameter);
    }

		// check for existing overrides/matching types
    $conversionTable = $this->getDatatypeConversionTable();
		if(array_key_exists($t,$conversionTable)) {
			// use defined type
			$res = $conversionTable[$t];
      return is_array($res) ? $res[0] : $res;
		} else {
			$tArr = explode('_', $t);

      // TODO: add top-down search
      // recursively re-combine $t's elements and reduce each loop by 1

			if(array_key_exists($tArr[0], $conversionTable)) {
				// we have a defined underlying db field type
        $res = $conversionTable[$tArr[0]];
				return is_array($res) ? $res[0] : $res;
			} else {
				// throw some error, as it is not in our type definition library
        throw new catchableException('EXCEPTION_DBDOC_MODEL_DATATYPE_NOT_IN_DEFINITION_LIBRARY', catchableException::$ERRORLEVEL_ERROR, array($t, $tArr[0]));
      }
		}
	}


  /**
   * [getDbDataTypeDefaultsTable description]
   * @return array [description]
   */
  public abstract function getDbDataTypeDefaultsTable(): array;

  /**
   * [convertDbDataTypeToDbColumnTypeDefault description]
   * @param  [type] $t [description]
   * @return [type]    [description]
   */
  public function convertDbDataTypeToDbColumnTypeDefault($t) {

    if($t == null) {
      throw new exception("EXCEPTION_DBDOC_PLUGIN_SQL_FIELD_NO_COLUMN_TYPE_TRANSLATION_AVAILABLE", exception::$ERRORLEVEL_ERROR, $this);
    }

    // check for existing overrides/matching types
    $conversionTable = $this->getDbDataTypeDefaultsTable();
		if(array_key_exists($t,$conversionTable)) {
			// use defined type
			return $conversionTable[$t];
		} else {
			$tArr = explode('_', $t);
			if(array_key_exists($tArr[0], $conversionTable)) {
				// we have a defined underlying db field type
				return $conversionTable[$tArr[0]];
			} else {
				// throw some error, as it is not in our type definition library
        // throw new \codename\core\exception('EXCEPTION_DBDOC_MODEL_COLUMN_TYPE_NOT_IN_DEFINITION_LIBRARY', catchableException::$ERRORLEVEL_ERROR, array($t, $tArr[0]));
        return null;
      }
		}
  }
}
