<?php

namespace codename\architect\dbdoc\plugin\sql\mysql;

use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class foreign extends \codename\architect\dbdoc\plugin\sql\foreign
{
    /**
     * {@inheritDoc}
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;

        // NOTE: Special implementation for MySQL
        // see: https://stackoverflow.com/questions/14122031/how-to-remove-constraints-from-my-mysql-table/14122155
        if ($task->name == "REMOVE_FOREIGNKEY_CONSTRAINT") {
            $constraintName = $task->data->get('constraint_name');

            // drop the foreign key constraint itself
            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        DROP FOREIGN KEY $constraintName;"
            );

            // drop the associated index
            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        DROP INDEX IF EXISTS $constraintName;"
            );
            return;
        }
        parent::runTask($task);
    }
}
