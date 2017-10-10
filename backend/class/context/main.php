<?php
namespace codename\architect\context;
use \codename\architect\app;

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
    if($this->getRequest()->getData('filter>vendor') != null&& $this->getRequest()->getData('filter>app') != null) {
      $app = $this->getRequest()->getData('filter>app');
      $vendor = $this->getRequest()->getData('filter>vendor');

      // TODO: check for validity! Compare to getSiblingApps return value!

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
        $dbdoc_ma->runDiagnostics();
      }

      $this->getResponse()->setData('models', $modelList);
    } else {
      echo("something undefined:");
      print_r($this->getRequest()->getData());
    }
  }

}