<?php
namespace codename\architect\deploy\task;

use codename\architect\app;

use codename\architect\deploy\taskresult;

/**
 * base class for doing client (abstract)-specific tasks
 */
abstract class client extends \codename\architect\deploy\task {

  /**
   * returns a name of an object type for getting the client instance
   * @return string
   */
  protected abstract function getClientObjectTypeName() : string;

  /**
   * [getClientInstance description]
   * @param  string $clientName [description]
   * @return object
   */
  protected function getClientInstance(string $clientName) {
    $dbValueObjecttype = new \codename\core\value\text\objecttype($this->getClientObjectTypeName());
    $dbValueObjectidentifier = new \codename\core\value\text\objectidentifier($clientName);
    return app::getForeignClient(
        $this->getDeploymentInstance()->getVirtualEnvironment(),
        $dbValueObjecttype,
        $dbValueObjectidentifier,
        false
    );
  }

}
