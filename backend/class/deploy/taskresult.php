<?php

namespace codename\architect\deploy;

use codename\core\config;

/**
 * deployment task result object
 */
abstract class taskresult extends config
{
    /**
     * [formatAsString description]
     * @return string [description]
     */
    abstract public function formatAsString(): string;
}
