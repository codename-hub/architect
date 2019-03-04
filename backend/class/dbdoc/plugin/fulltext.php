<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing fulltext field config in a model
 * @package architect
 */
abstract class fulltext extends \codename\architect\dbdoc\plugin\modelPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('fulltext') ?? array();
  }

}
