<?php
namespace codename\architect\validator\number;
use codename\architect\dbdoc\task;

class tasktype extends \codename\core\validator\number {

  /**
   * @inheritDoc
   */
  public function __CONSTRUCT(bool $nullAllowed = false) {
    parent::__CONSTRUCT(
      false,
      min(array_keys(task::TASK_TYPES)),
      max(array_keys(task::TASK_TYPES))
    );
  }

  /**
   * @inheritDoc
   */
  public function validate($value) : array
  {
    $errors = parent::validate($value);

    if(!array_key_exists($value, task::TASK_TYPES)) {
      $this->errorstack->addError('VALUE', 'TASK_TYPE_UNKNOWN', $value);
    }

    return $this->errorstack->getErrors();
  }
}