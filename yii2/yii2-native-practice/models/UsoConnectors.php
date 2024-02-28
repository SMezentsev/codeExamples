<?php

namespace app\modules\itrack\models;

use Yii;
use app\modules\itrack\components\AuditBehavior;

/**
 * This is the model class for table "uso_connectors".
 *
 * @property int        $id
 * @property string     $name
 * @property string     $url
 * @property string     $client_secret
 * @property string     $client_id
 * @property string     $user_id
 * @property string     $token_key
 * @property string     $token_pass
 * @property string     $sign_remote_ssh
 * @property string     $token_alg
 * @property string     $cryptopro_path
 * @property string     $auth_type                тип авторизации SIGNED_CODE | PASSWORD
 * @property string     $password                 пароль для авторизации по типу PASSWORD
 * @property int        $sign_remote_port
 *
 * @property Facility[] $objects
 */
class UsoConnectors extends \yii\db\ActiveRecord
{
    const CONNECTOR_TYPE_IN = 'in';
    const CONNECTOR_TYPE_OUT = 'out';
    
    
    static $auditOperation = \app\modules\itrack\models\AuditOperation::OP_CONNECTORS;
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'uso_connectors';
    }
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'url'], 'required'],
            [['name'], 'unique', 'message' => 'Подключение к МДЛП с таким наименованием уже создано'],
            [['name', 'url', 'client_secret', 'client_id', 'user_id', 'token_key', 'token_pass', 'sign_remote_ssh', 'cryptopro_path', 'token_alg', 'auth_type', 'password'], 'string'],
            [['sign_remote_port'], 'default', 'value' => null],
            [['auth_type'], 'default', 'value' => 'SIGNED_CODE'],
            [['auth_type'], 'in', 'range' => ['SIGNED_CODE', 'PASSWORD']],
            [['sign_remote_port'], 'integer'],
        ];
    }
    
    public function fields()
    {
        return array_merge(parent::fields(), [
            'uid' => 'id',
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'               => Yii::t('app', 'ID'),
            'name'             => Yii::t('app', 'Наименование подключения'),
            'url'              => Yii::t('app', 'Url адрес сервиса МДЛП'),
            'client_secret'    => Yii::t('app', 'Секретный ключ'),
            'client_id'        => Yii::t('app', 'Идентификатор клиента'),
            'user_id'          => Yii::t('app', 'Идентификатор пользователя'),
            'token_key'        => Yii::t('app', 'Ключ пользователя'),
            'token_pass'       => Yii::t('app', 'Пароль для ключа пользователя'),
            'sign_remote_ssh'  => Yii::t('app', 'Порт подписывающей машины ssh'),
            'sign_remote_port' => Yii::t('app', 'Порт подписывающей машины'),
            'token_alg'        => Yii::t('app', 'Алгоритм формирования подписи в криптопро'),
            'cryptopro_path'   => Yii::t('app', 'Путь до криптопро'),
            'auth_type'        => Yii::t('app', 'Тип авторизации'),
            'password'         => Yii::t('app', 'Пароль'),
        ];
    }
    
    public function delete()
    {
        if (count($this->objects)) {
            throw new \yii\web\BadRequestHttpException('Удаление невозможно, так как данное соединение активно на объектах');
        }
        
        return parent::delete();
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObjects()
    {
        return $this->hasMany(\app\modules\itrack\models\Facility::class, ['uso_uid' => 'id']);
    }
}
