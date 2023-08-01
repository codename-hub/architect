<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\plugin;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing model field data
 * especially count and array of fields / columns (not their datatype and constraints!)
 * @package architect
 */
class fieldlist extends plugin\fieldlist
{
    use modeladapterGetSqlAdapter;

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
                $this->adapter->addToQueue($plugin, true);
            }
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return $this->adapter->config->get('field');
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
            "SELECT column_name
      FROM information_schema.columns
      WHERE table_name = '{$this->adapter->model}'
      AND table_schema = '{$this->adapter->schema}'
    ;"
        );
        $res = $db->getResult();

        $columns = [];
        foreach ($res as $r) {
            $columns[] = $r['column_name'];
        }

        return $columns;
    }
}
