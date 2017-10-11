<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
abstract class foreign extends \codename\architect\dbdoc\plugin {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('foreign');
  }

}