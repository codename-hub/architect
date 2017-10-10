<?php
namespace codename\architect\dbdoc\plugin\sql;

/**
 * plugin for providing and comparing model field data details
 * @package architect
 */
class field extends \codename\architect\dbdoc\plugin\field {
  use \codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    $db = $this->getSqlAdapter()->db;
    $db->query(
      "SELECT column_name, column_type, data_type
      FROM information_schema.columns
      WHERE table_schema = '{$this->adapter->schema}'
      AND table_name = '{$this->adapter->model}'
      AND column_name = '{$this->parameter['field']}';"
    );
    $res = $db->getResult();
    if(count($res) === 1) {
      return $res[0];
    }
    return null;
  }

  /**
   * @inheritDoc
   */
  public function Compare() : array
  {
    $definition = $this->getDefinition();
    $structure = $this->getStructure();

    if($structure != null) {
      print_r($definition);
      print_r($structure);
    } else {
      // some error !
      // print_r($definition);
      // print_r($structure);

      // create create-field task
      return array(
        $this->createTask(array(
          'command' => 'CREATE_COLUMN',
          'field' => $definition['field'],
          'datatype' => $definition['datatype'],
          'datatype_override' => $definition['datatype_override'],
          'db_datatype' => $definition['datatype_override'] ?? $this->convertModelDataTypeToDbType($definition['datatype']) // first item == default?
        ))
      );
    }
    return array();
  }

  /**
   * basic conversion table between mysql defaults and core framework
   * @var [type]
   */
  protected $conversionTable = array(
      'text' => array('text', 'mediumtext'),
      'text_timestamp' => 'datetime',
      'text_date' => 'date',
      'number' => 'numeric', // was integer
      'number_natural' => array('integer', 'int', 'bigint'),
      'boolean' => 'boolean',
      'structure' => array('text', 'mediumtext'),
      'mixed' => array('text')
  );

  public function getConversionTable(): array
  {
    return $this->conversionTable;
  }

  public function convertModelDataTypeToDbType($t) {
		// check for existing overrides/matching types
    $conversionTable = $this->getConversionTable();
		if(array_key_exists($t,$conversionTable)) {
			// use defined type
			return $conversionTable[$t];
		} else {
			$tArr = explode('_', $t);
			if(array_key_exists($tArr[0], $conversionTable)) {
				// we have a defined underlying db field type
				return $conversionTable[$tArr[0]];
			} else {
				// throw some error, as it is not in our type definition library
        return '';
        // throw new \codename\core\exception('DBDOC_MODEL_DATATYPE_NOT_IN_DEFINITION_LIBRARY', \codename\core\exception::$ERRORLEVEL_ERROR, array($t, $tArr[0]));
			}
		}
	}
}
