<?php

namespace codename\architect\config;

use codename\core\config;

/**
 * virtualize a specific environment
 */
class environment extends config
{
    /**
     * env key
     * @var null|string
     */
    protected ?string $environmentKey = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $data, string $environmentKey = null)
    {
        parent::__construct($data);
        $this->environmentKey = $environmentKey;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key = '', mixed $default = null): mixed
    {
        if ($key == '') {
            $key = $this->environmentKey;
        } else {
            $key = $this->environmentKey . '>' . $key;
        }
        return parent::get($key, $default);
    }

    /**
     * gets the environment key currently used
     * @return string      [description]
     */
    public function getEnvironmentKey(): string
    {
        return $this->environmentKey;
    }

    /**
     * sets the environment key to be used
     * @param string $key [description]
     */
    public function setEnvironmentKey(string $key): void
    {
        $this->environmentKey = $key;
    }
}
