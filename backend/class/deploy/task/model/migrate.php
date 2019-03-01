<?php
namespace codename\architect\deploy\task\model;

use codename\core\exception;

class migrate extends \codename\architect\deploy\task\model {

  /**
   * @inheritDoc
   */
  public function handleConfig()
  {
    parent::handleConfig();
    $this->filters = $this->config->get('filter') ?? null;
    $this->filtercollections = $this->config->get('filtercollection') ?? null;
    $this->targetModel = $this->config->get('target>model');
    $this->targetSchema = $this->config->get('target>schema');
    $this->map = $this->config->get('map');
    $this->updateForeign = $this->config->get('update_foreign');
  }

  /**
   * target model name
   * @var string
   */
  protected $targetModel = null;

  /**
   * target schema
   * @var string
   */
  protected $targetSchema = null;

  /**
   * list of $sourceModelField => $targetModelField maps
   * @var array
   */
  protected $map = [];

  /**
   * foreign key update config in source model
   * list of foreign key names
   * @var array
   */
  protected $updateForeign = null;

  /**
   * @inheritDoc
   */
  public function run() : \codename\architect\deploy\taskresult
  {
    $sourceModel = $this->getModelInstance($this->schema, $this->model);
    $targetModel = $this->getModelInstance($this->targetSchema, $this->targetModel);


    // hide all fields not necessary.
    $sourceModel->hideAllFields();

    // add pkey anyways
    $sourceModel->addField($sourceModel->getPrimarykey());

    foreach($this->map as $sourceModelField => $targetModelField) {
      $sourceModel->addField($sourceModelField);
    }

    $backMap = [];

    // prepare foreign key maps
    if($this->updateForeign) {
      foreach($this->updateForeign as $foreignKey) {
        $foreignKeyConfig = $sourceModel->getConfig()->get('foreign>'.$foreignKey);
        if(!$foreignKeyConfig) {
          throw new exception('EXCEPTION_TASK_MODEL_MIGRATE_FOREIGNKEY_INVALID', exception::$ERRORLEVEL_ERROR, $foreignKey);
        }
        if(($foreignKeyConfig['schema'] != $this->targetSchema) || ($foreignKeyConfig['model'] != $this->targetModel)) {
          throw new exception('EXCEPTION_TASK_MODEL_MIGRATE_INVALID_BACKREFERENCE', exception::$ERRORLEVEL_ERROR, [
            'foreign_config' => $foreignKeyConfig,
            'target_schema' => $this->targetSchema,
            'target_model' => $this->targetModel
          ]);
        }
        $backMap[$foreignKey] = $foreignKeyConfig['key'];
      }
    }

    if(count($backMap) === 0) {
      $backMap = null;
    }


    //
    // Apply filters
    //
    $filtersApplied = false;
    if($this->filters) {
      $filtersApplied = true;
      foreach($this->filters as $filter) {
        $filterValue = $filter['value'];
        if($filter['eval'] ?? false) {
          if($filter['value']['function'] ?? false) {
            if(is_callable($filter['value']['function'])) {
              $filterValue = call_user_func($filter['value']['function']); // TODO: parameters?
            } else {
              throw new exception('EXCEPTION_TASK_MODEL_FILTER_VALUE_EVAL_INVALID', exception::$ERRORLEVEL_ERROR, $filter['value']['function']);
            }
          } else {
            throw new exception('EXCEPTION_TASK_MODEL_FILTER_VALUE_FUNCTION_NOT_SET', exception::$ERRORLEVEL_ERROR, $filter['value']);
          }
        }
        $sourceModel->addDefaultfilter($filter['field'], $filterValue, $filter['operator'], $filter['conjunction'] ?? null);
      }
    }
    if($this->filtercollections) {
      $filtersApplied = true;
      foreach($this->filtercollections as $filtercollection) {
        $sourceModel->addDefaultFilterCollection($filtercollection['filters'], $filtercollection['group_operator'] ?? 'AND', $filtercollection['group_name'] ?? 'default', $filtercollection['conjunction'] ?? 'AND');
      }
    }

    $transaction = new \codename\core\transaction('migrate', [ $sourceModel, $targetModel ]);

    $runBatch = true;
    $migratedCount = 0;

    while($runBatch === true) {

      $start = microtime(true);
      if($this->config->get('batch_size')) {
        echo("Batch Size: " . ($this->config->get('batch_size')) . ''.chr(10));
        $sourceModel->setLimit(intval($this->config->get('batch_size')));
      }
      $result = $sourceModel->search()->getResult();
      $end = microtime(true);

      echo("Query completed in " . ($end-$start) . ' ms'.chr(10));
      echo("Migrating...".chr(10));

      if(count($result) === 0) {
        echo("No more migration candidates, breaking".chr(10));
        $runBatch = false;
        break;
      }

      $transaction->start();

      foreach($result as $sourceDataset) {
        $targetDataset = [];
        foreach($this->map as $sourceModelField => $targetModelField) {
          $targetDataset[$targetModelField] = $sourceDataset[$sourceModelField];
        }

        $targetModel->save($targetDataset);
        $lastInsertId = $targetModel->lastInsertId();

        if($backMap) {
          $updateSourceDataset = [
            $sourceModel->getPrimarykey() => $sourceDataset[$sourceModel->getPrimarykey()]
          ];

          foreach($backMap as $sourceModelField => $targetModelField) {
            if($targetModelField === $targetModel->getPrimarykey()) {
              $updateSourceDataset[$sourceModelField] = $lastInsertId;
            } else {
              $updateSourceDataset[$sourceModelField] = $targetDataset[$targetModelField];
            }
          }

          $sourceModel->save($updateSourceDataset);
        }

        // echo("Migrated [{$sourceDataset[$sourceModel->getPrimaryKey()]} => {$lastInsertId}]".chr(10));
      }

      $migratedCount += count($result);

      $transaction->end();
    }

    return new \codename\architect\deploy\taskresult\text([
      'text' => "migrated count: ".$migratedCount
    ]);
  }

}
