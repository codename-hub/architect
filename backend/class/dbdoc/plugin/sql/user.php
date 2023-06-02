<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

/**
 * plugin for providing and comparing user config in database
 * @package architect
 */
abstract class user extends \codename\architect\dbdoc\plugin\user
{
    use modeladapterGetSqlAdapter;
}
