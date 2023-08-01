<?php

namespace codename\architect\dbdoc\modeladapter;

use codename\architect\config\environment;
use codename\architect\dbdoc\dbdoc;
use codename\architect\dbdoc\modeladapter;
use codename\core\config;

/**
 * bare model adapter
 * @package architect
 */
class bare extends modeladapter
{
    /**
     * {@inheritDoc}
     */
    public function __construct(dbdoc $dbdocInstance, string $schema, string $model, config $config, environment $environment)
    {
        parent::__construct($dbdocInstance, $schema, $model, $config, $environment);
    }

    /**
     * {@inheritDoc}
     */
    public function getDriverCompat(): string
    {
        return 'bare';
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginCompat(): array
    {
        return ['bare'];
    }

    /**
     * {@inheritDoc}
     */
    public function getPlugins(): array
    {
        return [// no plugins!
        ];
    }
}
