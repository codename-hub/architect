<?php

namespace codename\architect\dbdoc\plugin\sql;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
class initial extends \codename\architect\dbdoc\plugin\initial
{
    /**
     * {@inheritDoc}
     */
    public function Compare(): array
    {
        // call plugins

        // check for user existence
        $plugin = $this->adapter->getPluginInstance('user');
        if ($plugin != null) {
            // add this plugin to the first
            $this->adapter->addToQueue($plugin, true);
        }

        // we can simply continue constructing our database and tables
        // as the user is only relevant for authentication
        // constructing schema, table, fields and constraints
        // does not depend on it.
        $plugin = $this->adapter->getPluginInstance('schema');
        if ($plugin != null) {
            // add this plugin to the first
            $this->adapter->addToQueue($plugin, true);
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        return [];
    }
}
