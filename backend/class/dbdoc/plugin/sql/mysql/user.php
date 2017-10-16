<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
use codename\architect\dbdoc\task;
use codename\core\exception;

/**
 * plugin for providing and comparing user config in database
 * @package architect
 */
class user extends \codename\architect\dbdoc\plugin\sql\user {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $definition = $this->getDefinition();
    $db = $this->getSqlAdapter()->db;

    $permissions = array();

    // query user dataset
    $db->query(
      "SELECT exists(
        SELECT 1
        FROM mysql.user
        WHERE host = '%'
        AND user = '{$definition['user']}'
      ) as result;"
    );
    $exists = $db->getResult()[0]['result'];

    if($exists) {
      // check password, indirectly
      $db->query(
        "SELECT exists(
          SELECT 1
          FROM mysql.user
          WHERE host = '%'
          AND user = '{$definition['user']}'
          AND password = PASSWORD('{$definition['pass']}')
        ) as result;"
      );
      $passwordCorrect = $db->getResult()[0]['result'];


      $db->query(
        "SELECT table_priv
          FROM mysql.tables_priv
          WHERE host = '%'
          AND user = '{$definition['user']}'
          AND db = '{$this->adapter->schema}'
          AND table_name = '{$this->adapter->model}';"
      );

      $permissionsResult = $db->getResult();

      if(count($permissionsResult) === 1) {
        // yiss, we have it!
        // Format:  Select,Update,...
        $permissions = explode(',', $permissionsResult[0]['table_priv']);
      }
    } else {
      $passwordCorrect = null;
    }

    return array(
      'user_exists' => $exists,
      'password_correct' => $passwordCorrect,
      'permissions' => $permissions
    );
  }

  protected const NEEDED_DML_GRANTS = array(
    'select',
    'insert',
    'update',
    'delete' // TODO: we should NOT include this - instead, mark rows as is_deleted = TRUE
  );

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();

    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    if(!$structure['user_exists']) {
      // create user
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_USER", array());
    } else if(!$structure['password_correct']) {
      // change password
      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CHANGE_PASSWORD", array());
    } else {
      // check permissions:

      $needed = self::NEEDED_DML_GRANTS;
      $missing = array();

      foreach($needed as $permission) {
        if(!self::in_arrayi($permission, $structure['permissions'])) {
          $missing[] = $permission;
        }
      }

      if(count($missing) > 0) {
        $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "GRANT_PERMISSIONS", array(
          'permissions' => $missing
        ));
      }

    }
    return $tasks;
  }

  /**
   * [in_arrayi description]
   * @param  [type] $needle   [description]
   * @param  [type] $haystack [description]
   * @return bool             [description]
   */
  protected static function in_arrayi($needle, $haystack) : bool {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
  }

  /**
   * @inheritDoc
   */
  public function runTask(\codename\architect\dbdoc\task $task)
  {
    $db = $this->getSqlAdapter()->db;
    $definition = $this->getDefinition();

    if($task->name == 'CREATE_USER') {
      $db->query(
        "CREATE USER '{$definition['user']}'@'%' IDENTIFIED BY '{$definition['pass']}';"
      );
    }

    if($task->name == 'CHANGE_PASSWORD') {
      $db->query(
        "UPDATE mysql.user
        SET password = PASSWORD({$definition['pass']})
        WHERE host = '%'
        AND user = '{$definition['user']}'
        ) as result;"
      );
    }

    if($task->name == 'GRANT_PERMISSIONS') {

      if($task->data->get('permissions') == null) {
        throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_SQL_MYSQL_USER_PERMISSIONS_INVALID", exception::$ERRORLEVEL_FATAL, $task->data->get());
      }

      foreach($task->data->get('permissions') as $permission) {
        if(!in_array($permission, self::NEEDED_DML_GRANTS)) {
          throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_SQL_MYSQL_USER_PERMISSIONS_SECURITY_ISSUE", exception::$ERRORLEVEL_FATAL, $task->data->get());
        }
      }

      $permissions = implode(',', $task->data->get('permissions'));

      $db->query(
        "GRANT {$permissions}
        ON {$this->adapter->schema}.{$this->adapter->model}
        TO '{$definition['user']}'@'%';"
      );

    }
  }


}
