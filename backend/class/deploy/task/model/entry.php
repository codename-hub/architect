<?php
namespace codename\architect\deploy\task\model;

use codename\architect\deploy\taskresult;

use codename\core\exception;

/**
 * class for managing a model entry
 */
class entry extends \codename\architect\deploy\task\model {

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
    $model = $this->getModelInstance();

    $normalizedData = $model->normalizeData($this->data);

    $model->validate($normalizedData);

    $text = '';

    if(count($errors = $model->getErrors()) > 0) {
      $text = "Model '{$model->getIdentifier()}' data validation error: " . print_r($errors, true);
    } else {

      // check for PKEY value or uniques
      if($normalizedData[$model->getPrimarykey()] ?? false) {

        // we have a primary key - update the whole dataset
        $model->save($normalizedData);
        $text = "Model '{$model->getIdentifier()}' saved via PKEY";

      } else {
        if($model->getConfig()->get('unique')) {
          $filtersAdded = false;
          foreach($model->getConfig()->get('unique') as $uniqueKey) {
            if(is_array($uniqueKey)) {
              // multiple keys, combined unique key
              $filters = [];
              foreach($uniqueKey as $key) {
                if($normalizedData[$key] ?? false) {
                  $filters[] = [ 'field' => $key, 'operator' => '=', 'value' => $normalizedData[$key]];
                } else {
                  // irrelevant unique key, one value is null
                  $filters = [];
                  break;
                }
              }
              if(count($filters) > 0) {
                $filtersAdded = true;
                $model->addFilterCollection($filters, 'AND');
              }
            } else {
              // single unique key field
              $filtersAdded = true;
              $model->addFilter($uniqueKey, $normalizedData[$uniqueKey]);
            }
          }

          if($filtersAdded) {
            $res = $model->search()->getResult();
            if(count($res) === 1) {
              // update using found PKEY
              $normalizedData[$model->getPrimarykey()] = $res[0][$model->getPrimarykey()];
              $model->save($normalizedData);
              $text = "Model '{$model->getIdentifier()}' saved via filter, updated";
            } else if(count($res) === 0) {
              //
              // NOTE/WARNING:
              // if you have a PKEY or UNIQUE key constraint values differing
              // (two datasets which could match the filter)
              // we're trying to save here
              // which will cause an error.
              //
              // insert
              $model->save($normalizedData);
              $text = "Model '{$model->getIdentifier()}' saved via filter, inserted/created.";
            } else {
              // error - multiple results
              throw new exception('EXCEPTION_TASK_MODEL_ENTRY_MULTIPLE_UNIQUE_KEY_RESULTS', exception::$ERRORLEVEL_ERROR, $res);
            }
          } else {
            // no filterable fields as a base
            throw new exception('EXCEPTION_TASK_MODEL_ENTRY_NO_FILTERABLE_KEYS', exception::$ERRORLEVEL_ERROR, $res);
          }

        } else {
          // error, not handleable:
          // no PKEY and no unique-combination given
          throw new exception('EXCEPTION_TASK_MODEL_ENTRY_NO_UNIQUE_OR_PRIMARY_KEYS_GIVEN_OR_AVAILABLE', exception::$ERRORLEVEL_ERROR);
        }
      }
    }

    return new taskresult\text([
      'text' => $text
    ]);
  }

}
