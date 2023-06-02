<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for schema
 * mostly just for correct task prefixing
 * @package architect
 */
abstract class schemaPrefix extends connectionPrefix
{
    /**
     * {@inheritDoc}
     */
    protected function getTaskIdentifierPrefix(): string
    {
        return parent::getTaskIdentifierPrefix() . "{$this->adapter->schema}_";
    }
}
