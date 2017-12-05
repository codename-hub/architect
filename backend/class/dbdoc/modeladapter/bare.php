<?php
namespace codename\architect\dbdoc\modeladapter;
use \codename\architect\app;

/**
 * bare model adapter
 * @package architect
 */
class bare extends \codename\architect\dbdoc\modeladapter {

  /**
   * @inheritDoc
   */
  public function __construct(\codename\architect\dbdoc\dbdoc $dbdocInstance, string $schema, string $model, \codename\core\config $config, \codename\architect\config\environment $environment)
  {
    parent::__construct($dbdocInstance, $schema, $model, $config, $environment);
  }

  /**
   * @inheritDoc
   */
  public function getDriverCompat() : string
  {
    return 'bare';
  }

  /**
   * @inheritDoc
   */
  public function getPluginCompat() : array
  {
    return array('bare');
  }

  /**
   * @inheritDoc
   */
  public function getPlugins() : array
  {
    return array(
      // no plugins!
    );
  }

}