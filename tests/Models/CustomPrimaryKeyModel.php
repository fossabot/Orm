<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 16/09/16
 * Time: 19:04
 */

namespace Tsukasa\Orm\Tests\Models;

use Tsukasa\Orm\Fields\IntField;
use Tsukasa\Orm\AbstractModel;

class CustomPrimaryKeyModel extends AbstractModel
{
    public static function getFields()
    {
        return [
            'id' => [
                'class' => IntField::class,
                'primary' => true
            ],
        ];
    }
}