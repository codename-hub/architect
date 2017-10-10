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
      'field' => $this->parameter['field'],
      // 'nullable' => null,
      // 'default' => null,
      // NOTE: 'primary' => true/false -- should be handled in an extra plugin for EACH TABLE ! this is just to overcome some too field-specific stuff
      'primary' => in_array($field, $this->adapter->config->get('primary')),
      'datatype' => $this->adapter->config->get('datatype>' . $field),
      'datatype_override' => $this->adapter->config->get('datatype_override>' . $field)
    );
  }


}