<?php
namespace codename\architect\deploy\task;

use codename\architect\deploy\taskresult;

/**
 * task for running dbdoc
 */
class dbdoc extends \codename\architect\deploy\task {

  /**
   * @inheritDoc
   */
  public function run(): \codename\architect\deploy\taskresult
  {
    $dbdoc = new \codename\architect\dbdoc\dbdoc(
      $this->getDeploymentInstance()->getApp(),
      $this->getDeploymentInstance()->getVendor()
    );

    $exec = false;
    $execTasks = [];

    try {
      $res = $dbdoc->run($exec, $execTasks);
      $textResult = "Dbdoc execution success with no-dryrun: $exec and stuff";
    } catch (\Exception $e) {
      $textResult = "Dbdoc exception: " . $e->getCode() . ' ' . $e->getMessage();
    }

    return new taskresult\text([
      'text' => $textResult
    ]);
  }

}
