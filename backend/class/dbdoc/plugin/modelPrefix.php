<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for model
 * mostly just for correct task prefixing
 * @package architect
 */
abstract class modelPrefix extends schemaPrefix
{
    /**
     * {@inheritDoc}
     */
    protected function getTaskIdentifierPrefix(): string
    {
        return parent::getTaskIdentifierPrefix() . "{$this->adapter->model}_";
    }
}
