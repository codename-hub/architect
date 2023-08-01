<?php

namespace codename\architect\deploy\task\model;

use codename\architect\deploy\task\model;
use codename\architect\deploy\taskresult;
use codename\architect\deploy\taskresult\text;
use codename\core\exception;
use ReflectionException;

/**
 * base class for doing model-specific tasks
 */
class query extends model
{
    /**
     * {@inheritDoc}
     * @return taskresult
     * @throws ReflectionException
     * @throws exception
     */
    public function run(): taskresult
    {
        $res = $this->getModelInstance()->setLimit(1)->search()->getResult();

        return new text([
          'text' => print_r($res, true),
        ]);
    }
}
