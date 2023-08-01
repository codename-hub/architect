<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * we may add some kind of loading prevention, if some classes are not loaded/undefined
 * as we're using a filename that is the same as standard php scripts loaded for directories
 * if none is given
 */

/**
 * plugin for providing and comparing index / indices field config in a model
 * @package architect
 */
class index extends \codename\architect\dbdoc\plugin\index
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

        // $fieldsOnly = $this->parameter['fields_only'] ?? null;
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
            $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_INDEX", [
              'index_columns' => $def,
            ]);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        // "index" specified in model definition
        $definition = parent::getDefinition();

        //
        // NOTE: Bad issue on 2019-02-20:
        // Index Plugin wants to remove Indexes created
        // for Foreign & Unique Keys, as well as Primary Keys
        // after the change in structure retrieval (constraint_name is null)
        // therefore, we have to check those keys, too.
        //
        //
        // for mysql/sql, merge in foreign keys!
        $foreignPlugin = $this->adapter->getPluginInstance('foreign', [], true);
        $foreignKeys = $foreignPlugin->getDefinition();
        foreach ($foreignKeys as $fkey => $fkeyConfig) {
            if (is_array($fkeyConfig['key'])) {
                // multi-component foreign key - $fkey is NOT a field name, use 'key'-keys
                $definition[] = array_keys($fkeyConfig['key']);
            } else {
                // just use the foreign key definition name (this is the current table's key to be used)
                $definition[] = $fkey;
            }
        }

        // for mysql/sql, merge in unique keys!
        if ($this->adapter->getDriverCompat() == 'mysql') {
            $uniquePlugin = $this->adapter->getPluginInstance('unique', [], true);
            $uniqueKeys = $uniquePlugin->getDefinition();
            foreach ($uniqueKeys as $uniqueKey) {
                $definition[] = $uniqueKey;
            }
        }

        //
        // make unique!
        // otherwise, we may get duplicates
        // NOTE:
        // this may cause a problem, when creating a foreign key at the same time?
        //
        return array_values(array_unique($definition, SORT_REGULAR));
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
            "SELECT DISTINCT tc.table_schema, tc.table_name, s.index_name, tc.constraint_name, s.column_name, s.seq_in_index
      FROM information_schema.statistics s
      LEFT OUTER JOIN information_schema.table_constraints tc
          ON tc.table_schema = s.table_schema
             AND tc.table_name = s.table_name
             AND s.index_name = tc.constraint_name
      WHERE s.index_name NOT IN ('PRIMARY')
            AND s.table_schema = '{$this->adapter->schema}'
            AND s.table_name = '{$this->adapter->model}'
            AND s.index_type != 'FULLTEXT'"
        );

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
            if (array_key_exists($index['index_name'], $indexGroups)) {
                // match to existing group
                foreach ($indexGroups as $groupName => $group) {
                    if ($index['index_name'] == $groupName) {
                        $indexGroups[$groupName][] = $index;
                        break;
                    }
                }
            } else {
                // create new group
                $indexGroups[$index['index_name']][] = $index;
            }
        }

        $sortedIndexGroups = [];
        // sort!
        foreach ($indexGroups as $groupName => $group) {
            usort($group, function ($left, $right) {
                return $left['seq_in_index'] > $right['seq_in_index'];
            });
            $sortedIndexGroups[$groupName] = $group;
        }

        return $sortedIndexGroups;
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

        if ($task->name == "ADD_INDEX") {
            $indexColumns = $task->data->get('index_columns');
            $columns = is_array($indexColumns) ? implode(',', $indexColumns) : $indexColumns;
            $indexName = 'index_' . md5($columns);

            $db->query(
                "CREATE INDEX $indexName ON {$this->adapter->schema}.{$this->adapter->model} ($columns);"
            );
        }

        if ($task->name == "REMOVE_INDEX") {
            // simply drop index by index_name
            $indexName = $task->data->get('index_name');

            $db->query(
                "DROP INDEX IF EXISTS $indexName ON {$this->adapter->schema}.{$this->adapter->model};"
            );
        }
    }
}
