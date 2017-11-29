<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing table data
 * @package architect
 */
abstract class table extends \codename\architect\dbdoc\plugin\modelPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->model;
  }

}