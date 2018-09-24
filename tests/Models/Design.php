<?php
/**
 * 
 *
 * All rights reserved.
 * 
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 04/03/14.03.2014 01:15
 */

namespace Tsukasa\Orm\Tests\Models;


use Tsukasa\Orm\Fields\CharField;
use Tsukasa\Orm\Fields\ForeignField;
use Tsukasa\Orm\Model;

/**
 * Class Design
 * @package Tsukasa\Orm\Tests\Models
 * @property string name
 */
class Design extends Model
{
    public static function getFields()
    {
        return [
            'name' => [
                'class' => CharField::class
            ],
            'cup' => [
                'class' => ForeignField::class,
                'modelClass' => Cup::class
            ]
        ];
    }
}
