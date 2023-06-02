<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model table data
 * @package architect
 */
class table extends plugin\table
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

        // if virtual, simulate nonexisting structure
        $structure = $this->virtual ? false : $this->getStructure();

        // structure doesn't exist
        if (!$structure) {
            // table does not exist
            // create table
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_TABLE", [
              'table' => $definition,
            ]);
        }

        $tasks = $this->getCheckStructure($tasks);

        // either run sub-plugins virtually or the 'hard' way

        // execute plugin for indices
        $plugin = $this->adapter->getPluginInstance('index', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        // execute plugin for fulltext
        $plugin = $this->adapter->getPluginInstance('fulltext', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        // execute plugin for unique constraints
        $plugin = $this->adapter->getPluginInstance('unique', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        // collection key plugin
        $plugin = $this->adapter->getPluginInstance('collection', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        // foreign key plugin
        $plugin = $this->adapter->getPluginInstance('foreign', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        //N fieldlist
        $plugin = $this->adapter->getPluginInstance('fieldlist', [], $this->virtual);
        if ($plugin != null) {
            $this->adapter->addToQueue($plugin, true);
        }

        // pkey first
        $plugin = $this->adapter->getPluginInstance('primary', [], $this->virtual);
        if ($plugin != null) {
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
            "SELECT exists(select 1 FROM information_schema.tables WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}') as result;"
        );
        return $db->getResult()[0]['result'];
    }

    /**
     * @param $tasks
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    protected function getCheckStructure($tasks): array
    {
        $db = $this->getSqlAdapter()->db;
        $db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}';"
        );
        $columns = $db->getResult();

        if (count($columns)) {
            $fields = $this->adapter->config->get()['field'] ?? [];

            foreach ($columns as $column) {
                if (!in_array($column['COLUMN_NAME'], $fields)) {
                    $tasks[] = $this->createTask(task::TASK_TYPE_OPTIONAL, "DELETE_COLUMN", [
                      'table' => $this->adapter->model,
                      'field' => $column['COLUMN_NAME'],
                    ]);
                }
            }
        }
        return $tasks;
    }
}
