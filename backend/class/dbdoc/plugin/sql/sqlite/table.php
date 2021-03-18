<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;
use \codename\architect\dbdoc\plugin;
use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model table data
 * @package architect
 */
class table extends plugin\sql\table {

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();
    $definition = $this->getDefinition();

    // if virtual, simulate nonexisting structure
    $structure = $this->virtual ? false : $this->getStructure();

    // structure doesn't exist
    if(!$structure) {
      // table does not exist
      // create table
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_TABLE", array(
        'table' => $definition
      ));
    } else {
      // Modify existing table
      // Needs to be done as full re-create

      // NOTE: Only for deleting obsolete fields...
      // $tasks = $this->getCheckStructure($tasks);

      // either run sub-plugins virtually or the 'hard' way

      $modificationTasks = [];
      $regularTasks = [];

      // execute plugin for indices
      $plugin = $this->adapter->getPluginInstance('index', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $regularTasks = array_merge($regularTasks, $compareTasks);
        }
      }

      // execute plugin for fulltext
      $plugin = $this->adapter->getPluginInstance('fulltext', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      // execute plugin for unique constraints
      $plugin = $this->adapter->getPluginInstance('unique', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      // collection key plugin
      $plugin = $this->adapter->getPluginInstance('collection', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      // foreign key plugin
      $plugin = $this->adapter->getPluginInstance('foreign', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      // fieldlist
      $plugin = $this->adapter->getPluginInstance('fieldlist', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      // pkey first
      $plugin = $this->adapter->getPluginInstance('primary', array(), $this->virtual);
      if($plugin != null) {
        if(count($compareTasks = $plugin->Compare()) > 0) {
          // change(s) detected
          $modificationTasks = array_merge($modificationTasks, $compareTasks);
        }
      }

      if(count($modificationTasks) > 0) {


        $recreateTableTask = $this->createTask(task::TASK_TYPE_INFO, "RECREATE_TABLE", array(
          'table' => $definition,
        ));

        $tasks[] = $recreateTableTask;

        $infoTasks = [];

        $minimumTaskType = null;

        foreach ($modificationTasks as $mTask) {
          if($mTask instanceof task) {

            if($mTask->type === task::TASK_TYPE_ERROR) {
              // error overrides all
              $minimumTaskType = $mTask->type;
            } else {
              // required is the next largest one
              if($minimumTaskType !== task::TASK_TYPE_REQUIRED && $mTask->type === task::TASK_TYPE_REQUIRED) {
                $minimumTaskType = $mTask->type;
              } else if($mTask->type === task::TASK_TYPE_SUGGESTED) {
                $minimumTaskType = $mTask->type;
              }
            }

            $infoTask = $this->createTask(task::TASK_TYPE_INFO, 'RELAYED_'.$mTask->name, $mTask->data->get());
            // $infoTask->precededBy = $recreateTableTask->identifier;

            $infoTasks[] = $infoTask;
          }
        }

        $recreateTableTask->type = $minimumTaskType ?? $recreateTableTask->type;

        $tasks = array_merge($tasks, $infoTasks);
      }

      // Merge-in regular tasks
      $tasks = array_merge($tasks, $regularTasks);

    }


    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    // $db->query(
    //     "SELECT exists(select 1 FROM information_schema.tables WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}') as result;"
    // );
    $db->query(
        "SELECT exists(select 1 FROM sqlite_master WHERE type = 'table' AND tbl_name = '{$this->adapter->schema}.{$this->adapter->model}') as result;"
    );
    return $db->getResult()[0]['result'];
  }

  /**
   * @inheritDoc
   */
  protected function getCheckStructure($tasks) {
    $db = $this->getSqlAdapter()->db;
    // $db->query(
    //     "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = '{$this->adapter->schema}' AND table_name = '{$this->adapter->model}';"
    // );
    // $db->query(
    //     "PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');"
    // );
    $db->query(
        "SELECT name as COLUMN_NAME FROM pragma_table_info('{$this->adapter->schema}.{$this->adapter->model}');"
    );

    ;
    $columns = $db->getResult();

    if ($columns ?? false) {
      $fields = $this->adapter->config->get()['field'] ?? [];

      foreach($columns as $column) {
        if (!in_array($column['COLUMN_NAME'], $fields)) {
          $tasks[] = $this->createTask(task::TASK_TYPE_OPTIONAL, "DELETE_COLUMN", array(
            'table' => $this->adapter->model,
            'field' => $column['COLUMN_NAME']
          ));
        }
      }
    }
    return $tasks;
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;

    // If recreating a table due to required changes
    // this is variable being set during the process
    $preliminaryTable = null;

    if($task->name == 'RECREATE_TABLE') {

      // for recreating a table, we
      // - create a preliminary table first (with the desired structure)
      // - then copy all existing data to it (via mutual fields)
      // - drop the existing table
      // - rename the preliminary table to the desired name
      // (to minimize downtime and keep the system from failing)
      //
      $preliminaryTable = "__prelim__{$this->adapter->schema}.{$this->adapter->model}";

      // TODO: we also might have multiple 'prelims'
      // as an earlier creation process might have failed
      // to improve redundancy and keep old data for debugging
      // we might use an increment value,
      // when an existing table is detected
      // e.g. __prelim_2_tablename

      // test for existing prelim/backup table
      $db->query("SELECT exists(select 1 FROM sqlite_master WHERE type = 'table' AND tbl_name = '{$preliminaryTable}') as result;");
      if($db->getResult()[0]['result']) {
        // Drop existing preliminary table
        $db->query("DROP TABLE `{$preliminaryTable}`");
      }
    }


    if($task->name == 'CREATE_TABLE' || $task->name == 'RECREATE_TABLE') {
      //
      // SQLite full create
      // Aggregate every field statement of this table
      // and execute all at once
      //
      $fieldStatements = [];
      $fieldlistPlugin = $this->adapter->getPluginInstance('fieldlist');
      if($fieldlistPlugin instanceof partialStatementInterface) {
        $fieldStatements = $fieldlistPlugin->getPartialStatement();
      }

      // foreign key statements
      $foreignPlugin = $this->adapter->getPluginInstance('foreign');
      if($foreignPlugin instanceof partialStatementInterface) {
        $foreignStatements = $foreignPlugin->getPartialStatement();
        $fieldStatements = array_merge($fieldStatements, $foreignStatements);
      }


      $fieldSql = implode(",\n", $fieldStatements);

      $tableSpecifier = $preliminaryTable ?? "{$this->adapter->schema}.{$this->adapter->model}";

      // DEBUG
      // print_r($fieldSql);

      $db->query(
        "CREATE TABLE `{$tableSpecifier}` (
          $fieldSql
        );"
      );

    }

    if($task->name == 'RECREATE_TABLE') {

      // copy data from existing to preliminary table
      if($preliminaryTable) {

        // determine mutual fields
        $db->query("PRAGMA table_info(`{$preliminaryTable}`)");
        $preliminaryTableFields = array_column($db->getResult(), 'name');
        $db->query("PRAGMA table_info(`{$this->adapter->schema}.{$this->adapter->model}`)");
        $existingTableFields = array_column($db->getResult(), 'name');
        $mutualFields = array_intersect($preliminaryTableFields, $existingTableFields);
        $mutualFieldsSql = implode(',', $mutualFields);

        // copy old data to new table
        $db->query("INSERT INTO
          `{$preliminaryTable}` ({$mutualFieldsSql})
          SELECT {$mutualFieldsSql} FROM `{$this->adapter->schema}.{$this->adapter->model}`
        ");

        // drop existing table
        $db->query("DROP TABLE `{$this->adapter->schema}.{$this->adapter->model}`");

        // rename preliminary table
        $db->query("ALTER TABLE `{$preliminaryTable}` RENAME TO `{$this->adapter->schema}.{$this->adapter->model}`");
      }
    }

    // if($task->name == 'DELETE_COLUMN') {
    //   $db->query(
    //     "ALTER TABLE {$this->adapter->schema}.{$this->adapter->model} DROP COLUMN IF EXISTS {$task->data->get('field')};"
    //   );
    // }
  }
}
