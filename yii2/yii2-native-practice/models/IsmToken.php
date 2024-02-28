<?php

namespace app\modules\itrack\models;

/**
 * This is the model class for table "ism_token".
 *
 * @property int    $id
 * @property string $token
 * @property string $created
 * @property int    $valid_till
 * @property string $connection_name
 *
 */
class IsmToken extends \yii\db\ActiveRecord
{
    
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ism_token';
    }
    
    public static function getLastToken($connectionName)
    {
        return IsmToken::find()->where(['connection_name' => $connectionName])->orderBy(['valid_till' => SORT_DESC])->limit(1)->one();
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'token'           => 'Token',
            'created'         => 'Created',
            'valid_till'      => 'Valid till',
            'connection_name' => 'Connection name',
        ];
    }
}
