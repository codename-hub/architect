<?php
namespace codename\architect\dbdoc\plugin\sql\mysql;
use codename\architect\dbdoc\task;
use codename\core\exception;

/**
 * plugin for providing and comparing user config in database
 * @package architect
 */
class permissions extends \codename\architect\dbdoc\plugin\sql\permissions {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $definition = $this->getDefinition();
    $db = $this->getSqlAdapter()->db;

    $permissions = array();

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

    return $permissions;
  }

  /**
   * @inheritDoc
   */
  public function getDefinition()
  {
    // needed DML grants
    return array(
      'user' => $this->adapter->getPluginInstance('user')->getDefinition()['user'],
      'permissions' => array(
        'select',
        'insert',
        'update',
        'delete' // TODO: we should NOT include this - instead, mark rows as is_deleted = TRUE
      )
    );
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $tasks = array();

    $definition = $this->getDefinition();
    $structure = $this->virtual ? array() : $this->getStructure();

    $missing = array();

    foreach($definition['permissions'] as $permission) {
      if(!self::in_arrayi($permission, $structure)) {
        $missing[] = $permission;
      }
    }

    if(count($missing) > 0) {

      $userPlugin = $this->adapter->getPluginInstance('user');
      $tablePlugin = $this->adapter->getPluginInstance('table');

      $precededBy = [
        $userPlugin->getTaskIdentifierPrefix(), // execute user-related plugins first
        $tablePlugin->getTaskIdentifierPrefix() // also table-related ones
      ];

      $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "GRANT_PERMISSIONS", array(
        'permissions' => $missing
      ), $precededBy);
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

    if($task->name == 'GRANT_PERMISSIONS') {

      if($task->data->get('permissions') == null) {
        throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_SQL_MYSQL_USER_PERMISSIONS_INVALID", exception::$ERRORLEVEL_FATAL, $task->data->get());
      }

      foreach($task->data->get('permissions') as $permission) {
        if(!in_array($permission, $this->getDefinition()['permissions'])) {
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
