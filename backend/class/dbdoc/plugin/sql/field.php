<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\catchableException;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
abstract class field extends \codename\architect\dbdoc\plugin\field
{
    use modeladapterGetSqlAdapter;

    /**
     * basic conversion table between sql defaults and core framework
     * @var string[]
     */
    protected $conversionTable = [
      'text' => ['text', 'mediumtext', 'longtext'],
      'text_timestamp' => ['datetime'],
      'text_date' => ['date'],
      'number' => ['numeric', 'decimal'], // was integer
      'number_natural' => ['integer', 'int', 'bigint'],
      'boolean' => ['boolean'],
      'structure' => ['text', 'mediumtext', 'longtext'],
      'mixed' => ['text'],
        // 'virtual'         => [ null ]
        // 'collection'
    ];

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function Compare(): array
    {
        $tasks = [];
        $definition = $this->getDefinition();

        // cancel, if field is a collection (virtual field)
        if ($definition['collection']) {
            return $tasks;
        }

        // cancel, if field is a virtual field
        if ($definition['datatype'] == 'virtual') {
            return $tasks;
        }

        // override with definition from primary plugin
        if ($definition['primary']) {
            $plugin = $this->adapter->getPluginInstance('primary', [], $this->virtual);
            if ($plugin != null) {
                $definition = $plugin->getDefinition();
            }
        }

        $structure = $this->virtual ? null : $this->getStructure();

        if ($structure != null) {
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

            $column_type = trim(preg_replace('/\(.*\)/', '', $structure['column_type']));

            if (
                $definition['options']['db_column_type'] != null &&
                !in_array($structure['column_type'], $definition['options']['db_column_type']) &&
                !in_array($column_type, $definition['options']['db_column_type'])
            ) {
                /* $definition['options']['db_column_type'] != $structure['column_type'] */
                // check for array-based definition
                // different column type!
                // echo(" -- unequal?");
                /* echo("<pre>");
                print_r($structure);
                print_r($definition);
                echo("</pre>"); */
                $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_COLUMN_TYPE", $definition);
            } else {
                $checkDataType = false;
            }

            if ($checkDataType) {
                // echo("<br>{$definition['db_data_type']} <=> {$structure['data_type']}");
                if ($definition['options']['db_data_type'] != null && !in_array($structure['data_type'], $definition['options']['db_data_type'])) {
                    // different data type!
                    // echo(" -- unequal?");
                    $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DATA_TYPE", $definition);
                }
            }

            // mysql uses a varchar(3) for storing is_nullable (yes / no)
            if ($definition['notnull'] && $structure['is_nullable'] == 'YES') {
                // make not nullable!
                $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_NOTNULL", $definition);
            }


            if (isset($definition['default'])) {
                // set default column value

                if (is_bool($definition['default'])) {
                    if ($definition['default'] != boolval($structure['column_default'])) {
                        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
                    }
                } elseif (is_int($definition['default'])) {
                    if ($definition['default'] != intval($structure['column_default'])) {
                        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
                    }
                } elseif (is_string($definition['default'])) {
                    if ($definition['default'] != $structure['column_default']) {
                        $definition['debug'] = $structure;
                        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
                    }
                } // TODO: DEFAULT ARRAY VALUE
                /* elseif(is_array($definition['default'])) {
                  if(json_encode($definition['default']) != $structure['column_default']) {
                    $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "MODIFY_DEFAULT", $definition);
                  }
                }*/
            }
        } elseif (!$definition['primary']) {
            // some error !
            // print_r($definition);
            // print_r($structure);

            // only create, if not primary
            // is it is, it is created in the table plugin (at least for mysql)

            // create create-field task
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_COLUMN", [
              'field' => $definition['field'],
                // 'def' => $definition
                // 'datatype' => $definition['datatype'],
                // 'datatype_override' => $definition['datatype_override'],
                // 'db_datatype' => $definition['datatype_override'] ?? $this->convertModelDataTypeToDbType($definition['datatype']) // first item == default?
            ]);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function getDefinition(): array
    {
        $definition = parent::getDefinition();

        // required fields for SQL database adapters:
        $definition['options']['db_data_type'] = $definition['options']['db_data_type'] ?? null;
        $definition['options']['db_column_type'] = $definition['options']['db_column_type'] ?? null;

        if ($definition['primary']) {
            $plugin = $this->adapter->getPluginInstance('primary', [], $this->virtual);
            $definition = array_replace($definition, $plugin->getDefinition());
        }

        //
        // NOTE: we can only sync column datatype if it's not a structure (e.g. array)
        //
        if ($definition['foreign'] && $definition['datatype'] != 'structure') {
            // we have to get field information from a different model (!)
            // , $def['app'] ?? '', $def['vendor'] ?? ''
            $foreignAdapter = $this->adapter->dbdoc->getAdapter(
                $definition['foreign']['schema'],
                $definition['foreign']['model'],
                $definition['foreign']['app'] ?? '',
                $definition['foreign']['vendor'] ?? ''
            );
            $plugin = $foreignAdapter->getPluginInstance('field', ['field' => $definition['foreign']['key']]);
            if ($plugin != null) {
                $foreignDefinition = $plugin->getDefinition();

                // equalize datatype
                // both the referenced column and this one have to be of the same type
                $definition['options']['db_data_type'] = $foreignDefinition['options']['db_data_type'];
                $definition['options']['db_column_type'] = $foreignDefinition['options']['db_column_type'];
                // TODO: NEW OPTIONS FORMAT/SETTING?

                // TODO: we may warn, if there's a configuration difference!
            }
        }

        //
        // Handle automatic fallback to current_timestamp() for _created fields in models
        // except we override it in the model
        //
        if (!isset($definition['default']) && $definition['field'] == $this->adapter->model . '_created') {
            $definition['default'] = 'current_timestamp()';
        }

        return $definition;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     * @throws ReflectionException
     * @throws exception
     */
    public function getStructure(): mixed
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
        if (count($res) === 1) {
            return $res[0];
        }
        return null;
    }

    /**
     * {@inheritDoc}
     * @param task $task
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;

        $definition = $this->getDefinition();

        if ($task->name == "CREATE_COLUMN") {
            $attributes = [];

            if ($definition['notnull']) {
                $attributes[] = "NOT NULL";
            }

            if (isset($definition['default'])) {
                //
                // Special case: field is timestamp && default is CURRENT_TIMESTAMP
                //
                if ($definition['datatype'] === 'text_timestamp' && $definition['default'] == 'current_timestamp()') {
                    $attributes[] = "DEFAULT " . $definition['default'];
                } else {
                    $attributes[] = "DEFAULT " . json_encode($definition['default']);
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
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} ADD COLUMN {$definition['field']} $columnType $add;"
            );
        }

        if ($task->name == "MODIFY_COLUMN_TYPE" || $task->name == "MODIFY_DATA_TYPE" || $task->name == "MODIFY_NOTNULL" || $task->name == "MODIFY_DEFAULT") {
            // ALTER TABLE tablename MODIFY columnname INTEGER;
            $columnType = $definition['options']['db_column_type'][0] ?? $definition['options']['db_data_type'][0];
            $nullable = $definition['notnull'] ? 'NOT NULL' : 'NULL';

            if (array_key_exists('default', $definition) && $definition['datatype'] === 'text_timestamp' && $definition['default'] == 'current_timestamp()') {
                $defaultValue = $definition['default'];
            } else {
                $defaultValue = json_encode($definition['default'] ?? null);
            }

            //
            // we should update the existing dataset
            // if it's NOT nullable
            //
            if ($definition['notnull'] && $definition['default'] != null) {
                $db->query(
                    "UPDATE {$this->adapter->schema}.{$this->adapter->model} SET {$definition['field']} = $defaultValue WHERE {$definition['field']} IS NULL;"
                );
            }

            $default = isset($definition['default']) ? 'DEFAULT ' . $defaultValue : '';

            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} MODIFY COLUMN {$definition['field']} $columnType $nullable $default;"
            );
        }
    }

    /**
     * converts a field configuration
     * @param array $config
     * @return array [type] [description]
     * @throws exception
     */
    public function convertFieldConfigurationToDbColumnType(array $config = []): array
    {
        /**
         * check:
         * - datatype
         * - options
         *    + db_datatype ?
         *    + (db_column_type) ?
         *    + length
         */

        $dbDataType = $config['options']['db_data_type'] ?? null;
        $dbColumnType = $config['options']['db_column_type'] ?? null;
        $length = $config['options']['length'] ?? null;
        $precision = $config['options']['precision'] ?? null;

        // explicit db_column_type not specified
        if ($dbDataType == null) {
            $tDbDataType = $this->convertModelDataTypeToDbDataType($config['datatype']);
            // $dbDataType = count($tDbDataType) > 0 ? $tDbDataType[0] : null;
            $dbDataType = $tDbDataType;
        }

        if ($dbColumnType == null) {
            $columnTypes = [];
            foreach ($dbDataType as $type) {
                switch ($type) {
                    case 'text':
                        if ($length) {
                            $columnTypes[] = "varchar($length)";
                        }
                        break;

                    case 'numeric':
                        if ($length && $precision) {
                            $columnTypes[] = "numeric($length,$precision)";
                        } elseif ($length) {
                            $columnTypes[] = "numeric($length,0)";
                        }
                        break;

                    case 'decimal':
                        if ($length && $precision) {
                            $columnTypes[] = "decimal($length,$precision)";
                        } elseif ($length) {
                            $columnTypes[] = "decimal($length,0)";
                        }
                        break;

                    case 'integer':
                    case 'int':
                        if ($length) {
                            $columnTypes[] = "int($length)";
                        }
                        break;

                    default:
                        # code...
                        break;
                }
            }

            $dbColumnType = count($columnTypes) > 0 ? $columnTypes : null;
        }

        if ($dbColumnType == null) {
            // $defaults = $this->convertDbDataTypeToDbColumnTypeDefault($dbDataType);
            // $dbColumnType = count($defaults) > 0 ? $defaults[0] : null; // Should we fall back to the first entry?
            $dbColumnType = $this->convertDbDataTypeToDbColumnTypeDefault($dbDataType);
        }

        return [
          'db_column_type' => $dbColumnType && !is_array($dbColumnType) ? [$dbColumnType] : $dbColumnType,
          'db_data_type' => $dbDataType && !is_array($dbDataType) ? [$dbDataType] : $dbDataType,
        ];
    }

    /**
     * [convertModelDataTypeToDbDataType description]
     * @param  [type] $t [description]
     * @return array|string [db data type from conversion table]
     * @throws catchableException
     * @throws exception
     */
    public function convertModelDataTypeToDbDataType($t): array|string
    {
        if ($t == null) {
            throw new exception("EXCEPTION_DBDOC_PLUGIN_SQL_FIELD_MODEL_DATATYPE_NULL", exception::$ERRORLEVEL_ERROR, $this->parameter);
        }
        return $this->getDatatypeConversionOptions($t); // all results
    }

    /**
     * gets all conversion options for converting
     * model datatype => db datatype
     * @param string $key [datatype / validator]
     * @return array|string [description]
     * @throws catchableException
     */
    protected function getDatatypeConversionOptions(string $key): array|string
    {
        $conversionTable = $this->getDatatypeConversionTable();
        if (array_key_exists($key, $conversionTable)) {
            // use defined type
            return $conversionTable[$key];
        } else {
            $keyComponents = explode('_', $key);
            $keyComponentCount = count($keyComponents);

            // CHANGED/ADDED: add top-down search
            // recursively re-combine $t's elements and reduce each loop by 1
            // NOTE: the direct full match is handled above
            for ($i = 0; $i < $keyComponentCount; $i++) {
                $testKey = implode('_', array_slice($keyComponents, 0, $keyComponentCount - $i));
                if (array_key_exists($testKey, $conversionTable)) {
                    return $conversionTable[$testKey];
                }
            }

            // throw some error, as it is not in our type definition library
            throw new catchableException('EXCEPTION_DBDOC_MODEL_DATATYPE_NOT_IN_DEFINITION_LIBRARY', catchableException::$ERRORLEVEL_ERROR, [$key, $keyComponents[0]]);
        }
    }

    /**
     * [getDatatypeConversionTable description]
     * @return array [description]
     */
    public function getDatatypeConversionTable(): array
    {
        return $this->conversionTable;
    }

    /**
     * [convertDbDataTypeToDbColumnTypeDefault description]
     * @param  [type] $t [description]
     * @return array [type]    [description]
     * @throws exception
     */
    public function convertDbDataTypeToDbColumnTypeDefault($t): array
    {
        if ($t == null) {
            throw new exception("EXCEPTION_DBDOC_PLUGIN_SQL_FIELD_NO_COLUMN_TYPE_TRANSLATION_AVAILABLE", exception::$ERRORLEVEL_ERROR, $this);
        }

        // check for existing overrides/matching types
        $conversionTable = $this->getDbDataTypeDefaultsTable();

        // make $t an array, if it's not
        $checkTypes = !is_array($t) ? [$t] : $t;

        $res = [];
        foreach ($checkTypes as $checkType) {
            if (array_key_exists($checkType, $conversionTable)) {
                // use defined type
                $res[] = $conversionTable[$checkType];
            } else {
                $tArr = explode('_', $checkType);
                if (array_key_exists($tArr[0], $conversionTable)) {
                    // we have a defined underlying db field type
                    $res[] = $conversionTable[$tArr[0]];
                } else {
                    // throw some error, as it is not in our type definition library
                    // throw new \codename\core\exception('EXCEPTION_DBDOC_MODEL_COLUMN_TYPE_NOT_IN_DEFINITION_LIBRARY', catchableException::$ERRORLEVEL_ERROR, array($t, $tArr[0]));
                    // return null;
                }
            }
        }
        return $res;
    }

    /**
     * [getDbDataTypeDefaultsTable description]
     * @return array [description]
     */
    abstract public function getDbDataTypeDefaultsTable(): array;
}
