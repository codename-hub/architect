<?php

namespace codename\architect\dbdoc\modeladapter;

use codename\core\exception;

trait modeladapterGetSqlAdapter
{
    /**
     * [getSqlAdapter description]
     * @return sql [description]
     * @throws exception
     */
    protected function getSqlAdapter(): sql
    {
        if ($this->adapter instanceof sql) {
            return $this->adapter;
        }
        throw new exception('EXCEPTION_GETSQLADAPTER_WRONG_OBJECT', exception::$ERRORLEVEL_FATAL);
    }
}
