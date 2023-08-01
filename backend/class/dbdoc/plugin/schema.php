<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing schema data
 * @package architect
 */
abstract class schema extends connectionPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): ?string
    {
        return $this->adapter->schema;
    }
}
