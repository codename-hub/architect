<?php
namespace codename\architect\deploy\task\client;

/**
 * base class for doing cache-specific tasks
 */
abstract class cache extends \codename\architect\deploy\task\client {

  /**
   * cache identifier
   * @var string
   */
  protected $cacheIdentifier;

  /**
   * @inheritDoc
   */
  protected function handleConfig()
  {
    parent::handleConfig();
    $this->cacheIdentifier = $this->config->get('identifier');
  }

  /**
   * returns the cache instance
   * @return \codename\core\cache [description]
   */
  protected function getCache() : \codename\core\cache {
    return $this->getClientInstance($this->cacheIdentifier);
  }

  /**
   * @inheritDoc
   */
  protected function getClientObjectTypeName(): string
  {
    return 'cache';
  }

}
