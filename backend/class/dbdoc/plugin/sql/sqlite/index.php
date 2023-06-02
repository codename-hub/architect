<?php

namespace codename\architect\dbdoc\plugin\sql\sqlite;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;

/**
 * we may add some kind of loading prevention, if some classes are not loaded/undefined
 * as we're using a filename that is the same as standard php scripts loaded for directories
 * if none is given
 */

/**
 * plugin for providing and comparing index / indices field config in a model
 * @package architect
 */
class index extends \codename\architect\dbdoc\plugin\sql\index
{
    use modeladapterGetSqlAdapter;

    /**
     * {@inheritDoc}
     */
    public function Compare(): array
    {
        $tasks = [];

        $definition = $this->getDefinition();

        // virtual = assume empty structure
        $structure = $this->virtual ? [] : $this->getStructure();

        $valid = [];
        $missing = [];

        foreach ($structure as $strucName => $struc) {
            // get ordered (?) column_names
            $indexColumnNames = array_map(
                function ($spec) {
                    return $spec['column_name'];
                },
                $struc
            );

            // reduce to string, if only one element
            $indexColumnNames = count($indexColumnNames) == 1 ? $indexColumnNames[0] : $indexColumnNames;

            // compare!
            if (in_array($indexColumnNames, $definition)) {
                // constraint exists and is correct
                $valid[] = $indexColumnNames;
            } else {
                $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_INDEX", [
                  'index_name' => $strucName,
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
            $columns = is_array($def) ? implode(',', $def) : $def;
            $indexName = 'index_' . md5("{$this->adapter->schema}.{$this->adapter->model}-" . $columns); // prepend schema+model

            $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", [
              'index_name' => $indexName,
              'index_columns' => $def,
            ]);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        $db = $this->getSqlAdapter()->db;

        $db->query("PRAGMA index_list('{$this->adapter->schema}.{$this->adapter->model}')");

        //
        // NOTE: we removed the following WHERE-component:
        // AND tc.constraint_name IS NULL
        // and replaced it with a check for just != PRIMARY
        // because we may have constraints attached (foreign keys!)
        // So, this plugin now handles ALL indexes,
        // - explicit indexes (via "index" key in model config)
        // - implicit indexes (unique & foreign keys)
        //

        $allIndices = $db->getResult();

        $indexGroups = [];

        // perform grouping
        foreach ($allIndices as $index) {
            // Compat mapping to generic index plugin
            $index['index_name'] = $index['name'];

            $db->query("PRAGMA index_info('{$index['index_name']}')");
            $indexInfoRes = $db->getResult();

            foreach ($indexInfoRes as $indexColumn) {
                $indexGroups[$index['index_name']][] = array_merge(
                    $index,
                    $indexColumn,
                    ['column_name' => $indexColumn['name']]
                );
            }
        }

        $sortedIndexGroups = [];
        // sort!
        foreach ($indexGroups as $groupName => $group) {
            usort($group, function ($left, $right) {
                return $left['seqno'] > $right['seqno'];
            });
            $sortedIndexGroups[$groupName] = $group;
        }

        return $sortedIndexGroups;
    }


    /**
     * {@inheritDoc}
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;

        if ($task->name == "ADD_INDEX") {
            $indexColumns = $task->data->get('index_columns');
            $columns = is_array($indexColumns) ? implode(',', $indexColumns) : $indexColumns;
            $indexName = 'index_' . md5("{$this->adapter->schema}.{$this->adapter->model}-" . $columns);// prepend schema+model

            $db->query(
                "CREATE INDEX $indexName ON '{$this->adapter->schema}.{$this->adapter->model}' ($columns);"
            );
        }

        if ($task->name == "REMOVE_INDEX") {
            // simply drop index by index_name
            $indexName = $task->data->get('index_name');


            $db->query(
                "DROP INDEX IF EXISTS $indexName;" // ON '{$this->adapter->schema}.{$this->adapter->model}'
            );
        }
    }
}
