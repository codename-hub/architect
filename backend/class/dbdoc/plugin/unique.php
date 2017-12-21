<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing unique field config in a model
 * @package architect
 */
abstract class unique extends \codename\architect\dbdoc\plugin\modelPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('unique') ?? array();
  }

}