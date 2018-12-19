<?php
namespace codename\architect\deploy;

/**
 * deployment result object
 */
class deploymentresult extends \codename\core\config {

  /**
   * returns taskresults
   * @return taskresult[]
   */
  public function getTaskResults() : array {
    return $this->get('taskresult');
  }
}
