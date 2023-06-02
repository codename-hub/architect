<?php

namespace codename\architect\dbdoc\plugin;

use codename\architect\app;
use codename\core\exception;

/**
 * plugin for providing and comparing user settings for the database
 * @package architect
 */
abstract class user extends connectionPrefix
{
    /**
     * {@inheritDoc}
     * @return array
     * @throws exception
     */
    public function getDefinition(): array
    {
        // get some database definitions for the real-world (non-architect) environment
        // especially: username, password - for DB access!

        // get database specifier from model (connection)
        $connection = $this->adapter->config->get('connection') ?? 'default';
        $globalEnv = app::getEnv();
        $environment = $this->adapter->environment;

        // backup env key
        $prevEnv = $environment->getEnvironmentKey();

        // change env key
        $environment->setEnvironmentKey($globalEnv);

        // get database name
        $config = $environment->get('database>' . $connection);

        // username defined in config or as an ENV-key
        $user = isset($config['env_user']) ? getenv($config['env_user']) : $config['user'];

        // password defined as text or as ENV-key
        // should throw an exception if neither is defined
        $pass = isset($config['env_pass']) ? getenv($config['env_pass']) : $config['pass'];

        // revert env key
        $environment->setEnvironmentKey($prevEnv);

        if (empty($user) || empty($pass)) {
            throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_USER_INVALID_CONFIGURATION", exception::$ERRORLEVEL_FATAL, $config);
        }

        return [
          'user' => $user,
          'pass' => $pass,
        ];
    }
}
