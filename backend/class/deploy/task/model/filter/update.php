<?php

namespace codename\architect\deploy\task\model\filter;

use codename\architect\deploy\task\model\filter;
use codename\architect\deploy\taskresult;
use codename\core\exception;
use ReflectionException;

/**
 * update a dataset using filters
 */
class update extends filter
{
    /**
     * updatable data
     * @var null|array
     */
    protected ?array $data = null;

    /**
     * {@inheritDoc}
     * @return taskresult
     * @throws ReflectionException
     * @throws exception
     */
    public function run(): taskresult
    {
        $model = $this->getPreparedModel();

        $normalizedData = $model->normalizeData($this->data);

        //
        // TODO: we might make sure there's no PKEY or unique key value inside the dataset
        //

        if ($this->config->get('validate') ?? true) {
            $model->validate($normalizedData);
        }

        if (count($errors = $model->getErrors()) > 0) {
            $text = "Model '{$model->getIdentifier()}' data validation error: " . print_r($errors, true);
        } else {
            $filterQueryComponents = $model->getFilterQueryComponents();

            // perform the update
            $model->update($normalizedData);

            $text = "Model '{$model->getIdentifier()}' mass dataset update using: " . print_r($normalizedData, true);

            if ($this->config->get('verbose')) {
                $text .= "and filters: " . print_r($filterQueryComponents, true);
            }
        }

        return new taskresult\text([
          'text' => $text,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function handleConfig(): void
    {
        parent::handleConfig();
        $this->data = $this->config->get('data');
    }
}
