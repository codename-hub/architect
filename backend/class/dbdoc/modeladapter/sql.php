<?php
namespace codename\architect\dbdoc\modeladapter;
use \codename\architect\app;

trait modeladapterGetSqlAdapter {
  /**
   * [getSqlAdapter description]
   * @return \codename\architect\dbdoc\modeladapter\sql [description]
   */
  protected function getSqlAdapter() : \codename\architect\dbdoc\modeladapter\sql {
    return $this->adapter;
  }
}

/**
 * sql ddl adapter
 * @package architect
 */
abstract class sql extends \codename\architect\dbdoc\modeladapter {

  /**
   * Contains the database connection
   * @var \codename\core\database
   */
  public $db = null;

  /**
   * @inheritDoc
   */
  public function __construct(string $schema, string $model, \codename\core\config $config, \codename\architect\config\environment $environment)
  {
    parent::__construct($schema, $model, $config, $environment);

    // establish database connection
    // we require a special environment configuration
    // in the environment
    $this->db = $this->getDatabaseConnection($this->config->get('connection'));
  }

  /**
   * @inheritDoc
   */
  public function getPlugins() : array
  {
    return array(
      // 'connection',
      'schema',
      'table',
      'fieldlist'
    );
  }

  /**
   * [loadDatabaseConnection description]
   * @param  string $identifier [description]
   * @return [type]             [description]
   */
  protected function getDatabaseConnection(string $identifier = 'default') : \codename\core\database {
    $dbValueObjecttype = new \codename\core\value\text\objecttype('database');
    $dbValueObjectidentifier = new \codename\core\value\text\objectidentifier($identifier);
    return app::getForeignClient($this->environment, $dbValueObjecttype, $dbValueObjectidentifier);
  }

}