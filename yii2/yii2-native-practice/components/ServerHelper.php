<?php


namespace app\modules\itrack\components;

use Yii;

/**
 * Class ServerHelper
 * @package app\modules\itrack\components
 */
class ServerHelper
{
    /**
     * проверка тестовый ли сервер
     * @return bool
     */
    public static function isTestServer(): bool
    {
        $serverName = self::getServerName();
        return in_array($serverName, Yii::$app->params['testServers']);
    }

    /**
     * получить имя сервера
     * @return string|null
     */
    public static function getServerName(): ?string
    {
        $serverName = getenv('SERVER_NAME', true) ?? getenv('SERVER_NAME');
        return !empty($serverName) ? $serverName : null;
    }
}