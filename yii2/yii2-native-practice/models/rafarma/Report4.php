<?php

namespace app\modules\itrack\models\rafarma;

use Yii;

class Report4 extends \app\modules\itrack\models\Extdata
{
    
    const TYPEOF = 'reportRafarma4';
    public $series;
    public $grpcode;
    public $weight;
    public $fio;
    public $boxNumber;
    public $companyPrefix;
    public $dateOfCreation;
    public $dateOfExpiration;
    public $orderId;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'extdata';
    }
    
    static function find()
    {
        $q = parent::find();
        
        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $q->andWhere(['typeof' => self::TYPEOF]);
        } else {
            $q->from(['extdata' => "(SELECT * FROM _get_extdata('typeof = ''" . self::TYPEOF . "'''))"]);
        }
        
        return $q;
    }
    
    static function addMulti($data)
    {
        $added = [];
        foreach ($data as $element) {
            //var_dump($element);$die;
            $e = new self;
            if ($e->load($element, '')) {
                if ($e->save()) {
                    $e->refresh();
                    $added[] = $e;
                }
            }
        }
        
        return ['data' => $added];
    }
    
    static function report($series)
    {
        $query = self::find();
        $query->andWhere(['typeof' => self::TYPEOF]);
        if (!empty($series)) {
            $query->andWhere(['params1' => $series]);
        }
        $query->orderBy(['created_at' => SORT_ASC]);
        
        return $query;
    }
    
    public function scenarios()
    {
        return [
            'default' => ['series', 'grpcode', 'weight', 'fio', 'boxNumber', 'typeof', 'companyPrefix', 'dateOfCreation', 'dateOfExpiration', 'orderId'],
        ];
    }
    
    /*
     * Создание множества моделей
     */

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['series', 'typeof'], 'required'],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $query,
            'pagination' => false,
        ]);
        
        if (!empty($params["series"])) {
            $query->andFilterWhere(['params1' => $params["series"]]);
        }
        if (!empty($params["boxNumber"])) {
            $query->andFilterWhere(['data4' => $params["boxNumber"]]);
        }
        if (!empty($params["object_uid"])) {
            $query->andFilterWhere(['object_uid' => $params["object_uid"]]);
        }
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        $query->orderBy(['created_at' => SORT_ASC]);
        
        return $dataProvider;
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->created_at = 'NOW()';
            $data = [];
            $data[] = $event->sender->data1 = $event->sender->grpcode ?? '';
            $data[] = $event->sender->data2 = $event->sender->weight ?? '';
            $data[] = $event->sender->data3 = $event->sender->fio ?? '';
            $data[] = $event->sender->data4 = $event->sender->boxNumber ?? '';
            $data[] = '';
            $data[] = $event->sender->data6 = $event->sender->companyPrefix ?? '';
            $data[] = $event->sender->data7 = $event->sender->dateOfCreation ?? '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 = $event->sender->series ?? '';
            $params[] = $event->sender->params2 = $event->sender->dateOfExpiration ?? '';
            $params[] = $event->sender->params3 = $event->sender->orderId ?? '';
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($params);
            $event->sender->typeof = self::TYPEOF;
        });
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            $user = Yii::$app->user->getIdentity();
            $event->sender->object_uid = $user->object_uid;
            $event->sender->typeof = self::TYPEOF;
        });
    }
    
    public function fields()
    {
        return [
            'series'           => function () {
                return $this->params1;
            },
            'dateOfExpiration' => function () {
                return $this->params2;
            },
            'orderId'          => function () {
                return $this->params3;
            },
            'grpcode'          => function () {
                return $this->data1;
            },
            'weight'           => function () {
                return $this->data2;
            },
            'fio'              => function () {
                return $this->data3;
            },
            'boxNumber'        => function () {
                return $this->data4;
            },
            'companyPrefix'    => function () {
                return $this->data6;
            },
            'dateOfCreation'   => function () {
                return $this->data7;
            },
            'typeof'           => function () {
                return $this->typeof;
            },
            'created_by'       => function () {
                return $this->created_by;
            },
            'created_at'       => function () {
                return $this->created_at;
            },
            'object_uid'       => function () {
                return $this->object_uid;
            },
            'object'           => function () {
                return $this->object;
            },
            'createdBy'        => function () {
                return $this->createdBy;
            },
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(\app\modules\itrack\models\Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(\app\modules\itrack\models\User::class, ['id' => 'created_by']);
    }
    
}
