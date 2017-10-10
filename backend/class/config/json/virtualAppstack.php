<?php
namespace codename\architect\config\json;

/**
 * json config reader using a virtual or custom appstack
 */
class virtualAppstack extends \codename\core\config\json {

  /**
   * custom appstack for inheritance overriding
   * @var array
   */
  protected $useAppstack = null;

  /**
   * @inheritDoc
   */
  public function __CONSTRUCT(
    string $file,
    bool $appstack = false,
    bool $inherit = false,
    array $useAppstack = null
  ) {
    $this->useAppstack = $useAppstack;
    $value = parent::__CONSTRUCT($file, $appstack, $inherit, $useAppstack);
    return $value;
  }

  /**
   * @inheritDoc
   */
  protected function getFullpath(string $file, bool $appstack) : string
  {
    $fullpath = app::getHomedir() . $file;

    if(app::getInstance('filesystem_local')->fileAvailable($fullpath)) {
        return $fullpath;
    }

    if(!$appstack) {
        throw new \codename\core\exception(self::EXCEPTION_GETFULLPATH_FILEMISSING, \codename\core\exception::$ERRORLEVEL_FATAL, array('file' => $fullpath, 'info' => 'use appstack?'));
    }

    return app::getInheritedPath($file, $this->useAppstack);
  }
}