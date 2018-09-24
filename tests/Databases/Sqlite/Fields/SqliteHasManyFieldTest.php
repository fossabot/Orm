<?php

/**
 * All rights reserved.
 *
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 06/02/15 18:56
 */

namespace Tsukasa\Orm\Tests\Databases\Sqlite\Fields;

use Tsukasa\Orm\Tests\Fields\HasManyFieldTest;

class SqliteHasManyFieldTest extends HasManyFieldTest
{
    public $driver = 'sqlite';
}
