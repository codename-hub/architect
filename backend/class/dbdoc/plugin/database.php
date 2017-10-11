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
    // get database specifier from model (connection)
    $connection = $this->adapter->config->get('connection') ?? 'default';
    $globalEnv = \codename\architect\app::getEnv();
    $environment = $this->adapter->environment;

    // backup env key
    $prevEnv = $environment->getEnvironmentKey();

    // change env key
    $environment->setEnvironmentKey($globalEnv);

    // get database name
    $databaseName = $environment->get('database>'.$connection.'>database');

    // revert env key
    $environment->setEnvironmentKey($prevEnv);

    return $databaseName;
  }

}