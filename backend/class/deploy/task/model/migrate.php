<?php

namespace codename\architect\deploy\task\model;

use codename\architect\deploy\task\model;
use codename\architect\deploy\taskresult;
use codename\architect\deploy\taskresult\text;
use codename\core\exception;
use codename\core\transaction;
use ReflectionException;

class migrate extends model
{
    /**
     * target model name
     * @var null|string
     */
    protected ?string $targetModel = null;
    /**
     * target schema
     * @var null|string
     */
    protected ?string $targetSchema = null;
    /**
     * list of $sourceModelField => $targetModelField maps
     * @var array
     */
    protected array $map = [];
    /**
     * foreign key update config in source model
     * list of foreign key names
     * @var null|array
     */
    protected ?array $updateForeign = null;
    /**
     * @var array|mixed|null
     */
    private mixed $filters;
    /**
     * @var array|mixed|null
     */
    private mixed $filtercollections;

    /**
     * {@inheritDoc}
     */
    public function handleConfig(): void
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
     * {@inheritDoc}
     * @return taskresult
     * @throws ReflectionException
     * @throws exception
     */
    public function run(): taskresult
    {
        $sourceModel = $this->getModelInstance($this->schema, $this->model);
        $targetModel = $this->getModelInstance($this->targetSchema, $this->targetModel);


        // hide all fields not necessary.
        $sourceModel->hideAllFields();

        // add pkey anyway
        $sourceModel->addField($sourceModel->getPrimaryKey());

        foreach ($this->map as $sourceModelField => $targetModelField) {
            $sourceModel->addField($sourceModelField);
        }

        $backMap = [];

        // prepare foreign key maps
        if ($this->updateForeign) {
            foreach ($this->updateForeign as $foreignKey) {
                $foreignKeyConfig = $sourceModel->getConfig()->get('foreign>' . $foreignKey);
                if (!$foreignKeyConfig) {
                    throw new exception('EXCEPTION_TASK_MODEL_MIGRATE_FOREIGNKEY_INVALID', exception::$ERRORLEVEL_ERROR, $foreignKey);
                }
                if (($foreignKeyConfig['schema'] != $this->targetSchema) || ($foreignKeyConfig['model'] != $this->targetModel)) {
                    throw new exception('EXCEPTION_TASK_MODEL_MIGRATE_INVALID_BACKREFERENCE', exception::$ERRORLEVEL_ERROR, [
                      'foreign_config' => $foreignKeyConfig,
                      'target_schema' => $this->targetSchema,
                      'target_model' => $this->targetModel,
                    ]);
                }
                $backMap[$foreignKey] = $foreignKeyConfig['key'];
            }
        }

        if (count($backMap) === 0) {
            $backMap = null;
        }


        //
        // Apply filters
        //
        if ($this->filters) {
            foreach ($this->filters as $filter) {
                $filterValue = $filter['value'];
                if ($filter['eval'] ?? false) {
                    if ($filter['value']['function'] ?? false) {
                        if (is_callable($filter['value']['function'])) {
                            $filterValue = call_user_func($filter['value']['function']); // TODO: parameters?
                        } else {
                            throw new exception('EXCEPTION_TASK_MODEL_FILTER_VALUE_EVAL_INVALID', exception::$ERRORLEVEL_ERROR, $filter['value']['function']);
                        }
                    } else {
                        throw new exception('EXCEPTION_TASK_MODEL_FILTER_VALUE_FUNCTION_NOT_SET', exception::$ERRORLEVEL_ERROR, $filter['value']);
                    }
                }
                $sourceModel->addDefaultFilter($filter['field'], $filterValue, $filter['operator'], $filter['conjunction'] ?? null);
            }
        }
        if ($this->filtercollections) {
            foreach ($this->filtercollections as $filtercollection) {
                $sourceModel->addDefaultFilterCollection($filtercollection['filters'], $filtercollection['group_operator'] ?? 'AND', $filtercollection['group_name'] ?? 'default', $filtercollection['conjunction'] ?? 'AND');
            }
        }

        $transaction = new transaction('migrate', [$sourceModel, $targetModel]);

        $migratedCount = 0;

        while (true) {
            $start = microtime(true);
            if ($this->config->get('batch_size')) {
                echo("Batch Size: " . ($this->config->get('batch_size')) . chr(10));
                $sourceModel->setLimit(intval($this->config->get('batch_size')));
            }
            $result = $sourceModel->search()->getResult();
            $end = microtime(true);

            echo("Query completed in " . ($end - $start) . ' ms' . chr(10));
            echo("Migrating..." . chr(10));

            if (count($result) === 0) {
                echo("No more migration candidates, breaking" . chr(10));
                break;
            }

            $transaction->start();

            foreach ($result as $sourceDataset) {
                $targetDataset = [];
                foreach ($this->map as $sourceModelField => $targetModelField) {
                    $targetDataset[$targetModelField] = $sourceDataset[$sourceModelField];
                }

                $targetModel->save($targetDataset);
                $lastInsertId = $targetModel->lastInsertId();

                if ($backMap) {
                    $updateSourceDataset = [
                      $sourceModel->getPrimaryKey() => $sourceDataset[$sourceModel->getPrimaryKey()],
                    ];

                    foreach ($backMap as $sourceModelField => $targetModelField) {
                        if ($targetModelField === $targetModel->getPrimaryKey()) {
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

        return new text([
          'text' => "migrated count: " . $migratedCount,
        ]);
    }
}
