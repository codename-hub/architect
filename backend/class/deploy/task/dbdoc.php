<?php
namespace codename\architect\deploy\task;

use codename\architect\deploy\taskresult;

use codename\core\exception;

/**
 * task for running dbdoc
 */
class dbdoc extends \codename\architect\deploy\task {

  /**
   * whether DBDoc executes tasks
   * true => not a dryrun, applies changes
   * false => dryrun, more or less a test
   * @var bool
   */
  protected $executionFlag = false;

  /**
   * to-be-executed task types as int ids
   * @see \codename\architect\dbdoc\task::TASK_TYPES
   * @var int[]
   */
  protected $executeTaskTypes = [];

  /**
   * @inheritDoc
   */
  protected function handleConfig()
  {
    parent::handleConfig();
    $this->executionFlag = $this->config->get('executionFlag') ?? false;

    // map string task types to int codes
    $executeTaskTypes = [];
    foreach($this->config->get('executeTaskTypes') as $typeName) {
      foreach(\codename\architect\dbdoc\task::TASK_TYPES as $taskTypeInt => $taskTypeName) {
        if($typeName === $taskTypeName) {
          $executeTaskTypes[] = $taskTypeInt;
        }
      }
    }
    $this->executeTaskTypes = $executeTaskTypes;
  }

  /**
   * @inheritDoc
   */
  public function run(): \codename\architect\deploy\taskresult
  {
    $executionFlagString = var_export($this->executionFlag, true);
    $executeTaskTypesString = implode(', ', $this->executeTaskTypes);
    $executeTaskTypeNamesString = implode(', ', $this->config->get('executeTaskTypes'));

    try {

      // build a new dbdoc instance
      $dbdoc = new \codename\architect\dbdoc\dbdoc(
        $this->getDeploymentInstance()->getApp(),
        $this->getDeploymentInstance()->getVendor()
      );

      // run it with params
      $res = $dbdoc->run($this->executionFlag, $this->executeTaskTypes);

      // handle result
      $textResult = "Dbdoc execution success with executionFlag: {$executionFlagString} and task types: [ {$executeTaskTypesString} ] ([ {$executeTaskTypeNamesString} ])";

      // if verbose, additionally export the dbdoc results
      if($this->config->get('verbose')) {
        $textResult .= print_r($res, true);
      }

    } catch (\Exception $e) {

      // prepare error output
      $textResult = "Dbdoc exception: " . $e->getCode() . ' ' . $e->getMessage() . " using executionFlag: {$executionFlagString} and task types: [ {$executeTaskTypesString} ] ([ {$executeTaskTypeNamesString} ])";
      if($e instanceof exception) {
        $textResult .= print_r($e->info, true);
      }

      if($this->config->get('verbose')) {
        $textResult .= print_r($e->getTrace(), true);
      }

    }

    return new taskresult\text([
      'text' => $textResult
    ]);
  }

}
