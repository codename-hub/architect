<?php
namespace codename\architect\deploy;

/**
 * deployment task result object
 */
abstract class taskresult extends \codename\core\config {

  /**
   * [formatAsString description]
   * @return string [description]
   */
  public abstract function formatAsString() : string;
}
