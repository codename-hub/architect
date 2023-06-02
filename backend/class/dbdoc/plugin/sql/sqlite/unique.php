<?php

namespace codename\architect\dbdoc\plugin\sql\sqlite;

use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class unique extends \codename\architect\dbdoc\plugin\sql\unique implements partialStatementInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        $db = $this->getSqlAdapter()->db;

        $db->query("SELECT name AS constraint_name, `unique` FROM pragma_index_list('{$this->adapter->schema}.{$this->adapter->model}')");
        $res = $db->getResult();

        $constraints = [];
        foreach ($res as $c) {
            if ($c['unique']) {
                $db->query("SELECT name as column_name FROM pragma_index_info('{$c['constraint_name']}')");
                $constraint = $c;
                $constraintColumns = $db->getResult();
                $constraint['constraint_columns'] = $constraintColumns;
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * {@inheritDoc}
     */
    public function getPartialStatement(): array|null|string
    {
        $definition = $this->getDefinition();

        $uniqueStatements = [];
        foreach ($definition as $def) {
            $constraintColumns = $def;
            $columns = is_array($constraintColumns) ? implode(',', $constraintColumns) : $constraintColumns;
            $constraintName = "unique_" . md5("{$this->adapter->schema}_{$this->adapter->model}_$columns");
            $uniqueStatements[] = "CONSTRAINT $constraintName UNIQUE ($columns)";
        }

        return $uniqueStatements;
    }

    /**
     * {@inheritDoc}
     */
    public function runTask(task $task): void
    {
        // Disabled, NOTE:
        // SQLite unique constraint handling is faulty
        // those have to be created during CREATE TABLE
        // as CREATE UNIQUE INDEX ... afterwards does not produce
        // a working constraint
    }
}
