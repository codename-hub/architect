<?php

namespace codename\architect\dbdoc\modeladapter\sql;

use codename\architect\dbdoc\modeladapter\sql;

/**
 * sqlite ddl model adapter
 * @package architect
 */
class sqlite extends sql
{
    /**
     * {@inheritDoc}
     */
    public function getDriverCompat(): string
    {
        return "sqlite";
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginCompat(): array
    {
        return [
          'sql_sqlite',
          'sql',
        ];
    }
}
