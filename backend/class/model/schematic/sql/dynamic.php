<?php

namespace codename\architect\model\schematic\sql;

use codename\core\config;
use codename\core\config\json;
use codename\core\exception;
use codename\core\model;
use codename\core\model\schematic\sql;
use ReflectionException;

/**
 * dynamic SQL model
 */
class dynamic extends sql
{
    /**
     * workaround to get db from another appstack
     * @var callable
     */
    protected $getDbCallback = null;

    /**
     * @param array $modeldata
     * @param callable $getDbCallback
     */
    public function __construct(array $modeldata, callable $getDbCallback)
    {
        $this->getDbCallback = $getDbCallback;
        parent::__construct($modeldata);
    }

    /**
     * @param string|null $connection
     * @param string $schema
     * @param string $table
     * @return model
     * @throws ReflectionException
     * @throws exception
     */
    public function setConfig(?string $connection, string $schema, string $table): model
    {
        $this->schema = $schema;
        $this->table = $table;

        if (!$this->config) {
            $this->config = $this->loadConfig();
        }

        // Connection now defined in model .json
        if ($this->config->exists("connection")) {
            $connection = $this->config->get("connection");
        } else {
            $connection = 'default';
        }

        $getDbCallback = $this->getDbCallback;
        $this->db = $getDbCallback($connection, $this->storeConnection);

        return $this;
    }

    /**
     * loads a new config file (uncached)
     * @return config
     * @throws ReflectionException
     * @throws exception
     */
    protected function loadConfig(): config
    {
        if ($this->modeldata->exists('appstack')) {
            return new json('config/model/' . $this->schema . '_' . $this->table . '.json', true, false, $this->modeldata->get('appstack'));
        } else {
            return new json('config/model/' . $this->schema . '_' . $this->table . '.json', true);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getType(): string
    {
        // TODO: make dynamic, based on ENV setting!
        return 'mysql';
    }
}
