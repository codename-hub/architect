<?php

namespace codename\architect\dbdoc\plugin\sql;

use codename\architect\dbdoc\modeladapter\modeladapterGetSqlAdapter;

/**
 * plugin for providing and comparing user config in database
 * @package architect
 */
abstract class permissions extends \codename\architect\dbdoc\plugin\permissions
{
    use modeladapterGetSqlAdapter;
}
