<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing database data
 * @package architect
 */
abstract class database extends \codename\architect\dbdoc\plugin {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('field');
  }

}