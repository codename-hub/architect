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
 * plugin for providing and comparing fulltext / indices field config in a model
 * @package architect
 */
class fulltext extends \codename\architect\dbdoc\plugin\fulltext
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

        foreach ($structure as $strucName => $struc) {
            // get ordered (?) column_names
            $fulltextColumnNames = array_map(
                function ($spec) {
                    return $spec['column_name'];
                },
                $struc
            );

            // reduce to string, if only one element
            $fulltextColumnNames = count($fulltextColumnNames) == 1 ? $fulltextColumnNames[0] : $fulltextColumnNames;

            // compare!
            if (in_array($fulltextColumnNames, $definition)) {
                // constraint exists and is correct
                $valid[] = $fulltextColumnNames;
            } else {
                $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_FULLTEXT", [
                  'fulltext_name' => $strucName,
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
            $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_FULLTEXT", [
              'fulltext_columns' => $def,
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
            "SELECT DISTINCT tc.table_schema, tc.table_name, s.index_name, tc.constraint_name, s.column_name, s.seq_in_index
      FROM information_schema.statistics s
      LEFT OUTER JOIN information_schema.table_constraints tc
          ON tc.table_schema = s.table_schema
             AND tc.table_name = s.table_name
             AND s.index_name = tc.constraint_name
      WHERE s.index_name NOT IN ('PRIMARY')
            AND s.table_schema = '{$this->adapter->schema}'
            AND s.table_name = '{$this->adapter->model}'
            AND s.index_type = 'FULLTEXT'"
        );

        $allFulltext = $db->getResult();

        $fulltextGroups = [];

        // perform grouping
        foreach ($allFulltext as $fulltext) {
            if (array_key_exists($fulltext['index_name'], $fulltextGroups)) {
                // match to existing group
                foreach ($fulltextGroups as $groupName => $group) {
                    if ($fulltext['index_name'] == $groupName) {
                        $fulltextGroups[$groupName][] = $fulltext;
                        break;
                    }
                }
            } else {
                // create new group
                $fulltextGroups[$fulltext['index_name']][] = $fulltext;
            }
        }

        $sortedfulltextGroups = [];
        // sort!
        foreach ($fulltextGroups as $groupName => $group) {
            usort($group, function ($left, $right) {
                return $left['seq_in_index'] > $right['seq_in_index'];
            });
            $sortedfulltextGroups[$groupName] = $group;
        }

        return $sortedfulltextGroups;
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

        if ($task->name == "ADD_FULLTEXT") {
            $fulltextColumns = $task->data->get('fulltext_columns');
            $columns = is_array($fulltextColumns) ? implode(',', $fulltextColumns) : $fulltextColumns;
            $fulltextName = 'fulltext_' . md5($columns);

            $db->query(
                "CREATE FULLTEXT INDEX $fulltextName ON {$this->adapter->schema}.{$this->adapter->model} ($columns) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;"
            );
        }

        if ($task->name == "REMOVE_FULLTEXT") {
            // simply drop fulltext by fulltext_name
            $fulltextName = $task->data->get('fulltext_name');

            $db->query(
                "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} DROP INDEX $fulltextName;"
            );
        }
    }
}
