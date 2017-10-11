<?php
namespace codename\architect\dbdoc\plugin\sql;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
class initial extends \codename\architect\dbdoc\plugin\initial {

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    // call plugins

    $plugin = $this->adapter->getPluginInstance('schema');
    if($plugin != null) {
      // add this plugin to the first
      $this->adapter->addToQueue($plugin, true);
    }

    return array();
  }

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
  }

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
  }

}
