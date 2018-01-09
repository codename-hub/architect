<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing collection field config in a model
 * @package architect
 */
abstract class collection extends \codename\architect\dbdoc\plugin\modelPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('collection') ?? array();
  }

}