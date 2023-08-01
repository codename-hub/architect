<?php

namespace codename\architect\deploy\task\model;

use codename\core\exception;
use codename\core\model;
use ReflectionException;

/**
 * base class for filtering
 * which extends to deleting or updating mass datasets
 */
abstract class filter extends \codename\architect\deploy\task\model
{
    /**
     * list of filters to be applied
     * @var null|array
     */
    protected ?array $filters = null;

    /**
     * list of filtercollections to be applied
     * @var null|array
     */
    protected ?array $filtercollections = null;

    /**
     * {@inheritDoc}
     */
    protected function handleConfig(): void
    {
        parent::handleConfig();
        $this->filters = $this->config->get('filter') ?? null;
        $this->filtercollections = $this->config->get('filtercollection') ?? null;
    }

    /**
     * returns a prepared model instance (with filters and stuff)
     * @return model [description]
     * @throws ReflectionException
     * @throws exception
     */
    protected function getPreparedModel(): model
    {
        $model = $this->getModelInstance();

        $filtersApplied = false;
        if ($this->filters) {
            $filtersApplied = true;
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
                $model->addDefaultFilter($filter['field'], $filterValue, $filter['operator'], $filter['conjunction'] ?? null);
            }
        }
        if ($this->filtercollections) {
            $filtersApplied = true;
            foreach ($this->filtercollections as $filtercollection) {
                // evaluate filters
                $filters = [];
                foreach ($filtercollection['filters'] as $filter) {
                    $filterValue = $filter['value'];
                    if ($filter['eval'] ?? false) {
                        if ($filter['value']['function'] ?? false) {
                            if (is_callable($filter['value']['function'])) {
                                $filterValue = call_user_func($filter['value']['function']); // TODO: parameters?
                            } else {
                                throw new exception('EXCEPTION_TASK_MODEL_FILTERCOLLECTION_FILTER_VALUE_EVAL_INVALID', exception::$ERRORLEVEL_ERROR, $filter['value']['function']);
                            }
                        } else {
                            throw new exception('EXCEPTION_TASK_MODEL_FILTERCOLLECTION_FILTER_VALUE_FUNCTION_NOT_SET', exception::$ERRORLEVEL_ERROR, $filter['value']);
                        }
                    }
                    $filters[] = ['field' => $filter['field'], 'operator' => $filter['operator'], 'value' => $filterValue];
                }

                $model->addDefaultFilterCollection($filters, $filtercollection['group_operator'] ?? 'AND', $filtercollection['group_name'] ?? 'default', $filtercollection['conjunction'] ?? 'AND');
            }
        }
        if (!$filtersApplied) {
            throw new exception('EXCEPTION_TASK_MODEL_FILTER_INVALID', exception::$ERRORLEVEL_ERROR);
        }
        return $model;
    }
}
