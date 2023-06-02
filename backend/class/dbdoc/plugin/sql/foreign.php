<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\catchableException;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class foreign extends \codename\architect\dbdoc\plugin\foreign
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
        $structure = $this->virtual ? [] : $this->getStructure();

        $valid = [];

        foreach ($structure as $struc) {
            // invalid or simply too much
            if (array_key_exists($struc['column_name'], $definition)) {
                // struc-def match, check values
                $foreignConfig = $definition[$struc['column_name']];

                if ($foreignConfig['schema'] != $struc['referenced_table_schema']
                  || $foreignConfig['model'] != $struc['referenced_table_name']
                  || $foreignConfig['key'] != $struc['referenced_column_name']
                ) {
                    $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_FOREIGNKEY_CONSTRAINT", [
                      'constraint_name' => $struc['constraint_name'],
                      'field' => $struc['column_name'],
                      'config' => $foreignConfig,
                    ]);
                } else {
                    $valid[$struc['column_name']] = $foreignConfig;
                }
            } else {
                $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_FOREIGNKEY_CONSTRAINT", [
                  'constraint_name' => $struc['constraint_name'],
                ]);
            }
        }

        $missing = array_diff_key($definition, $valid);

        foreach ($missing as $field => $def) {
            $precededBy = [];

            // let the task be preceded by tasks related to the existence of the foreign field
            $foreignAdapter = $this->adapter->dbdoc->getAdapter($def['schema'], $def['model'], $def['app'] ?? '', $def['vendor'] ?? '');

            // omit multi-component foreignkeys
            if (isset($def['optional']) && $def['optional']) {
                continue;
            }

            $foreignFields = is_array($def['key']) ? array_values($def['key']) : [$def['key']];
            $nullPluginDetected = false;
            foreach ($foreignFields as $key) {
                $plugin = $foreignAdapter->getPluginInstance('field', ['field' => $key]);
                if ($plugin != null) {
                    $precededBy[] = $plugin->getTaskIdentifierPrefix();
                } else {
                    // cancel here, as we might reference a model that can't be constructed
                    // in this case, the field plugin is null
                    $nullPluginDetected = true;
                }
            }
            if ($nullPluginDetected) {
                continue;
            }

            // the foreign table
            $plugin = $foreignAdapter->getPluginInstance('table');
            if ($plugin != null) {
                $precededBy[] = $plugin->getTaskIdentifierPrefix();
            }

            // let the task be preceded by tasks related to the existence the field itself
            $plugin = $this->adapter->getPluginInstance('field', ['field' => $field]);
            if ($plugin != null) {
                $precededBy[] = $plugin->getTaskIdentifierPrefix();
            }

            // the current table
            $plugin = $this->adapter->getPluginInstance('table');
            if ($plugin != null) {
                $precededBy[] = $plugin->getTaskIdentifierPrefix();
            }

            $tasks[] = $this->createTask(
                task::TASK_TYPE_SUGGESTED,
                "ADD_FOREIGNKEY_CONSTRAINT",
                [
                  'field' => $field,
                  'config' => $def,
                ],
                $precededBy
            );
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        $def = parent::getDefinition();
        $res = [];
        foreach ($def as $field => $config) {
            // omit pure structure fields
            if ($this->adapter->config->get('datatype>' . $field) == 'structure') {
                // omit.
            } else {
                $res[$field] = $config;
            }
        }
        return $res;
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
            "SELECT tc.table_schema, tc.table_name, constraint_name, column_name, referenced_table_schema, referenced_table_name, referenced_column_name
        FROM information_schema.table_constraints tc
        INNER JOIN information_schema.key_column_usage kcu
        USING (constraint_catalog, constraint_schema, constraint_name)
        WHERE constraint_type = 'FOREIGN KEY'
        AND tc.table_schema = '{$this->adapter->schema}'
        AND tc.table_name = '{$this->adapter->model}';"
        );

        return $db->getResult();
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
        if ($task->name == "ADD_FOREIGNKEY_CONSTRAINT") {
            $field = $task->data->get('field');
            $config = $task->data->get('config');

            $constraintName = "fkey_" . md5("{$this->adapter->model}_{$config['model']}_{$field}_fkey");

            if (is_array($config['key'])) {
                $fkey = implode(',', array_keys($config['key']));
                $references = implode(',', array_values($config['key']));
            } else {
                $fkey = $field;
                $references = $config['key'];
            }

            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        ADD CONSTRAINT $constraintName
        FOREIGN KEY ($fkey)
        REFERENCES {$config['schema']}.{$config['model']} ($references);"
            );
            return;
        }

        // TODO: Remove / modify foreign key
        // may be abstracted to two tasks, first: delete/drop, then (re)create
        //

        // NOTE: this is not valid for MySQL
        // see: https://stackoverflow.com/questions/14122031/how-to-remove-constraints-from-my-mysql-table/14122155
        if ($task->name == "REMOVE_FOREIGNKEY_CONSTRAINT") {
            $constraintName = $task->data->get('constraint_name');

            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        DROP CONSTRAINT $constraintName;"
            );
        }
    }
}
