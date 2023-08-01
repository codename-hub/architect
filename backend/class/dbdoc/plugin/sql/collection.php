<?php

namespace codename\architect\dbdoc\plugin\sql;

/**
 * plugin for providing and comparing collection field config in a model
 * @package architect
 */
class collection extends \codename\architect\dbdoc\plugin\collection
{
    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function Compare(): array
    {
        //
        // TODO: Check, if the given collection config is correct
        //

        return [];
    }
}
