<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model schema data
 * @package architect
 */
class schema extends plugin\schema
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
        $structure = $this->virtual ? false : $this->getStructure();

        if (!$structure) {
            // schema/database does not exist
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_SCHEMA", [
              'schema' => $definition,
            ]);
        }

        // schema/database exists
        // start subroutine plugins
        $plugin = $this->adapter->getPluginInstance('table', [], !$structure);
        if ($plugin != null) {
            // add this plugin to the first
            $this->adapter->addToQueue($plugin, true);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     * @throws ReflectionException
     * @throws exception
     */
    public function getStructure(): mixed
    {
        $db = $this->getSqlAdapter()->db;
        $db->query(
            "SELECT exists(select 1 FROM information_schema.schemata WHERE schema_name = '{$this->adapter->schema}') as result;"
        );
        return $db->getResult()[0]['result'];
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

        if ($task->name == 'CREATE_SCHEMA') {
            // CREATE SCHEMA
            $db->query("CREATE SCHEMA `{$this->adapter->schema}`;");
        }
    }
}
