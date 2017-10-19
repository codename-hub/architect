<?php
namespace codename\architect\dbdoc\plugin\sql;

/**
 * we may add some kind of loading prevention, if some classes are not loaded/undefined
 * as we're using a filename that is the same as standard php scripts loaded for directories
 * if none is given
 */

/**
 * plugin for providing and comparing index / indices field config in a model
 * @package architect
 */
class index extends \codename\architect\dbdoc\plugin\index {

  /**
   * @inheritDoc
   */
  public function Compare()
  {
    $value = parent::Compare();

    // TODO

    return $value;
  }

  /**
   * @inheritDoc
   */
  public function getStructure()
  {
    /*
     we may use some query like this:
     // @see: https://stackoverflow.com/questions/5213339/how-to-see-indexes-for-a-database-or-table

     SELECT (DISTINCT?) s.*
     FROM INFORMATION_SCHEMA.STATISTICS s
     LEFT OUTER JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
         ON t.TABLE_SCHEMA = s.TABLE_SCHEMA
            AND t.TABLE_NAME = s.TABLE_NAME
            AND s.INDEX_NAME = t.CONSTRAINT_NAME
     WHERE 0 = 0
           AND t.CONSTRAINT_NAME IS NULL
           AND s.TABLE_SCHEMA = 'YOUR_SCHEMA_SAMPLE';


      *** removal:
      DROP INDEX `indexname` ON `tablename`


     */
    throw new \LogicException('Not implemented'); // TODO
  }

}