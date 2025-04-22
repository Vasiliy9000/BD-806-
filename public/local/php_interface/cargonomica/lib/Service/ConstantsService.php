<?php
namespace Cargonomica\Service;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\GroupTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Text\StringHelper;

/**
 * Класс определяет динамические константы идентификаторов.
 */
class ConstantsService
{
    /**
     * Идентификатор кеширования.
     */
    protected const CACHE_IDENTIFIER = 'dynamicIdentifiers';

    /**
     * Время кеширования.
     */
    protected const CACHE_TIME = 3600 * 24 * 365;

    /**
     * Постфиксы констант в зависимости от того, к какому типу относится идентификатор.
     */
    public const IDENTIFIER_TYPE_POSTFIXES = [
        'userGroups' => '_UG_ID',
        'infoBlocks' => '_IB_ID',
        'highLoadBlocks' => '_HLB_ID',
    ];

    /**
     * Для всех необходимых идентификаторов генерирует php-константы.
     * В дальнейшем коде вместо жёстких использований идентификаторов следует использовать константы.
     * Константа в базовом случае состоит из символьного кода сущности и постфикса, обозначающего тип сущности.
     *
     * @param bool $dontUseCache
     * @return void
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function define(bool $dontUseCache = false): void
    {
        static::includeModules();

        foreach (static::getValues($dontUseCache) as $identifierType => $identifiers) {
            foreach ($identifiers as $code => $value) {
                define($code . static::IDENTIFIER_TYPE_POSTFIXES[$identifierType], $value);
            }
        }
    }

    /**
     * Возвращает значения идентификаторов для генерации констант.
     *
     * @param bool $dontUseCache
     * @return array|array[]
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected static function getValues(bool $dontUseCache = false): array
    {
        $cacheInstance = Cache::createInstance();

        $cacheInstance->forceRewriting($dontUseCache);

        if ($cacheInstance->initCache(static::CACHE_TIME, static::CACHE_IDENTIFIER)) {
            return $cacheInstance->getVars();
        } elseif ($cacheInstance->startDataCache()) {
            $constants = [
                'userGroups' => static::getUserGroupValues(),
                'infoBlocks' => static::getInfoBlockValues(),
                'highLoadBlocks' => static::getHighLoadBlockValues(),
            ];

            $cacheInstance->endDataCache($constants);
        }

        return $constants ?? [];
    }

    /**
     * Возвращает массив идентификаторов групп пользователей.
     *
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected static function getUserGroupValues(): array
    {
        $values = [];

        $userGroupsDbResult = GroupTable::getList([
            'select' => ['ID', 'STRING_ID'],
        ]);

        while ($userGroup = $userGroupsDbResult->fetch()) {
            $userGroupCode = strtoupper(
                str_replace(
                    ['.', '-', ' '],
                    '_',
                    $userGroup['STRING_ID'],
                ),
            );

            if (!$userGroupCode) {
                continue;
            }

            $values[$userGroupCode] = (int)$userGroup['ID'];
        }

        return $values;
    }

    /**
     * Возвращает массив идентификаторов информационных блоков.
     *
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected static function getInfoBlockValues(): array
    {
        $values = [];

        $infoBlocksDbResult = IblockTable::getList([
            'select' => ['ID', 'CODE'],
        ]);

        while ($infoBlock = $infoBlocksDbResult->fetch()) {
            $infoBlockCode = strtoupper(
                str_replace(
                    ['.', '-', ' '],
                    '_',
                    $infoBlock['CODE'],
                ),
            );

            if (!$infoBlockCode) {
                continue;
            }

            $values[$infoBlockCode] = (int)$infoBlock['ID'];
        }

        return $values;
    }

    /**
     * Возвращает массив идентификаторов highload-блоков.
     *
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected static function getHighLoadBlockValues(): array
    {
        $values = [];

        $highLoadBlocksDbResult = HighloadBlockTable::getList([
            'select' => ['ID', 'NAME'],
        ]);

        while ($highLoadBlock = $highLoadBlocksDbResult->fetch()) {
            $highLoadBlockCode = strtoupper(StringHelper::camel2snake(
                str_replace(
                    ['.', '-', ' '],
                    '_',
                    $highLoadBlock['NAME'],
                ),
            ));

            if (!$highLoadBlockCode) {
                continue;
            }

            $values[$highLoadBlockCode] = (int)$highLoadBlock['ID'];
        }

        return $values;
    }

    /**
     * Подключает модули, необходимые для установки констант.
     *
     * @return void
     * @throws LoaderException
     */
    protected static function includeModules(): void
    {
        Loader::includeModule('iblock');
        Loader::includeModule('highloadblock');
    }

    /**
     * Возвращает массив  "constant_name" => 'value'
     * можно получить константы только по определенному типу сущности, передав $identityType
     *
     * @param string $filterByIdentityType
     * @param bool $dontUseCache
     * @return array
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getAll(string $filterByIdentityType = '', bool $dontUseCache = false): array
    {
        static::includeModules();

        $result = [];

        foreach (static::getValues($dontUseCache) as $identifierType => $identifiers) {
            foreach ($identifiers as $code => $value) {
                $result[$identifierType][$code . static::IDENTIFIER_TYPE_POSTFIXES[$identifierType]] = $value;
            }
        }

        return $filterByIdentityType ? $result[$filterByIdentityType] : $result;
    }

}
