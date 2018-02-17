<?php
namespace codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
abstract class field extends \codename\architect\dbdoc\plugin\modelPrefix {

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $field = $this->parameter['field'];
    $def = array(
      'field' => $field,
      'notnull' => in_array($field, $this->adapter->config->get('notnull') ?? []),
      // 'default' => $this->adapter->config->get('default>' . $field),
      // NOTE: 'primary' => true/false -- should be handled in an extra plugin for EACH TABLE ! this is just to overcome some too field-specific stuff
      'primary' => in_array($field, $this->adapter->config->get('primary') ?? array()),
      'foreign' => is_array($field) ? null : $this->adapter->config->get('foreign>' . $field),
      'datatype' => is_array($field) ? null : $this->adapter->config->get('datatype>' . $field),
      'collection' => is_array($field) ? null : $this->adapter->config->get('collection>' . $field),
      'children' => is_array($field) ? null : $this->adapter->config->get('children>' . $field),
      'options' => is_array($field) ? null : $this->adapter->config->get('options>' . $field) ?? []
    );

    if($this->adapter->config->exists('default')) {
      $def['default'] = $this->adapter->config->get('default>' . $field);
    }
    return $def;
  }


}