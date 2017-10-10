<?php
namespace codename\architect\config;
use codename\core\catchableException;

/**
 * virtualize a specific environment
 */
class environment extends \codename\core\config {

  /**
   * env key
   * @var string
   */
  protected $environmentKey = null;

  /**
   * @inheritDoc
   */
  public function __construct(array $data, string $environmentKey = null)
  {
    parent::__construct($data);
    $this->environmentKey = $environmentKey;
  }

  /**
   * @inheritDoc
   */
  public function get(string $key = '', $default = null)
  {
    if($key == '') {
      $key = $this->environmentKey;
    } else {
      $key = "{$this->environmentKey}>{$key}";
    }
    return parent::get($key, $default);
  }

  /**
   * sets the environment key to be used
   * @param string $key [description]
   */
  public function setEnvironmentKey(string $key) {
    $this->environmentKey = $key;
  }

  /**
   * gets the environment key currently used
   * @return string      [description]
   */
  public function getEnvironmentKey() : string {
    return $this->environmentKey;
  }

}