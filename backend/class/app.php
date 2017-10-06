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
  public function run()
  {
    $value = parent::run();

    // $this->printNamespaces();

    $apps = $this->getSiblingApps();
    foreach($apps as $a) {

      // build specific appstack
      $foreignAppstack = app::makeAppstack($a['vendor'], $a['app']);

      // debug
      print_r($foreignAppstack);

      // get all models from the respective app and traverse its ancestors, too.
      $foreignModels = app::getAllModels($a['app'], $a['vendor'], $foreignAppstack);

      // even more debug:
      echo("<br>{$a['vendor']}\\{$a['app']}:");

      foreach($foreignModels as $m) {

        // debug:
        echo("<br>-- model: {$m}");

        // initialize each model?
      }
    }

    return $value;
  }




  /**
   * returns an array of sibling app names
   * if they depend on the core framework
   * @return array [description]
   */
  public function getSiblingApps() : array {

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
