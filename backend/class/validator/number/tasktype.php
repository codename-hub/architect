<?php

namespace codename\architect\validator\number;

use codename\architect\dbdoc\task;
use codename\core\exception;
use codename\core\validator\number;

class tasktype extends number
{
    /**
     * {@inheritDoc}
     * @param bool $nullAllowed
     * @throws exception
     */
    public function __construct(bool $nullAllowed = false)
    {
        if ($nullAllowed !== false) {
            throw new exception('EXCEPTION_VALIDATOR_NUMBER_TASKTYPE_NULL_NOT_ALLOWED', exception::$ERRORLEVEL_FATAL);
        }

        parent::__construct(
            false,
            min(array_keys(task::TASK_TYPES)),
            max(array_keys(task::TASK_TYPES))
        );
    }

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value): array
    {
        $errors = parent::validate($value);
        if (count($errors)) {
            return $this->errorstack->getErrors();
        }

        if (!array_key_exists($value, task::TASK_TYPES)) {
            $this->errorstack->addError('VALUE', 'TASK_TYPE_UNKNOWN', $value);
        }

        return $this->errorstack->getErrors();
    }
}
