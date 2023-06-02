<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing unique field config in a model
 * @package architect
 */
abstract class unique extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return $this->adapter->config->get('unique') ?? [];
    }
}
