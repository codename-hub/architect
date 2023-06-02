<?php

namespace codename\architect\config\json;

use codename\core\app;
use codename\core\config\json;
use codename\core\exception;

/**
 * json config reader using a virtual or custom appstack
 */
class virtualAppstack extends json
{
    /**
     * custom appstack for inheritance overriding
     * @var null|array
     */
    protected ?array $useAppstack = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        string $file,
        bool $appstack = false,
        bool $inherit = false,
        array $useAppstack = null
    ) {
        $this->useAppstack = $useAppstack;
        return parent::__construct($file, $appstack, $inherit, $useAppstack);
    }

    /**
     * {@inheritDoc}
     */
    protected function getFullpath(string $file, bool $appstack): string
    {
        $fullpath = app::getHomedir() . $file;

        if (app::getInstance('filesystem_local')->fileAvailable($fullpath)) {
            return $fullpath;
        }

        if (!$appstack) {
            throw new exception(self::EXCEPTION_GETFULLPATH_FILEMISSING, exception::$ERRORLEVEL_FATAL, ['file' => $fullpath, 'info' => 'use appstack?']);
        }

        return app::getInheritedPath($file, $this->useAppstack);
    }
}
