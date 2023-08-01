<?php

namespace codename\architect\dbdoc\plugin\sql\mysql;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
class field extends \codename\architect\dbdoc\plugin\sql\field
{
    /**
     * array of default datatype (note the difference to the column type!)
     * @var array
     */
    protected array $defaultsConversionTable = [
      'bigint' => 'bigint(20)',
      'integer' => 'int(11)',
      'text' => 'text',
      'date' => 'date',
      'datetime' => 'datetime',
    ];

    /**
     * {@inheritDoc}
     */
    public function getDefinition(): array
    {
        $definition = parent::getDefinition();
        // TODO: check if this is the correct behaviour
        // the base class sql\field may already set db_data_type, e.g. if it's a primary key

        // field is a virtual field (collection)
        if ($definition['collection']) {
            return $definition;
        }

        if ($definition['datatype'] == 'virtual') {
            return $definition;
        }

        if (!is_array($definition['field'])) {
            $definition['options'] = array_replace($definition['options'], $this->convertFieldConfigurationToDbColumnType($definition));
        }
        return $definition;
    }

    /**
     * {@inheritDoc}
     */
    public function getDbDataTypeDefaultsTable(): array
    {
        return $this->defaultsConversionTable;
    }
}
