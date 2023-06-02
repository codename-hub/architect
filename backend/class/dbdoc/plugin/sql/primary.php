<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\catchableException;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
abstract class primary extends \codename\architect\dbdoc\plugin\primary
{
    use modeladapterGetSqlAdapter;

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

        // virtual = assume empty structure
        $structure = $this->virtual ? null : $this->getStructure();

        if ($structure == null) {
            // in mysql, we prefer to create the table with the primary key, in the first place.
            if (!$this->virtual) {
                // set task for PKEY creation
                $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_PRIMARYKEY", [
                  'field' => $definition,
                ]);
            }
        } elseif ($definition['field'] == $structure['column_name']) {
            // we got the right column, compare properties
            $this->checkPrimaryKeyAttributes($definition, $structure);
        } else {
            // primary key set on wrong column/field !
            // task? info? error? modify?
            $tasks[] = $this->createTask(task::TASK_TYPE_ERROR, "PRIMARYKEY_WRONG_COLUMN", [
              'field' => $definition['field'],
              'column' => $structure['column_name'],
            ]);
        }
        return $tasks;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        $primarykey = parent::getDefinition();
        $field = $primarykey;
        return [
          'field' => $field,
          'auto_increment' => true,
          'notnull' => true,
          'primary' => true,
          'datatype' => $this->adapter->config->get('datatype>' . $field),
        ];
    }

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function getStructure(): array
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
        if (count($res) === 1) {
            return $res[0];
        }
        return [];
    }

    /**
     * this function checks a given structure information
     * for correctness and returns an array of tasks needed for completion
     * @param array $definition
     * @param array $structure
     * @return array [description]
     */
    abstract protected function checkPrimaryKeyAttributes(array $definition, array $structure): array;
}
