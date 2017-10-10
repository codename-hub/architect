<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data
 * @package architect
 */
abstract class fieldlist extends \codename\architect\dbdoc\plugin {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('field');
  }

}