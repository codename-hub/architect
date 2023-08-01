<?php

namespace codename\architect\dbdoc\plugin\sql\mysql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;
use codename\architect\dbdoc\task;
use codename\core\exception;
use ReflectionException;

/**
 * plugin for providing and comparing user config in database
 * @package architect
 */
class user extends \codename\architect\dbdoc\plugin\sql\user
{
    use modeladapterGetSqlAdapter;

    protected const NEEDED_DML_GRANTS = [
      'select',
      'insert',
      'update',
      'delete', // TODO: we should NOT include this - instead, mark rows as is_deleted = TRUE
    ];

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function Compare(): array
    {
        $tasks = [];
        $structure = $this->getStructure();

        if (!$structure['user_exists']) {
            // create user
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CREATE_USER");
        } elseif (!$structure['password_correct']) {
            // change password
            $tasks[] = $this->createTask(task::TASK_TYPE_REQUIRED, "CHANGE_PASSWORD");
        }

        // run permissions plugin.
        // virtual, if structure/user does not exist (yet)
        $plugin = $this->adapter->getPluginInstance('permissions', [], !$structure['user_exists']);
        if ($plugin != null) {
            // add this plugin to the first
            $this->adapter->addToQueue($plugin, true);
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     * @return array
     * @throws ReflectionException
     * @throws exception
     */
    public function getStructure(): array
    {
        $definition = $this->getDefinition();
        $db = $this->getSqlAdapter()->db;

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

        if ($exists) {
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
        } else {
            $passwordCorrect = null;
        }

        return [
          'user_exists' => $exists,
          'password_correct' => $passwordCorrect,
        ];
    }

    /**
     * {@inheritDoc}
     * @param task $task
     * @throws ReflectionException
     * @throws exception
     */
    public function runTask(task $task): void
    {
        $db = $this->getSqlAdapter()->db;
        $definition = $this->getDefinition();

        if ($task->name == 'CREATE_USER') {
            $db->query(
                "CREATE USER '{$definition['user']}'@'%' IDENTIFIED BY '{$definition['pass']}';"
            );
        }

        if ($task->name == 'CHANGE_PASSWORD') {
            $db->query(
                "UPDATE mysql.user
        SET password = PASSWORD({$definition['pass']})
        WHERE host = '%'
        AND user = '{$definition['user']}'
        ) as result;"
            );
        }

        if ($task->name == 'GRANT_PERMISSIONS') {
            if ($task->data->get('permissions') == null) {
                throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_SQL_MYSQL_USER_PERMISSIONS_INVALID", exception::$ERRORLEVEL_FATAL, $task->data->get());
            }

            foreach ($task->data->get('permissions') as $permission) {
                if (!in_array($permission, self::NEEDED_DML_GRANTS)) {
                    throw new exception("EXCEPTION_ARCHITECT_DBDOC_PLUGIN_SQL_MYSQL_USER_PERMISSIONS_SECURITY_ISSUE", exception::$ERRORLEVEL_FATAL, $task->data->get());
                }
            }

            $permissions = implode(',', $task->data->get('permissions'));

            $db->query(
                "GRANT $permissions
        ON {$this->adapter->schema}.{$this->adapter->model}
        TO '{$definition['user']}'@'%';"
            );
        }
    }
}
