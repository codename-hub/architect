<?php
namespace codename\architect\deploy\task;

use codename\architect\deploy\taskresult;

/**
 * task for running a test
 */
class dummy extends \codename\architect\deploy\task {

  /**
   * @inheritDoc
   */
  public function run(): taskresult
  {

    return new taskresult\text([
      'text' => 'Success!'
    ]);
  }


}
