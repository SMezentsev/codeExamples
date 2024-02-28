<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 09.06.16
 * Time: 16:15
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\AccessToken as AT;
use yii\web\UnauthorizedHttpException;

/**
 * @OA\Schema(schema="app_modules_itrack_models_AccessToken",
 *      type="object",
 *      properties={
 *          @OA\Property(property="id", type="string", description="id", example="fc074cc1b9d4335151ca656ffd6e43d1"),
 *          @OA\Property(property="user_uid", type="integer", description="user_uid", example=1),
 *          @OA\Property(property="r", type="integer", description="r", example=1),
 *          @OA\Property(property="w", type="integer", description="w", example=1),
 *          @OA\Property(property="ip", type="string", description="ip", example=""),
 *          @OA\Property(property="la", type="integer", description="la", example=1583926533),
 *          @OA\Property(property="d", type="object",
 *               @OA\Property(property="device", type="string", description="device", example=""),
 *               @OA\Property(property="login", type="string", description="login", example="test"),
 *          ),
 *          @OA\Property(property="ttl", type="string", description="ttl", example=604800),
 *      }
 * )
 */
class AccessToken extends AT
{
    /**
     * Генерация токена для пользователя
     *
     * @param \app\modules\itrack\components\boxy\User $user
     *
     * @return AccessToken|false
     */
    public static function generateForUser(\app\modules\itrack\components\boxy\User $user)
    {
        // Если у пользователя стоит право на вход под одним девайсом, то удаляем все другие token
        
        $sessionTimeout = (int)\Yii::$app->params['sessionTimeout'];
        
        if (isset($user->permissions['oneAuth'])) {
            // Remote all auth token with user
            self::deleteAll(['user_uid' => $user->id]);
            
            $sessionTimeout = (int)\Yii::$app->params['sessionTimeoutOneAuth'];
        }
        
        $accessToken = new static([
            'user_uid' => $user->getId(),
            'r'        => 1,
            'w'        => 1,
            'ip'       => \Yii::$app->request->getUserIP(),
            'la'       => time(),
            'd'        => [
                'device' => '',
                'login'  => $user->login,
            ],
            'ttl'      => $sessionTimeout,
        ]);
        
        if (!$accessToken->save()) {
            return false;
        }
        
        self::getDb()->executeCommand("EXPIRE", [static::keyPrefix() . ':a:' . static::buildKey($accessToken->id), $sessionTimeout]);
        
        return $accessToken;
    }
    
    /**
     * @return User|null
     * @throws UnauthorizedHttpException
     */
    public function getUser()
    {
        $userClass = \Yii::$app->user->identityClass;
        $user = $userClass::findIdentity($this->user_uid);
        if (empty($user)) {
            return null;
        }
        if (!$user->active) {
            throw new UnauthorizedHttpException("Пользователь заблокирован. Обратитесь к администраторам.");
        }
        
        return $user;
    }
    
    public function afterFind()
    {
        self::getDb()->executeCommand("EXPIRE", [static::keyPrefix() . ':a:' . static::buildKey($this->id), $this->ttl]);
    }
}