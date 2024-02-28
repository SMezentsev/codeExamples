<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\AuditBehavior;
use Yii;

/**
 * This is the model class for table "extdata".
 *
 * @property string  $id
 * @property string  $created_at
 * @property int     $created_by
 * @property int     $object_uid
 * @property string  $params1
 * @property string  $params2
 * @property string  $params3
 * @property string  $params
 * @property string  $data1
 * @property string  $data2
 * @property string  $data3
 * @property string  $data4
 * @property string  $data5
 * @property string  $data6
 * @property string  $data7
 * @property string  $data
 * @property string  $typeof
 *
 * @property Facility $objectU
 * @property User   $createdBy
 */
class Extdata extends \yii\db\ActiveRecord
{
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'extdata';
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
     * @inheritdoc
     */
    public function rules()
    {
        return [
//            [['created_at'], 'safe'],
            [['created_by', 'typeof'], 'required'],
            [['created_by', 'object_uid'], 'default', 'value' => null],
            [['created_by', 'object_uid'], 'integer'],
            [['params1', 'params2', 'params3', 'params', 'data1', 'data2', 'data3', 'data4', 'data5', 'data6', 'data7', 'data', 'typeof'], 'string'],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['created_by' => 'id']],
        ];
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            if (!empty($event->sender->created_at)) {
                $event->sender->created_at = \Yii::$app->formatter->asDatetime($event->sender->created_at, 'php:Y-m-d H:i:sP');
            } else {
                $event->sender->created_at = 'now()';
            }
            $data = [];
            $data[] = $event->sender->data1 ?? '';
            $data[] = $event->sender->data2 ?? '';
            $data[] = $event->sender->data3 ?? '';
            $data[] = $event->sender->data4 ?? '';
            $data[] = $event->sender->data5 ?? '';
            $data[] = $event->sender->data6 ?? '';
            $data[] = $event->sender->data7 ?? '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 ?? '';
            $params[] = $event->sender->params2 ?? '';
            $params[] = $event->sender->params3 ?? '';
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($params);
        });
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            $user = Yii::$app->user->getIdentity();
            $event->sender->object_uid = $user->object_uid;
        });
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'object_uid' => 'Object Uid',
            'params1'    => 'Params1',
            'params2'    => 'Params2',
            'params3'    => 'Params3',
            'params'     => 'Params',
            'data1'      => 'Data1',
            'data2'      => 'Data2',
            'data3'      => 'Data3',
            'data4'      => 'Data4',
            'data5'      => 'Data5',
            'data6'      => 'Data6',
            'data7'      => 'Data7',
            'data'       => 'Data',
            'typeof'     => 'Typeof',
        ];
    }
    
    public function search($params)
    {
        /** @var \yii\db\ActiveQuery $query */
        $query = self::find();
        
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
        ]);
        
        if (!($this->load($params, ''))) {
            return $dataProvider;
        }
        
        //$query->andFilterWhere(['ilike', 'name', $this->name]);
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
    
}
