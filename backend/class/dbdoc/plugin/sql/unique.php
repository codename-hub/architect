<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing unique field config in a model
 * @package architect
 */
class unique extends \codename\architect\dbdoc\plugin\unique
{
    use modeladapterGetSqlAdapter;

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function Compare(): array
    {
        $tasks = [];

        $definition = $this->getDefinition();

        // virtual = assume empty structure
        $structure = $this->virtual ? [] : $this->getStructure();

        $valid = [];
        $missing = [];

        foreach ($structure as $struc) {
            // get ordered (?) column_names
            $constraintColumnNames = array_map(
                function ($spec) {
                    return $spec['column_name'];
                },
                $struc['constraint_columns']
            );

            // reduce to string, if only one element
            $constraintColumnNames = count($constraintColumnNames) == 1 ? $constraintColumnNames[0] : $constraintColumnNames;

            // compare!
            if (in_array($constraintColumnNames, $definition)) {
                // constraint exists and is correct
                $valid[] = $constraintColumnNames;
            } else {
                $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_UNIQUE_CONSTRAINT", [
                  'constraint_name' => $struc['constraint_name'],
                ]);
            }
        }

        // determine missing constraints
        array_walk($definition, function ($d) use ($valid, &$missing) {
            foreach ($valid as $v) {
                if (gettype($v) == gettype($d)) {
                    if ($d == $v) {
                        return;
                    }
                }
            }
            $missing[] = $d;
        });

        foreach ($missing as $def) {
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "ADD_UNIQUE_CONSTRAINT", [
              'constraint_columns' => $def,
            ]);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function getStructure(): array
    {
        $db = $this->getSqlAdapter()->db;
        $db->query(
            "SELECT table_schema, table_name, constraint_name
      FROM information_schema.table_constraints
      WHERE constraint_type='UNIQUE'
      AND table_schema = '{$this->adapter->schema}'
      AND table_name = '{$this->adapter->model}';"
        );
        $constraints = $db->getResult();

        foreach ($constraints as &$constraint) {
            $db->query(
                "SELECT table_schema, table_name, constraint_name, column_name
        FROM information_schema.key_column_usage
        WHERE constraint_name = '{$constraint['constraint_name']}'
        AND table_schema = '{$this->adapter->schema}'
        AND table_name = '{$this->adapter->model}'
        ORDER BY constraint_name;"
            );
            $constraintColumns = $db->getResult();
            $constraint['constraint_columns'] = $constraintColumns;
        }

        return $constraints;
    }

    /**
     * {@inheritDoc}
     * @param task $task
     * @throws ReflectionException
     * @throws exception
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;
        if ($task->name == "ADD_UNIQUE_CONSTRAINT") {
            $constraintColumns = $task->data->get('constraint_columns');
            $columns = is_array($constraintColumns) ? implode(',', $constraintColumns) : $constraintColumns;

            $constraintName = "unique_" . md5("{$this->adapter->schema}_{$this->adapter->model}_$columns");

            $db->query(
                "CREATE UNIQUE INDEX $constraintName
         ON {$this->adapter->schema}.{$this->adapter->model} ($columns);"
            );
        } elseif ($task->name == "REMOVE_UNIQUE_CONSTRAINT") {
            $constraintName = $task->data->get('constraint_name');

            $db->query(
                "DROP INDEX $constraintName ON {$this->adapter->schema}.{$this->adapter->model};"
            );
        }
    }
}
