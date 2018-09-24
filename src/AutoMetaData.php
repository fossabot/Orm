<?php
namespace Tsukasa\Orm;

use Doctrine\DBAL\Schema\Column;
use Modules\Core\Helpers\Cache;
use ReflectionMethod;

use Tsukasa\Orm\Fields\BigIntField;
use Tsukasa\Orm\Fields\BlobField;
use Tsukasa\Orm\Fields\CharField;
use Tsukasa\Orm\Fields\DateField;
use Tsukasa\Orm\Fields\DateTimeField;
use Tsukasa\Orm\Fields\DecimalField;
use Tsukasa\Orm\Fields\FloatField;
use Tsukasa\Orm\Fields\IntField;
use Tsukasa\Orm\Fields\TextField;
use Tsukasa\Orm\Fields\TimeField;

class AutoMetaData extends MetaData
{
    private static $_tables;
    private static $_configs;

    /**
     * @param string $className
     * @throws \Doctrine\DBAL\DBALException
     * @throws \ReflectionException
     */
    protected function init($className)
    {
        $this->initTableData();

        if ((new ReflectionMethod($className, 'getFields'))->isStatic()
//            || (new ReflectionMethod($className, 'getColumns'))->isStatic()
        ) {
            parent::init($className);
        }

        $primaryFields = [];


        foreach ($this->getTableConfig($className) as $name => $config)
        {
            if (!isset($this->fields[$name])) {
                /** @var \Tsukasa\Orm\Fields\Field $field */
                $field = $this->createField($config);
                $field->setName($name);
                $field->setModelClass($className);

                $this->fields[$name] = $field;
                $this->mapping[$field->getAttributeName()] = $name;

                if ($field->primary) {
                    $primaryFields[] = $field->getAttributeName();
                }
            }
        }

        if (empty($primaryFields) && empty($this->primaryKeys)) {
            $this->primaryKeys = \call_user_func([$className, 'getPrimaryKeyName']);
        }
        elseif (!empty($primaryFields)) {

            $this->primaryKeys = $primaryFields;
        }
    }

    /**
     * @param string $className
     *
     * @return \Doctrine\DBAL\Schema\Column[]
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getTableColumns($className): array
    {
        if (!isset(self::$_tables[$className]))
        {
            self::$_tables[$className] = Xcart::app()->db
                ->getConnection()
                ->getSchemaManager()
                ->listTableColumns(\call_user_func([$className, 'tableName']));
        }

        return self::$_tables[$className];
    }

    /**
     * @param string $className
     *
     * @return array Config fields as $name => $config
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getTableConfig($className): array
    {
        if (!isset(self::$_configs[$className]))
        {
            foreach ($this->getTableColumns($className) as $column) {
                $name = $column->getName();

                if (!isset($this->fields[$name])) {
                    if ($config = $this->getConfigFromDBAL($column)) {
                        self::$_configs[$className][$name] = $config;
                    }
                }
            }
        }

        return self::$_configs[$className];
    }

    private function getConfigFromDBAL(Column $column)
    {
        if ($type = $column->getType())
        {
            $config = [
                'null'    => !$column->getNotnull(),
                'default' => $column->getDefault(),
            ];

            if ($column->getLength()) {
                $config['length'] = $column->getLength();
            }

            switch ($type->getName()) {
                case 'smallint' :
                case 'integer' : {
                    $config['class'] = IntField::class;
                    break;
                }
                case 'bigint' : {
                    $config['class'] = BigIntField::class;
                    break;
                }
                case 'decimal' : {
                    $config['class'] = DecimalField::class;
                    $config['precision'] = $column->getPrecision();
                    $config['scale'] = $column->getScale();
                    break;
                }
                case 'float' : {
                    $config['class'] = FloatField::class;
                    break;
                }

                case 'blob' : {
                    $config['class'] = BlobField::class;
                    unset($config['length']);
                    break;
                }
                case 'date' : {
                    $config['class'] = DateField::class;
                    break;
                }
                case 'datetime' : {
                    $config['class'] = DateTimeField::class;
                    break;
                }
                case 'time' : {
                    $config['class'] = TimeField::class;
                    break;
                }
//            case 'timeshtamp' : {
//                $config['class'] = TimestampField::class;
//                break;
//            }

                case 'string' : {
                    $config['class'] = CharField::class;
                    break;
                }
                case 'longtext' :
                case 'text' : {
                    unset($config['length']);
                }
                default: {
                    $config['class'] = TextField::class;
                }
            }

            return $config;
        }

        return null;
    }

    public function initTableData(): void
    {

        self::$_tables = [];

        if (null === self::$_configs)
        {
            if (Xcart::app()->hasComponent('event') && Xcart::app()->hasComponent('cache'))
            {
                self::$_configs = Xcart::app()->cache->get('auto_meta_data_configs', []);
                Xcart::app()->event->on('app:end', [$this, 'saveCache']);
            }
            else {
                self::$_configs = [];
            }
        }
    }

    public static function saveCache($owner): void
    {
        if (!\defined('APP_DEBUG') && self::$_configs) {
            Xcart::app()->cache->set('auto_meta_data_configs', self::$_configs, Cache::CACHE_HALF_DAY);
        }
    }
}