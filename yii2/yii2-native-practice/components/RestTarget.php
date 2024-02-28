<?php

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 01.06.15
 * Time: 9:00
 */

namespace app\modules\itrack\components;

class RestTarget extends \yii\log\FileTarget
{
    
    /**
     * @inheritdoc
     */
    public function getMessagePrefix($message)
    {
        if ($this->prefix !== null) {
            return call_user_func($this->prefix, $message);
        }
        
        if (\Yii::$app === null) {
            return '';
        }
        
        $request = \Yii::$app->getRequest();
        $ip = $request instanceof \yii\web\Request ? $request->getUserIP() : '-';
        
        /* @var $user \yii\web\User */
        $user = \Yii::$app->has('user', true) ? \Yii::$app->get('user') : null;
        if ($user && ($identity = $user->getIdentity(false))) {
            $userID = $identity->getId();
        } else {
            $userID = '-';
        }
        
        $accessToken = (isset($_GET['access-token'])) ? $_GET['access-token'] : null;
        $authorization = $request instanceof \yii\web\Request && isset($request->getHeaders()['Authorization']) ? $request->getHeaders()['Authorization'] : null;
        
        $sessionID = $authorization ? $authorization : ($accessToken ? $accessToken : '-');
        
        return "[$ip][$userID][$sessionID]";
    }
    
    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        $context = [];
        $method = \Yii::$app->getRequest()->getMethod() . ' ' . \Yii::$app->getRequest()->getUrl();
        foreach ($this->logVars as $name) {
            if ($name == '_REST') {
                $context[] = $method;
                $context[] = "\${$name} = " . \yii\helpers\VarDumper::dumpAsString(\Yii::$app->getRequest()->getBodyParams());
                if ((defined('YII_LOG_REST_RESPONSE') && YII_LOG_REST_RESPONSE === true) || \Yii::$app->getResponse()->getStatusCode() >= 300) {
                    $context[] = "\$Response = " . \Yii::$app->getResponse()->getStatusCode() . ' ' . \yii\helpers\VarDumper::dumpAsString(\Yii::$app->getResponse()->data);
                }
            }
        }
        
        return implode("\n", $context) . "\n" . parent::getContextMessage() . "\n";
    }
    
}
