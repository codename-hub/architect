<?php

namespace codename\architect\deploy\task\client;

use codename\architect\deploy\task\client;
use codename\core\exception;
use ReflectionException;

/**
 * base class for doing cache-specific tasks
 */
abstract class cache extends client
{
    /**
     * cache identifier
     * @var string
     */
    protected string $cacheIdentifier;

    /**
     * {@inheritDoc}
     */
    protected function handleConfig(): void
    {
        parent::handleConfig();
        $this->cacheIdentifier = $this->config->get('identifier');
    }

    /**
     * returns the cache instance
     * @return \codename\core\cache [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function getCache(): \codename\core\cache
    {
        $object = $this->getClientInstance($this->cacheIdentifier);
        if ($object instanceof \codename\core\cache) {
            return $object;
        }
        throw new exception('EXCEPTION_GETCACHE_WRONG_OBJECT', exception::$ERRORLEVEL_FATAL);
    }

    /**
     * {@inheritDoc}
     */
    protected function getClientObjectTypeName(): string
    {
        return 'cache';
    }
}
