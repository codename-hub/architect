<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing schema data
 * @package architect
 */
abstract class schema extends \codename\architect\dbdoc\plugin\connectionPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->schema;
  }

}