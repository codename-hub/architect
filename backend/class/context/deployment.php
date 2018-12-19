<?php
namespace codename\architect\context;

/**
 * deployment context
 */
class deployment extends \codename\core\context {

  /**
   * list available app configs for deployment
   * @return void
   */
  public function view_apps () {

  }

  /**
   * view available tasks from a given deployment config
   * @return void
   */
  public function view_tasks () {

  }

  /**
   * run a given deployment configuration
   * @return void
   */
  public function view_run() {
    $vendor = $this->getRequest()->getData('vendor');
    $app = $this->getRequest()->getData('app');
    $deploy = $this->getRequest()->getData('deploy');

    $instance = \codename\architect\deploy\deployment::createFromConfig($vendor, $app, $deploy);

    $result = $instance->run();

    $this->getResponse()->setData('deploymentresult', $result);
  }

  /**
   * [view_default description]
   * @return void
   */
  public function view_default() {
  }

}
