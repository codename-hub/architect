<?php

namespace codename\architect\deploy\task;

use codename\architect\app;
use codename\architect\config\json\virtualAppstack;
use codename\architect\deploy\task;
use codename\architect\model\schematic\sql\dynamic;
use codename\core\exception;
use codename\core\value\text\objectidentifier;
use codename\core\value\text\objecttype;
use ReflectionException;

/**
 * base class for doing model-specific tasks
 */
abstract class model extends task
{
    /**
     * the model name
     * @var string
     */
    protected string $model;

    /**
     * the schema name
     * @var string
     */
    protected string $schema;

    /**
     * {@inheritDoc}
     */
    protected function handleConfig(): void
    {
        parent::handleConfig();
        $this->model = $this->config->get('model');
        $this->schema = $this->config->get('schema');
    }

    /**
     * [getModelInstance description]
     * @param string|null $schemaName [description]
     * @param string|null $modelName [description]
     * @return \codename\core\model         [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function getModelInstance(string $schemaName = null, string $modelName = null): \codename\core\model
    {
        if (!$schemaName) {
            $schemaName = $this->schema;
        }
        if (!$modelName) {
            $modelName = $this->model;
        }
        $useAppstack = $this->getDeploymentInstance()->getAppstack();
        $modelconfig = (new virtualAppstack("config/model/" . $schemaName . '_' . $modelName . '.json', true, true, $useAppstack))->get();
        $modelconfig['appstack'] = $useAppstack;
        $model = new dynamic($modelconfig, function (string $connection, bool $storeConnection = false) {
            $dbValueObjecttype = new objecttype('database');
            $dbValueObjectidentifier = new objectidentifier($connection);
            return app::getForeignClient(
                $this->getDeploymentInstance()->getVirtualEnvironment(),
                $dbValueObjecttype,
                $dbValueObjectidentifier,
                $storeConnection
            );
        });
        $model->setConfig(null, $schemaName, $modelName);
        return $model;
    }
}
