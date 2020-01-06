<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing fulltext / indices field config in a model
 * @package architect
 */
class fulltext extends \codename\architect\dbdoc\plugin\fulltext {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
  
  /**
   * @inheritDoc
   */
  public function getStructure() : array
  {
    return [];
  }
}
