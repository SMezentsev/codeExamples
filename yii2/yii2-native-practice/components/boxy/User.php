<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 13.04.15
 * Time: 17:35
 */

namespace app\modules\itrack\components\boxy;

abstract class User extends \app\modules\itrack\components\boxy\ActiveRecord implements \yii\web\IdentityInterface
{
    
    public $accessToken;
    
    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }
    
    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $accessTokenClass = \Yii::$app->user->accessTokenClass;
        
        /** @var AccessToken $accessToken */
        $accessToken = $accessTokenClass::findOne(['id' => $token]);
        
        if (!$accessToken) {
            return null;
        }
        
        $user = $accessToken->getUser();
        if (!$user) {
            return null;
        }
        $user->accessToken = $token;
        
        return $user;
    }
    
    /**
     * Login user by login, email or phone
     *
     * @param $login
     *
     * @return static|null
     */
    public static function findByLogin($login)
    {
        // try login as login, email or phone
        $userQuery = static::find();
        $userQuery->andWhere(['login' => $login]);
        
        return $userQuery->one();
    }
    
    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }
    
    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        throw new \BadMethodCallException;
    }
    
    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        throw new \BadMethodCallException;
    }
    
    /**
     * Валидация пароля
     *
     * @param $password
     *
     * @return bool
     */
    abstract public function validatePassword($password);
}