<?php
namespace codename\architect\deploy\task\model;


/**
 * base class for doing model-specific tasks
 */
class query extends \codename\architect\deploy\task\model {

  /**
   * @inheritDoc
   */
  public function run(): \codename\architect\deploy\taskresult
  {
    $res = $this->getModelInstance()->setLimit(1)->search()->getResult();

    return new \codename\architect\deploy\taskresult\text([
      'text' => print_r($res, true)
    ]);
  }

}
