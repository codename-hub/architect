<?php

namespace codename\architect\dbdoc\plugin;

use codename\architect\dbdoc\plugin;

/**
 * plugin for connection
 * mostly just for correct task prefixing
 * @package architect
 */
abstract class connectionPrefix extends plugin
{
    /**
     * {@inheritDoc}
     */
    protected function getTaskIdentifierPrefix(): string
    {
        return parent::getTaskIdentifierPrefix() . "{$this->adapter->config->get('connection')}_";
    }
}
