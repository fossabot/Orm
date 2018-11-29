<?php
namespace Tsukasa\Orm\Exception;

class OrmExceptions extends \RuntimeException
{

    public static function FailCreateLink()
    {
        throw new self('At the table when there is a composite key. It is impossible to build link automatically.');
    }
}