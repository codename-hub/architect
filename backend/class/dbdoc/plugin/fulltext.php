<?php

namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing fulltext field config in a model
 * @package architect
 */
abstract class fulltext extends modelPrefix
{
    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        return $this->adapter->config->get('fulltext') ?? [];
    }
}
