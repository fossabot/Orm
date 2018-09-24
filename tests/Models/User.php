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
use Tsukasa\Orm\Fields\HasManyField;
use Tsukasa\Orm\Fields\ManyToManyField;
use Tsukasa\Orm\Fields\PasswordField;
use Tsukasa\Orm\Model;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class User
 * @package Tsukasa\Orm\Tests\Models
 * @property string username
 * @property string password
 */
class User extends Model
{
    public static function getFields()
    {
        return [
            'username' => [
                'class' => CharField::class,
                'null' => false,
                'validators' => [
                    new Assert\Length(['min' => 3, 'max' => 20]),
                ]
            ],
            'password' => new PasswordField([
                'null' => true
            ]),
            'groups' => [
                'class' => ManyToManyField::class,
                'modelClass' => Group::class,
                'through' => Membership::class,
                'link' => ['user_id', 'group_id']
            ],
            'addresses' => [
                'class' => HasManyField::class,
                'modelClass' => Customer::class,
                'link' => ['user_id', 'id'],
                'editable' => false,
            ],
        ];
    }

    public static function tableName()
    {
        return 'users';
    }

    public static function objectsManager($instance = null)
    {
        $className = get_called_class();
        return new UserManager($instance ? $instance : new $className);
    }
}
