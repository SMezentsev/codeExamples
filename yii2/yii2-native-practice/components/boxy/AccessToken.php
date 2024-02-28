<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 13.04.15
 * Time: 18:09
 */

namespace app\modules\itrack\components\boxy;


/**
 * Class AccessToken
 *
 * ``` json
 * {
 *     "id": <token_id>,
 *     "user_uid": <user_uid>,
 *     "r": 1,
 *     "w": 1,
 *     "ip": "127.0.0.1",
 *     "la": time(),
 *     "d": {
 *         "device": <device>,
 *         "login": <user_name>
 *     },
 *     "ttl": <lifetime>
 * }
 * ```
 *
 * @package app\modules\core\models\users
 *
 * @property $id
 * @property $user_uid
 * @property $r
 * @property $w
 * @property $ip
 * @property $la
 * @property $d
 * @property $ttl
 *
 * Magic property
 * @property $users
 */
class AccessToken extends \yii\redis\ActiveRecord
{
    /**
     * Генерация токена для пользователя
     *
     * @param User $user
     *
     * @return false|AccessToken
     */
    public static function generateForUser(User $user)
    {
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
            'ttl'      => 3600 * 24 * 7,
        ]);
        
        return (true === $accessToken->save()) ? $accessToken : false;
    }
    
    public function init()
    {
        parent::init();
        
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            $event->sender->id = self::buildKey((new \yii\base\Security)->generateRandomString());
        });
    }
    
    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return ['id', 'user_uid', 'r', 'w', 'ip', 'la', 'd', 'ttl'];
    }
    
    public function fields()
    {
        return [
            'id',
            'user_uid',
            'r',
            'w',
            'ip',
            'la',
            'd' => function ($accessToken) {
                return (is_string($accessToken->d)) ? json_decode($accessToken->d) : $accessToken->d;
            },
            'ttl',
        ];
    }
    
    /**
     * @return User|null
     */
    public function getUser()
    {
        $userClass = \Yii::$app->user->identityClass;
        
        return $userClass::findIdentity($this->user_uid);
    }
    
    public function beforeSave($insert)
    {
        if (is_array($this->d)) {
            $this->d = json_encode($this->d);
        }
        
        return parent::beforeSave($insert);
    }
}