<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
abstract class field extends \codename\architect\dbdoc\plugin {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $field = $this->parameter['field'];
    return array(
      'field' => $field,
      'notnull' => $this->adapter->config->get('notnull>' . $field),
      'default' => $this->adapter->config->get('default>' . $field),
      // NOTE: 'primary' => true/false -- should be handled in an extra plugin for EACH TABLE ! this is just to overcome some too field-specific stuff
      'primary' => in_array($field, $this->adapter->config->get('primary') ?? array()),
      'datatype' => $this->adapter->config->get('datatype>' . $field),
    );
  }


}