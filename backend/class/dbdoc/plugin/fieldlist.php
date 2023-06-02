<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data
 * @package architect
 */
abstract class fieldlist extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return $this->adapter->config->get('field');
    }
}
