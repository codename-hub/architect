<?php

namespace codename\architect\dbdoc\plugin\sql\sqlite;

use codename\architect\dbdoc\task;

/**
 * plugin for providing and comparing foreign field config in a model
 * @package architect
 */
class foreign extends \codename\architect\dbdoc\plugin\sql\foreign implements partialStatementInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStructure(): array
    {
        $db = $this->getSqlAdapter()->db;

        $db->query("PRAGMA foreign_key_list('{$this->adapter->schema}.{$this->adapter->model}')");

        return $db->getResult();
    }

    /**
     * {@inheritDoc}
     */
    public function Compare(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getPartialStatement(): array|string|null
    {
        $definition = $this->getDefinition();

        $foreignStatements = [];
        foreach ($definition as $fkeyName => $def) {
            // Omit multi-component Fkeys
            if ($def['optional'] ?? false) {
                continue;
            }

            $constraintName = "fkey_" . md5("{$this->adapter->model}_{$def['model']}_{$fkeyName}_fkey");
            $foreignStatements[] = "CONSTRAINT $constraintName FOREIGN KEY ($fkeyName) REFERENCES `{$def['schema']}.{$def['model']}` ({$def['key']})";
        }

        return $foreignStatements;
    }

    /**
     * {@inheritDoc}
     */
    public function runTask(task $task): void
    {
        // Disabled, as Sqlite's FKEY implementation is quite different.
    }
}
