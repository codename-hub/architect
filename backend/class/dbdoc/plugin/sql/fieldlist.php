<?php
namespace codename\architect\dbdoc\plugin\sql;
use \codename\architect\dbdoc\plugin;

/**
 * plugin for providing and comparing model field data
 * especially count and array of fields / columns (not their datatypes and constraints!)
 * @package architect
 */
class fieldlist extends plugin\fieldlist {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    return $this->adapter->config->get('field');
  }

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query("SELECT column_name
      FROM information_schema.columns
      WHERE table_name = '{$this->adapter->model}'
      AND table_schema = '{$this->adapter->schema}'
    ;");
    $res = $db->getResult();

    $columns = array();
    foreach($res as $r) {
      $columns[] = $r['column_name'];
    }

    return $columns;
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    // fields contained in model, that are not in the database table
    $missing = array_diff($definition, $structure);

    // columns in the database table, that are simply "too much" (not in the model definition)
    $toomuch = array_diff($structure, $definition);

    // TODO: handle toomuch
    // e.g. check for prefix __old_
    // of not, create task to rename column
    // otherwise, recommend harddeletion ?

    foreach($definition as $field) {
      $plugin = $this->adapter->getPluginInstance(
        'field',
        array(
          'field' => $field
        ),
        $this->virtual // virtual on need.
      );

      if($plugin != null) {
        // add this plugin to the first
        $this->adapter->addToQueue($plugin, true);
      }
    }

    // do something with it.
    if(count($missing) == 0) {

    } else {

    }

    return array();
  }

}