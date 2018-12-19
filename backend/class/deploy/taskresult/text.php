<?php
namespace codename\architect\deploy\taskresult;

/**
 * deployment task result object
 */
class text extends \codename\architect\deploy\taskresult {

  /**
   * @inheritDoc
   */
  public function formatAsString(): string
  {
    return $this->get('text');
  }
  
}
