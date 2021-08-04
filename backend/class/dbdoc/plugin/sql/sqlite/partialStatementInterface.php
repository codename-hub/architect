<?php
namespace codename\architect\dbdoc\plugin\sql\sqlite;

interface partialStatementInterface
{
  /**
   * Returns a partial statement
   * (final state that is desired)
   * @return array|string|null
   */
  function getPartialStatement();
}
