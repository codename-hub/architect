<?php

namespace codename\architect\dbdoc\modeladapter;

use codename\architect\app;
use codename\architect\config\environment;
use codename\architect\dbdoc\dbdoc;
use codename\architect\dbdoc\modeladapter;
use codename\core\config;
use codename\core\database;
use codename\core\exception;
use codename\core\value\text\objectidentifier;
use codename\core\value\text\objecttype;
use ReflectionException;

/**
 * sql ddl adapter
 * @package architect
 */
abstract class sql extends modeladapter
{
    /**
     * Contains the database connection
     * @var null|database
     */
    public ?database $db = null;

    /**
     * {@inheritDoc}
     * @param dbdoc $dbdocInstance
     * @param string $schema
     * @param string $model
     * @param config $config
     * @param environment $environment
     * @throws ReflectionException
     * @throws exception
     */
    public function __construct(dbdoc $dbdocInstance, string $schema, string $model, config $config, environment $environment)
    {
        parent::__construct($dbdocInstance, $schema, $model, $config, $environment);

        // establish database connection
        // we require a special environment configuration
        // in the environment
        $this->db = $this->getDatabaseConnection($this->config->get('connection'));
    }

    /**
     * [loadDatabaseConnection description]
     * @param string $identifier [description]
     * @return database [type]             [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function getDatabaseConnection(string $identifier = 'default'): database
    {
        $dbValueObjecttype = new objecttype('database');
        $dbValueObjectidentifier = new objectidentifier($identifier);
        $object = app::getForeignClient($this->environment, $dbValueObjecttype, $dbValueObjectidentifier);
        if ($object instanceof database) {
            return $object;
        }
        throw new exception('EXCEPTION_GETDATABASECONNECTION_WRONG_OBJECT', exception::$ERRORLEVEL_FATAL);
    }

    /**
     * {@inheritDoc}
     */
    public function getPlugins(): array
    {
        return [
          'initial',
        ];
    }
}
