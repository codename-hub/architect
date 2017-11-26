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
  public function run()
  {
    $value = parent::run();
    return $value;
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
          $comp = explode( '_' , $modelName);
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

    // for now, we're relying on our current vendor name for finding siblings
    $appdirs = app::getFilesystem()->dirList(CORE_VENDORDIR . app::getVendor());

    // The base app class, reflected.
    $baseReflectionClass = new \ReflectionClass( app::getVendor() . '\\core\\app' );

    $apps = array();

    foreach($appdirs as $appdir) {
      if(app::getFilesystem()->isDirectory(CORE_VENDORDIR . app::getVendor() . '/' . $appdir)) {

        // exclude this app and the core framework.
        if($appdir != 'architect' && $appdir != 'core') {

          // try to look for app class
          $classname = app::getVendor() . '\\' . $appdir . '\\app';
          if(class_exists($classname)) {

            // testing for inheritance from $baseReflectionClass
            // @see https://stackoverflow.com/questions/782653/checking-if-a-class-is-a-subclass-of-another
            $testReflectionClass = new \ReflectionClass($classname);
            if($testReflectionClass->isSubclassOf($baseReflectionClass)) {
              // compatible sibling app found.
              $apps[] = array(
                'vendor' => app::getVendor(),
                'app' => $appdir
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
