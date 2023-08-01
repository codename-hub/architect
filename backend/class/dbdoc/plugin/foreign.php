<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
abstract class foreign extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return $this->adapter->config->get('foreign') ?? [];
    }
}
