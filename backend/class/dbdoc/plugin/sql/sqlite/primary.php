<?php

namespace codename\architect\dbdoc\plugin\sql\sqlite;

use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing model primary key config
 * @package architect
 */
class primary extends \codename\architect\dbdoc\plugin\sql\primary
{
    /**
     * default column data type for primary keys on mysql
     * @var string
     */
    public const DB_DEFAULT_DATA_TYPE = 'INTEGER';
    /**
     * default column type for primary keys on mysql
     * @var string
     */
    public const DB_DEFAULT_COLUMN_TYPE = 'INTEGER';

    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        // get some column specifications
        $db = $this->getSqlAdapter()->db;

        // $db->query(
        //   "PRAGMA table_info('{$this->adapter->schema}.{$this->adapter->model}');"
        // );

        $db->query(
            "SELECT name AS column_name, type AS column_type, type AS data_type
      FROM pragma_table_info('{$this->adapter->schema}.{$this->adapter->model}')
      WHERE
      pk = 1;"
        );

        $res = $db->getResult();
        if (count($res) === 1) {
            return $res[0];
        }
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        $definition = parent::getDefinition();
        $definition['options'] = $this->adapter->config->get('options>' . $definition['field']) ?? [];
        $definition['options']['db_data_type'] = $definition['options']['db_data_type'] ?? [self::DB_DEFAULT_DATA_TYPE]; // NOTE: this has to be an array
        $definition['options']['db_column_type'] = $definition['options']['db_column_type'] ?? [self::DB_DEFAULT_COLUMN_TYPE]; // NOTE: this has to be an array
        return $definition;
    }

    /**
     * {@inheritDoc}
     */
    protected function checkPrimaryKeyAttributes(array $definition, array $structure): array
    {
        $tasks = [];

        if ($structure['data_type'] != self::DB_DEFAULT_DATA_TYPE) {
            // suggest column data_type modification
            $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_DATATYPE", [
              'field' => $structure['column_name'],
              'db_data_type' => self::DB_DEFAULT_DATA_TYPE,
            ]);
        } elseif ($structure['column_type'] != self::DB_DEFAULT_COLUMN_TYPE) {
            // suggest column column_type modification
            $tasks[] = $this->createTask(task::TASK_TYPE_SUGGESTED, "MODIFY_COLUMN_COLUMNTYPE", [
              'field' => $structure['column_name'],
              'db_data_type' => self::DB_DEFAULT_DATA_TYPE,
              'db_column_type' => self::DB_DEFAULT_COLUMN_TYPE,
            ]);
        }

        return $tasks;
    }
}
