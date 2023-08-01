<?php

namespace codename\architect\deploy\taskresult;

use codename\architect\deploy\taskresult;

/**
 * deployment task result object
 */
class text extends taskresult
{
    /**
     * {@inheritDoc}
     */
    public function formatAsString(): string
    {
        return $this->get('text');
    }
}
