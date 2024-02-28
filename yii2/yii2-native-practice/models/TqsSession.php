<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use yii\base\ModelEvent;

/**
 * Class TqsSession
 *  Сущность активные сессии для OCS оборудования
 *
 * Методы:
 *  - создание
 *
 * @property integer    $id              - идентификатор
 * @property integer    $equip_uid       - ссылка на оборудование
 * @property string     $created_at      - Дата создания
 * @property integer    $created_by      - Дата создания
 * @property string     $updated_at      - Дата изменения/обнвления статуса
 * @property string     $generation_uid  - ссылка на генерацию кодов
 * @property integer    $state           — статус
 * @property integer    $nerrors         — кол-во ошибок
 *
 * @property Generation $generation
 */
class TqsSession extends ActiveRecord
{
    
    static $auditOperation = AuditOperation::OP_EQUIP;
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tqs_sessions';
    }
    
    static function checkActive()
    {
        $sessions = self::find()->andWhere(['state' => 0])->andWhere(['<=', 'updated_at', new \yii\db\Expression("now() - interval '100 sec'")])->all();
        foreach ($sessions as $session) {
            if ($session->equip->data == "2") {
                $params = [];
                $params["id"] = $session->id;
                $params["order-name"] = $session->generation->number;
                $params["generation_uid"] = $session->generation->id;
                $params["ocs_uid"] = "";
                $params["tqs_session"] = $session->id;
                $params["equip_uid"] = $session->equip_uid;
                $params["created_by"] = $session->created_by;
                $fns = FnsOcs::createTQSoutput('get-order-status-request', $params);
                $params["sn-status"] = 3;
                $fns = FnsOcs::createTQSoutput('query-serial-numbers-request', $params);
                $session->updated_at = new \yii\db\Expression('now()');
                $session->save(false);
            }
        }
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at', 'generation_uid'], 'safe'],
            [['state', 'equip_uid', 'nerrors'], 'integer'],
            [['generation_uid', 'state', 'equip_uid', 'created_by'], 'required'],
            ['generation_uid', 'exist', 'targetClass' => Generation::class, 'targetAttribute' => 'id'],
        ];
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->created_at = new \yii\db\Expression('now()');
        });
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGeneration()
    {
        return $this->hasOne(Generation::class, ['id' => 'generation_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEquip()
    {
        return $this->hasOne(Equip::class, ['id' => 'equip_uid']);
    }
}
