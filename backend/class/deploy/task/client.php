<?php

namespace codename\architect\deploy\task;

use codename\architect\app;
use codename\architect\deploy\task;
use codename\core\exception;
use codename\core\value\text\objectidentifier;
use codename\core\value\text\objecttype;
use ReflectionException;

/**
 * base class for doing client (abstract)-specific tasks
 */
abstract class client extends task
{
    /**
     * [getClientInstance description]
     * @param string $clientName [description]
     * @return object
     * @throws ReflectionException
     * @throws exception
     */
    protected function getClientInstance(string $clientName): object
    {
        $dbValueObjecttype = new objecttype($this->getClientObjectTypeName());
        $dbValueObjectidentifier = new objectidentifier($clientName);
        return app::getForeignClient(
            $this->getDeploymentInstance()->getVirtualEnvironment(),
            $dbValueObjecttype,
            $dbValueObjectidentifier,
            false
        );
    }

    /**
     * returns a name of an object type for getting the client instance
     * @return string
     */
    abstract protected function getClientObjectTypeName(): string;
}
