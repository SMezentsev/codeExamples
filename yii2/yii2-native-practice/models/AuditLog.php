<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use yii\base\ModelEvent;

/**
 * Class AuditLog
 *  Журналирование действий пользоватея
 *
 * Методы:
 *  - создание
 *
 * @property integer    $id              - идентификатор
 * @property string     $created_at      - Дата создания
 * @property string     $updated_at      - Дата изменения/обнвления статуса
 * @property string     $generation_uid  - ссылка на генерацию кодов
 * @property integer    $state           — статус
 *
 * @property Generation $generation
 */
class AuditLog extends ActiveRecord
{
    
    
    static $vars = [
        'per-page',
        'access-token',
        'sort',
        'expand',
        'rand',
        'full',
    ];
    
    static $skip = [
        'destruction_org'    => true,
        'object'             => true,
        'destructor'         => true,
        'destructionMethod'  => true,
        'type'               => true,
        'types'              => true,
        'newobject'          => true,
        'withdrawalType'     => true,
        'manufacturer'       => true,
        'contractType'       => true,
        'sourceType'         => true,
        'controlSamplesType' => true,
        'confirmDoc'         => true,
    ];
    static $dict = [
        'id'                         => 'Идентификатор',
        'doc_num'                    => 'Номер документа',
        'doc_date'                   => 'Дата документа',
        'object_uid'                 => 'Идентификатор объекта',
        'contract_type'              => 'Тип договора при реализации',
        'newobject_uid'              => 'Идентификатор объекта получателя',
        'operation_date'             => 'Дата операции',
        'gtins'                      => 'Массив GTIN',
        'receiver_address_aoguid'    => 'Адрес получателя',
        'receiver_address_flat'      => 'Адрес получателя - квартира',
        'receiver_address_houseguid' => 'Адрес получателя - дом',
        'receiver_ul_inn'            => 'ИНН получателя юр.лицо',
        'receiver_ul_kpp'            => 'КПП получателя',
        'subject_id'                 => 'Идентификатор отправителя',
        'control_samples_type'       => 'Вид отбора образцов',
        'counterparty_id'            => 'Идентификатор контрагента',
        'destruction'                => 'Идентификатор организации утилизатора',
        'destruction_method'         => 'Метод утилизации',
        'act_number'                 => 'Акт передачи - номер',
        'act_date'                   => 'Акт передачи - дата',
        'withdrawal_reason'          => 'Причина изъятия',
        'withdrawal_type'            => 'Тип вывода из оборота',
        'receiver_id'                => 'Идентификатор получателя',
        'owner_id'                   => 'Идентификатор собственника',
        'order_type'                 => 'Тип производственного заказа',
        'series_number'              => 'Серия',
        'expiration_date'            => 'Дата срока годности',
        'gtin'                       => 'GTIN',
        'tnved'                      => 'ТНВЭД',
        'saveOnly'                   => 'Только сохранение',
        'manufacturer_uid'           => 'Идентификатор проиозводителя',
        'source'                     => 'Вид источника финансирования',
        'confirm_doc'                => 'Вид документа подтверждения',
    ];
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    static function canSkip($k)
    {
        if (isset(self::$skip[$k])) {
            return self::$skip[$k];
        }
        
        return false;
    }
    
    static function trans($k)
    {
        if (isset(self::$dict[$k])) {
            return self::$dict[$k];
        }
        
        return $k;
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'audit_log';
    }
    
    static function find()
    {
        $query = parent::find();
        $query->leftJoin('audit_operations', 'operation_uid = audit_operations.id')
            ->select(['audit_log.*', 'name_rus', 'name_eng']);
        
        return $query;
    }
    
    static function Audit($operation_uid, $data = '', $params = null)
    {
        foreach (self::$vars as $v) {
            if (isset($params[$v])) {
                unset($params[$v]);
            }
        }
        $request = \Yii::$app->getRequest();
        if (!isset($request->isOptions) || $request->isOptions) {
            return;
        }
        
        $ip = $request instanceof \yii\web\Request ? $request->getUserIP() : '-';
        $userid = @\Yii::$app->user ? (\Yii::$app->user->isGuest ? null : \Yii::$app->user->identity->id) : null;
        if (isset($params['userid'])) {
            $userid = $params['userid'];
        }
        
        $s = new self;
        if (is_array($params)) {
            $params = serialize($params);
        }
        if ($s->load(['operation_uid' => $operation_uid, 'user_uid' => $userid, 'data' => $data, 'params' => $params, 'ip' => $ip], '')) {
            $s->save(false);
            //таблица партицирвоаннная - ретурнинга нет - сохраенение фальш
//            if(!$s->save())
//                \Yii::getLogger()->log('Ошибка сохранения логов ' . print_r($s->getErrors(), true), \yii\log\Logger::LEVEL_ERROR);
        } else {
            \Yii::getLogger()->log('Ошибка валидации ' . print_r($s->getErrors(), true), \yii\log\Logger::LEVEL_ERROR);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['user_uid', 'operation_uid'], 'integer'],
            [['params', 'data', 'ip'], 'string'],
            [['operation_uid'], 'required'],
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
    
    public function attributes()
    {
        return array_merge(parent::attributes(), ['name_rus', 'name_eng']);
    }
    
    public function fields()
    {
        return array_merge(parent::fields(), ['name_rus',
            'name_eng',
            'params' => function () {
                $ar = unserialize($this->params);
                
                return $ar;
            },
        ]);
    }
    
    public function extraFields()
    {
        return ['user'];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
        ]);
        if (empty($params["dateStart"])) {
            $params["dateStart"] = date("Y-m-d 00:00:00");
        }
        if (empty($params["dateEnd"])) {
            $params["dateEnd"] = date("Y-m-d 23:59:59");
        }
        if (!empty($params["user_uid"])) {
            $query->andWhere(['=', 'user_uid', $params["user_uid"]]);
        }
        if (!empty($params["operation_uid"])) {
            $query->andWhere(['=', 'operation_uid', $params["operation_uid"]]);
        }
        $query->andWhere(['>=', 'created_at', $params["dateStart"]]);
        $query->andWhere(['<=', 'created_at', $params["dateEnd"]]);
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        $query->with('user');
        
        return $dataProvider;
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_uid']);
    }
    
}
