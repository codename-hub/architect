<?php
namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\task;



/**
 * plugin for providing and comparing collection field config in a model
 * @package architect
 */
class collection extends \codename\architect\dbdoc\plugin\collection {

  /**
   * @inheritDoc
   */
  public function getStructure() : array
  {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();

    $collectionDefinitions = $this->getDefinition();

    $precededBy = [];

    // 
    // TODO: Check, if the given collection config is correct
    //

    // foreach($collectionDefinitions as $def) {
    //
    //   // $foreignAdapter = $this->adapter->dbdoc->getAdapter($def['schema'], $def['model'], $def['app'] ?? '', $def['vendor'] ?? '');
    //   // $plugin = $foreignAdapter->getPluginInstance('table', [], $this->virtual /*, array('field' => $def['key'])*/ );
    //   // if($plugin != null) {
    //   //   $precededBy[] = $plugin->getTaskIdentifierPrefix();
    //   // }
    //
    //   $aux = $def['aux'];
    //   $auxAdapter = $this->adapter->dbdoc->getAdapter($aux['schema'], $aux['model'], $aux['app'] ?? '', $aux['vendor'] ?? '');
    //   $plugin = $auxAdapter->getPluginInstance('table', [], $this->virtual /*, array('field' => $aux['key'])*/);
    //   if($plugin != null) {
    //     $precededBy[] = $plugin->getTaskIdentifierPrefix();
    //   } else {
    //     echo("table plugin is null");
    //   }
    //
    //   print_r($precededBy);
    //
    //   $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "DUMMY_COLLECTION_TASK_RUN",
    //     array(
    //       // 'field' => $field,
    //       'config' => $def,
    //     ),
    //     $precededBy
    //   );
    // }

    return $tasks;
  }

}
