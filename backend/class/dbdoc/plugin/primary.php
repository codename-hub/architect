<?php
namespace codename\architect\dbdoc\plugin;
use codename\core\catchableException;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
abstract class primary extends \codename\architect\dbdoc\plugin {

  /**
   * [EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MISSING description]
   * @var string
   */
  const EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MISSING = "EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MISSING"

  /**
   * [EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MULTIPLE description]
   * @var string
   */
  const EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MULTIPLE = "EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MULTIPLE";

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    $primary = $this->adapter->config->get('primary');
    if(count($primary) === 0) {
      throw new catchableException(self::EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MISSING, catchableException::$ERRORLEVEL_FATAL, $this->adapter->schema);
    } else if(count($primary) > 1) {
      throw new catchableException(self::EXCEPTION_DBDOC_PLUGIN_PRIMARY_GETDEFINITION_MULTIPLE, catchableException::$ERRORLEVEL_FATAL, $this->adapter->schema);
    }
    return $primary[0];
  }

}