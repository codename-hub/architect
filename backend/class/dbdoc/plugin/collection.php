<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing collection field config in a model
 * @package architect
 */
abstract class collection extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): mixed
    {
        return $this->adapter->config->get('collection') ?? [];
    }
}
