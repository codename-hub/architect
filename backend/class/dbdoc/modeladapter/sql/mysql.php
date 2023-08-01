<?php

namespace codename\architect\dbdoc\modeladapter\sql;

use codename\architect\dbdoc\modeladapter\sql;

/**
 * mysql ddl model adapter
 * @package architect
 */
class mysql extends sql
{
    /**
     * {@inheritDoc}
     */
    public function getDriverCompat(): string
    {
        return "mysql";
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginCompat(): array
    {
        return [
          'sql_mysql',
          'sql',
        ];
    }
}
