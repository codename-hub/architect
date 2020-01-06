<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use \codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data
 * especially count and array of fields / columns (not their datatypes and constraints!)
 * @package architect
 */
class fieldlist extends plugin\sql\fieldlist {

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    // $db->query("SELECT column_name
    //   FROM information_schema.columns
    //   WHERE table_name = '{$this->adapter->model}'
    //   AND table_schema = '{$this->adapter->schema}'
    // ;");
    $db->query("PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');");

    $res = $db->getResult();

    // echo("<pre>");print_r($res);echo("</pre>");

    $columns = array();
    foreach($res as $r) {
      $columns[] = $r['name'];
    }

    return $columns;
  }

}
