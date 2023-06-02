<?php

namespace codename\architect\deploy;

use codename\core\config;

/**
 * deployment result object
 */
class deploymentresult extends config
{
    /**
     * returns taskresult
     * @return taskresult[]
     */
    public function getTaskResults(): array
    {
        return $this->get('taskresult');
    }
}
