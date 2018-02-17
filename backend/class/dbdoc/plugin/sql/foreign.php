<?php
namespace codename\architect\dbdoc\plugin\sql;
use codename\architect\dbdoc\task;
use codename\core\exception;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class foreign extends \codename\architect\dbdoc\plugin\foreign {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;

    $db->query(
      "SELECT tc.table_schema, tc.table_name, constraint_name, column_name, referenced_table_schema, referenced_table_name, referenced_column_name
        FROM information_schema.table_constraints tc
        INNER JOIN information_schema.key_column_usage kcu
        USING (constraint_catalog, constraint_schema, constraint_name)
        WHERE constraint_type = 'FOREIGN KEY'
        AND tc.table_schema = '{$this->adapter->schema}'
        AND tc.table_name = '{$this->adapter->model}';");

    $constraints = $db->getResult();

    return $constraints;
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();

    $definition = $this->getDefinition();

    // virtual = assume empty structure
    $structure = $this->virtual ? array() : $this->getStructure();

    $valid = array();
    $missing = array();
    $toomuch = array();

    foreach($structure as $struc) {

      // invalid or simply too much
      if(array_key_exists($struc['column_name'], $definition)) {
        // struc-def match, check values
        $foreignConfig = $definition[$struc['column_name']];

        if($foreignConfig['schema'] != $struc['referenced_table_schema']
          || $foreignConfig['model'] != $struc['referenced_table_name']
          || $foreignConfig['key'] != $struc['referenced_column_name']
        ) {
          $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_FOREIGNKEY_CONSTRAINT", array(
            'constraint_name' => $struc['constraint_name'],
            'field' => $struc['column_name'],
            'config' => $foreignConfig
          ));
        } else {
          $valid[$struc['column_name']] = $foreignConfig;
        }

      } else {
        $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "REMOVE_FOREIGNKEY_CONSTRAINT", array(
          'constraint_name' => $struc['constraint_name']
        ));
      }
    }

    $missing = array_diff_key($definition, $valid);

    foreach($missing as $field => $def) {

      $precededBy = array();

      // let the task be preceded by tasks related to the existance of the foreign field
      $foreignAdapter = $this->adapter->dbdoc->getAdapter($def['schema'], $def['model'], $def['app'] ?? '', $def['vendor'] ?? '');

      // omit multi-component foreignkeys
      if(isset($def['optional']) && $def['optional']) {
        continue;
      }

      $foreignFields = is_array($def['key']) ? array_values($def['key']) : [$def['key']];
      $nullPluginDetected = false;
      foreach($foreignFields as $key) {
        $plugin = $foreignAdapter->getPluginInstance('field', array('field' => $key));
        if($plugin != null) {
          $precededBy[] = $plugin->getTaskIdentifierPrefix();
        } else {
          // cancel here, as we might reference a model that can't be constructed
          // in this case, the field plugin is null
          $nullPluginDetected = true;
        }
      }
      if($nullPluginDetected) {
        continue;
      }


      // the foreign table
      $plugin = $foreignAdapter->getPluginInstance('table');
      if($plugin != null) {
        $precededBy[] = $plugin->getTaskIdentifierPrefix();
      }

      // let the task be preceded by tasks related to the existance the field itself
      $plugin = $this->adapter->getPluginInstance('field', array('field' => $field));
      if($plugin != null) {
        $precededBy[] = $plugin->getTaskIdentifierPrefix();
      }

      // the current table
      $plugin = $this->adapter->getPluginInstance('table');
      if($plugin != null) {
        $precededBy[] = $plugin->getTaskIdentifierPrefix();
      }

      // echo("<pre>{$field} preceded by: \n" . print_r($def,true) . "</pre>");
      // echo("<pre>foreign plugin, preceded by: \n" . print_r($definition,true) . "</pre>");

      //echo("<pre>{$field} preceded by: \n" . print_r($precededBy,true) . "</pre>");

      $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "ADD_FOREIGNKEY_CONSTRAINT",
        array(
          'field' => $field,
          'config' => $def,
        ),
        $precededBy
      );
    }

    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;
    if($task->name == "ADD_FOREIGNKEY_CONSTRAINT") {
      /*
      "ALTER TABLE $schema.$table
       ADD CONSTRAINT ".$table."_".$ref_table."_".$column."_fkey
       FOREIGN KEY ($column)
       REFERENCES $ref_schema.$ref_table ($ref_column);"
      */

      $field = $task->data->get('field');
      $config = $task->data->get('config');

      $constraintName = "fkey_" . md5("{$this->adapter->model}_{$config['model']}_{$field}_fkey");

      if(is_array($config['key'])) {
        $fkey = implode(',', array_keys($config['key']));
        $references = implode(',', array_values($config['key']));
      } else {
        $fkey = $field;
        $references = $config['key'];
      }

      $db->query(
       "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model}
        ADD CONSTRAINT {$constraintName}
        FOREIGN KEY ({$fkey})
        REFERENCES {$config['schema']}.{$config['model']} ({$references});"
      );
    }

    // TODO: Remove / modify foreign key
    // may be abstracted to two tasks, first: delete/drop, then (re)create
  }

}