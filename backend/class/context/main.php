<?php
namespace codename\architect\context;
use \codename\architect\app;
use codename\core\catchableException;

/**
 * main context
 */
class main extends \codename\core\context {

  /**
   * view "default"
   */
  public function view_default() {
    $this->view_listapps();
    $this->getResponse()->setData('view', 'listapps');
  }

  public function view_listapps() {
    $apps = app::getSiblingApps();
    $this->getResponse()->setData('apps', $apps);
  }

  public function view_listmodels() {
    if($this->getRequest()->getData('filter>vendor') != null && $this->getRequest()->getData('filter>app') != null) {
      $app = $this->getRequest()->getData('filter>app');
      $vendor = $this->getRequest()->getData('filter>vendor');

      $dbdoc = new \codename\architect\dbdoc\dbdoc($app, $vendor);
      $dbdoc->run( $this->getRequest()->getData('exec') == '1' );

      // TODO: check for validity! Compare to getSiblingApps return value!
      /*
      $foreignAppstack = app::makeForeignAppstack($vendor, $app);

      $modelConfigurations = app::getModelConfigurations($vendor, $app, '', $foreignAppstack);

      $modelList = array();

      foreach($modelConfigurations as $schema => $models) {
        foreach($models as $modelname => $modelConfig) {
          $modelList[] = array(
            'identifier' => "{$schema}_{$modelname}",
            'model' => $modelname,
            'vendor' => $vendor,
            'app' => $app,
            'schema' => $schema,
            'driver' => 'dummy value',
            'config' => $modelConfig[0] // ??
          );
        }
      }

      // Load this file by default - plus inheritance
      // 'config/environment.json'
      $environment = (new \codename\core\config\json('config/environment.json', true, true, $foreignAppstack))->get();

      // NOTE:
      // We're using architect_ prefix by default!

      foreach($modelList as $m) {
        $dbdoc_ma = new \codename\architect\dbdoc\modeladapter\sql\mysql(
          $m['schema'],
          $m['model'],
          new \codename\core\config($m['config']),
          new \codename\architect\config\environment($environment, 'architect_' . app::getEnv())
        );

        $tasks = $dbdoc_ma->runDiagnostics();

        if($this->getRequest()->getData('exec') == '1') {
          foreach($tasks as $t) {
            echo("executing task ... ");
            $t->run();
          }
        }
      }
      */

      $this->getResponse()->setData('models', $dbdoc->models);
    } else {

      if($this->getRequest()->getData('filter>vendor') == null) {
        throw new catchableException("EXCEPTION_ARCHITECT_CONTEXT_MAIN_MISSING_FILTER_VENDOR", catchableException::$ERRORLEVEL_ERROR);
      }
      if($this->getRequest()->getData('filter>app') == null) {
        throw new catchableException("EXCEPTION_ARCHITECT_CONTEXT_MAIN_MISSING_FILTER_APP", catchableException::$ERRORLEVEL_ERROR);
      }

      // echo("something undefined:");
      // print_r($this->getRequest()->getData());
    }
  }

}