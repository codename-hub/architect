<?php

namespace codename\architect\dbdoc\plugin\sql\sqlite;

use codename\architect\dbdoc\plugin;
use codename\core\catchableException;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model field data
 * especially count and array of fields / columns (not their datatype and constraints!)
 * @package architect
 */
class fieldlist extends plugin\sql\fieldlist implements partialStatementInterface
{
    /**
     * {@inheritDoc}
     */
    public function Compare(): array
    {
        $definition = $this->getDefinition();
//        $structure = $this->getStructure();

        // fields contained in model, that are not in the database table
//        $missing = array_diff($definition, $structure);

        // columns in the database table, that are simply "too much" (not in the model definition)
//        $toomuch = array_diff($structure, $definition);

        // TODO: handle toomuch
        // e.g. check for prefix __old_
        // of not, create task to rename column
        // otherwise, recommend harddeletion ?

        $modificationTasks = [];

        foreach ($definition as $field) {
            $plugin = $this->adapter->getPluginInstance(
                'field',
                [
                  'field' => $field,
                ],
                $this->virtual // virtual on need.
            );

            if ($plugin != null) {
                // add this plugin to the first
                // $this->adapter->addToQueue($plugin, true);
                if (count($compareTasks = $plugin->Compare()) > 0) {
                    $modificationTasks = array_merge($modificationTasks, $compareTasks);
                }
            }
        }

        return $modificationTasks;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        $db = $this->getSqlAdapter()->db;
        $db->query("PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');");

        $res = $db->getResult();

        $columns = [];
        foreach ($res as $r) {
            $columns[] = $r['name'];
        }

        return $columns;
    }

    /**
     * [getPartialStatement description]
     * @return array [type] [description]
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function getPartialStatement(): array
    {
        $definition = $this->getDefinition();
        $partialStatements = [];
        foreach ($definition as $field) {
            $plugin = $this->adapter->getPluginInstance(
                'field',
                [
                  'field' => $field,
                ],
                $this->virtual // virtual on need.
            );
            if ($plugin instanceof field) {
                $partialStatement = $plugin->getPartialStatement();
                if ($partialStatement) {
                    //
                    // getPartialStatement() might return a NULL value
                    // (e.g. if virtual/collection field)
                    // only add, if there's a value
                    //
                    $partialStatements[] = $partialStatement;
                }
            }
        }
        return $partialStatements;
    }
}
