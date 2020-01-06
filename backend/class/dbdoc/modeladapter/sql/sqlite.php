<?php
namespace codename\architect\dbdoc\modeladapter\sql;

/**
 * sqlite ddl model adapter
 * @package architect
 */
class sqlite extends \codename\architect\dbdoc\modeladapter\sql {

  /**
   * @inheritDoc
   */
  public function getDriverCompat(): string
  {
    return "sqlite";
  }

  /**
   * @inheritDoc
   */
  public function getPluginCompat(): array
  {
    return array(
      'sql_sqlite',
      'sql'
    );
  }



}
