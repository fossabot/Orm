<?php
namespace Tsukasa\Orm\Fields;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class SerializeField extends TextField
{

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (is_string($value)) {
            return unserialize($value);
        }

        return parent::convertToPHPValue($value, $platform);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!is_string($value) && !is_null($value)) {
            $value = serialize($value);
        }
        return parent::convertToDatabaseValue($value, $platform);
    }

}