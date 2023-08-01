<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing table data
 * @package architect
 */
abstract class table extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): ?string
    {
        return $this->adapter->model;
    }
}
