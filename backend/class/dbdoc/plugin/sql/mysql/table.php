<?php

namespace codename\architect\dbdoc\plugin\sql\mysql;

use codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model table data
 * @package architect
 */
class table extends plugin\sql\table
{
    /**
     * {@inheritDoc}
     * @param task $task
     * @throws ReflectionException
     * @throws exception
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;
        if ($task->name == 'CREATE_TABLE') {
            // get pkey creation info
            $pkeyPlugin = $this->adapter->getPluginInstance('primary');
            $field = $pkeyPlugin->getDefinition();

            $attributes = [];

            if ($field['notnull']) {
                $attributes[] = "NOT NULL";
            }

            if ($field['auto_increment']) {
                $attributes[] = "AUTO_INCREMENT";
            }

            $add = implode(' ', $attributes);

            // for mysql, we have to create the table with at least ONE COLUMN
            $db->query(
                "CREATE TABLE {$this->adapter->schema}.{$this->adapter->model} (
          {$field['field']} {$field['options']['db_column_type'][0]} $add,
          PRIMARY KEY({$field['field']})
        ) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE utf8_general_ci;"
            );
        }
        if ($task->name == 'DELETE_COLUMN') {
            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} DROP COLUMN IF EXISTS {$task->data->get('field')};"
            );
        }
    }
}
