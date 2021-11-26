<?php
namespace codename\architect;

/**
 * app class for a the architect app
 * @package architect
 * @author Kevin Dargel
 * @since 2017-10-05
 */
class app extends \codename\core\app {

  /**
   * @inheritDoc
   */
  public function __CONSTRUCT()
  {
    parent::__CONSTRUCT();
    $this->initDebug();
  }

  /**
   * @inheritDoc
   */
  protected function makeRequest()
  {
    parent::makeRequest();
    if($this->getResponse() instanceof \codename\core\response\cli) {
      $this->getResponse()->setData('templateengine', 'cli');
    }
  }

  /**
   * [makeForeignAppstack description]
   * @param  string $vendor [description]
   * @param  string $app    [description]
   * @return array          [description]
   */
  public static function makeForeignAppstack(string $vendor, string $app) : array {
    return parent::makeAppstack($vendor, $app);
  }

  /**
   * Gets the all models/definitions, also inherited
   * returns a multidimensional assoc array like:
   * models[schema][model] = array( 'fields' => ... )
   * @author Kevin Dargel
   * @return array
   */
  public static function getModelConfigurations(string $filterByVendor = '', string $filterByApp = '', string $model = '', array $useAppstack = null) : array {

    $result = array();

    if($useAppstack == null) {
      $useAppstack = self::getAppstack();
    }

    // Traverse Appstack
    foreach($useAppstack as $app) {

      if($filterByApp !== '') {
        if($filterByApp !== $app['app']) {
          continue;
        }
      }

      if($filterByVendor !== '') {
        if($filterByVendor !== $app['vendor']) {
          continue;
        }
      }

      // array of vendor,app
      $appdir = app::getHomedir($app['vendor'], $app['app']);
      $dir = $appdir . "config/model";

      // get all model json files, first:
      $files = app::getFilesystem()->dirList( $dir );

      foreach($files as $f) {
        $file = $dir . '/' . $f;

        // check for .json extension
        $fileInfo = new \SplFileInfo($file);
        if($fileInfo->getExtension() === 'json') {
          // get the model filename w/o extension
          $modelName = $fileInfo->getBasename('.json');

          // split: schema_model
          // maximum: two components (schema, model)
          // following _ are treated as part of the model name itself
          $comp = explode( '_' , $modelName, 2);
          $schema = $comp[0];
          $model = $comp[1];

          $modelconfig = (new \codename\architect\config\json\virtualAppstack("config/model/" . $fileInfo->getFilename(), true, true, $useAppstack))->get();
          $result[$schema][$model][] = $modelconfig;
        }
      }
    }

    return $result;
  }


  /**
   * returns an array of sibling app names
   * if they depend on the core framework
   * @return array [description]
   */
  public static function getSiblingApps() : array {

    $vendorDirs = app::getFilesystem()->dirList(CORE_VENDORDIR);
    $appPaths = [];

    foreach($vendorDirs as $vendorDir) {
      // for now, we're relying on our current vendor name for finding siblings
      $paths = app::getFilesystem()->dirList(CORE_VENDORDIR . $vendorDir);
      foreach($paths as $p) {
        if(app::getFilesystem()->isDirectory(CORE_VENDORDIR . $vendorDir . '/' . $p)) {
          $appPaths[] = [ $vendorDir, $p ];
        }
      }
    }

    // The base app class, reflected.
    $baseReflectionClass = new \ReflectionClass( '\\codename\\core\\app' );

    $apps = array();

    foreach($appPaths as $pathComponents) {

      $vendordir = $pathComponents[0];
      $appdir = $pathComponents[1];

      if(app::getFilesystem()->isDirectory($dir = CORE_VENDORDIR . $vendordir . '/' . $appdir)) {

        // exclude this app and the core framework.
        if($appdir != 'architect' && $appdir != 'core') {

          $appname = null;
          $vendorname = null;
          $probeNamespace = null;

          // analyze composer.json, if available
          if(file_exists($composerJson = $dir . '/composer.json')) {
            $composerData = @json_decode(file_get_contents($composerJson), true);
            $probeNamespace = array_keys($composerData['autoload']['psr-4'] ?? [])[0] ?? $probeNamespace;

            // assume vendor/project (composer-style)
            // to define the core-app identifier (vendor and app)
            if($names = explode('/', $composerData['name'] ?? '')) {
              $vendorname = $names[0];
              $appname = $names[1];
            }
          } else {
            // not a composer-loadable directory
            continue;
          }

          if($probeNamespace) {
            // try to look for app class
            $classname = $probeNamespace . 'app';
          } else {
            $classname = $vendordir . '\\' . $appdir . '\\app';
          }

          if(class_exists($classname)) {

            // testing for inheritance from $baseReflectionClass
            // @see https://stackoverflow.com/questions/782653/checking-if-a-class-is-a-subclass-of-another
            $testReflectionClass = new \ReflectionClass($classname);
            if($testReflectionClass->isSubclassOf($baseReflectionClass)) {
              // compatible sibling app found.
              $apps[] = array(
                'vendor'  => $vendorname ?? $vendordir,
                'app'     => $appname ?? $appdir,
                'homedir' => $vendordir . '/' . $appdir
              );
            }
          }
        }
      }
    }

    return $apps;
  }



  /**
   * Returns the (maybe cached) client that is stored as "driver" in $identifier (app.json) for the given $type.
   * @param string $type
   * @param string $identifier
   * @return object
   * @todo refactor
   */
  final public static function getForeignClient(\codename\architect\config\environment $environment, \codename\core\value\text\objecttype $type, \codename\core\value\text\objectidentifier $identifier, bool $store = true) {

      // $config = self::getData($type, $identifier);
      $config = $environment->get("{$type->get()}>{$identifier->get()}");

      $type = $type->get();
      $identifier = $identifier->get();
      $simplename = $type . $identifier;

      if ($store && array_key_exists($simplename, $_REQUEST['instances'])) {
          return $_REQUEST['instances'][$simplename];
      }


      $app = array_key_exists('app', $config) ? $config['app'] : self::getApp();
      $vendor = self::getVendor();

      if(is_array($config['driver'])) {
          $config['driver'] = $config['driver'][0];
      }

      // we have to traverse the appstack!
      $classpath = self::getHomedir($vendor, $app) . '/backend/class/' . $type . '/' . $config['driver'] . '.php';
      $classname = "\\{$vendor}\\{$app}\\{$type}\\" . $config['driver'];


      // if not found in app, traverse appstack
      if(!self::getInstance('filesystem_local')->fileAvailable($classpath)) {
        $found = false;
        foreach(self::getAppstack() as $parentapp) {
          $vendor = $parentapp['vendor'];
          $app = $parentapp['app'];
          $classpath = self::getHomedir($vendor, $app) . '/backend/class/' . $type . '/' . $config['driver'] . '.php';
          $classname = "\\{$vendor}\\{$app}\\{$type}\\" . $config['driver'];

          if(self::getInstance('filesystem_local')->fileAvailable($classpath)) {
            $found = true;
            break;
          }
        }

        if($found !== true) {
          throw new \codename\core\exception(self::EXCEPTION_GETCLIENT_NOTFOUND, \codename\core\exception::$ERRORLEVEL_FATAL, array($type, $identifier));
        }
      }

      // instanciate
      return $_REQUEST['instances'][$simplename] = new $classname($config);
  }

  /**
   * [printNamespaces description]
   * only needed for debug purposes.
   * may be removed in the future
   * @return [type] [description]
   */
  protected function printNamespaces() {
    $namespaces=array();
    foreach(get_declared_classes() as $name) {
        if(preg_match_all("@[^\\\]+(?=\\\)@iU", $name, $matches)) {
            $matches = $matches[0];
            $parent =&$namespaces;
            while(count($matches)) {
                $match = array_shift($matches);
                if(!isset($parent[$match]) && count($matches))
                    $parent[$match] = array();
                $parent =&$parent[$match];
            }
        }
    }

    echo("<pre>");
    print_r($namespaces);
    echo("</pre>");
  }

}
