<?php
namespace codename\architect\deploy\task\model\filter;

use codename\architect\deploy\taskresult;

use codename\core\exception;

/**
 * update a dataset using filters
 */
class update extends \codename\architect\deploy\task\model\filter {

  /**
   * updatable data
   * @var array
   */
  protected $data = null;

  /**
   * @inheritDoc
   */
  protected function handleConfig()
  {
    parent::handleConfig();
    $this->data = $this->config->get('data');
  }

  /**
   * @inheritDoc
   */
  public function run(): \codename\architect\deploy\taskresult
  {
    $model = $this->getPreparedModel();

    $normalizedData = $model->normalizeData($this->data);

    //
    // TODO: we might make sure there's no PKEY or unique key value inside the dataset
    //

    $model->validate($normalizedData);

    $text = '';

    if(count($errors = $model->getErrors()) > 0) {
      $text = "Model '{$model->getIdentifier()}' data validation error: " . print_r($errors, true);
    } else {

      $filterQueryComponents = $model->getFilterQueryComponents();

      // perform the update
      $model->update($normalizedData);

      $text = "Model '{$model->getIdentifier()}' mass dataset update using: " . print_r($normalizedData, true);

      if($this->config->get('verbose')) {
        $text .= "and filters: " . print_r($filterQueryComponents, true);
      }
    }

    return new taskresult\text([
      'text' => $text
    ]);

  }
}
