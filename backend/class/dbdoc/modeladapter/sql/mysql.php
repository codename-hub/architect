<?php
namespace codename\architect\dbdoc\modeladapter\sql;

/**
 * mysql ddl model adapter
 * @package architect
 */
class mysql extends \codename\architect\dbdoc\modeladapter\sql {

  /**
   * @inheritDoc
   */
  public function getDriverCompat(): string
  {
    return "mysql";
  }

  /**
   * @inheritDoc
   */
  public function getPluginCompat(): array
  {
    return array(
      'sql_mysql',
      'sql'
    );
  }



}