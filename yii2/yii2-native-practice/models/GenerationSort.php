<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 02/03/16
 * Time: 16:35
 */

namespace app\modules\itrack\models;


use app\modules\itrack\components\boxy\Helper;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class GenerationSort extends Model
{
    public $object_uid;
    public $start_time;
    public $finish_time;
    public $codetype_uid;
    public $product_uid;
    public $number;
    public $status_uid;
    public $equip_uid;
    
    public function rules()
    {
        return [
            [['object_uid', 'start_time', 'finish_time', 'codetype_uid', 'product_uid', 'number', 'status_uid', 'equip_uid'], 'safe'],
        ];
    }
    
    public function search($params)
    {
        /** @var ActiveQuery $query */
        $query = Generation::find();
        $query->andWhere([
            'is_rezerv'  => false,
            'deleted_at' => null,
        ]);

//        $query->andWhere(['!=', 'comment', 'Дозаказ']);
        if (isset($params["noauto"])) {
            $query->andWhere(['!=', 'comment', 'Автоматическая генерация']);
        }
        if (isset($params["full"]) && $params["full"]) {
            $pag = ['pagination' => false];
        } else {
            $pag = [];
        }
        
        $dataProvider = new ActiveDataProvider(array_merge([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                    'num'        => SORT_DESC,
                ],
            ],
        ], $pag));
        
        
        if (!($this->load($params, '') && $this->validate())) {
            return null;
        }
        
        $query->andFilterWhere(['in', 'object_uid', $this->object_uid]);
        $query->andFilterWhere(['between', 'created_at', $this->start_time, $this->finish_time]);
        $query->andFilterWhere(['>=', 'created_at', $this->start_time]);
        $query->andFilterWhere(['<', 'created_at', $this->finish_time]);
        $query->andFilterWhere(['in', 'codetype_uid', $this->codetype_uid]);
        $query->andFilterWhere(['in', 'product_uid', $this->product_uid]);
        $query->andFilterWhere(['in', 'status_uid', $this->status_uid]);
        $query->andFilterWhere(['in', 'equip_uid', $this->equip_uid]);

        if(!empty($this->number))
            $query->andWhere(new \yii\db\Expression("generations.object_uid || '/' || generations.num = '". pg_escape_string($this->number)."'"));

        if (\Yii::$app->request->get('hide-closed') == true) {
            $query->andWhere(['is_closed' => false]);
        }

        $query->with('object');
        $query->with('product');
        if (SERVER_RULE == SERVER_RULE_RF) {
            $query->with('status');
        }
        $query->with('product.nomenclature');
        $query->with('product.nomenclature.manufacturer');
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
    /**
     * Поиск резерва
     *
     * @param $params
     *
     * @return ActiveDataProvider
     */
    public function searchReserve($params)
    {
        $query = Generation::find();
        $query->andWhere(['is_rezerv' => true]);
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ],
            ],
        ]);
        
        
        if (!($this->load($params, '') && $this->validate())) {
            return $dataProvider;
        }
        
        $query->andFilterWhere(['=', 'object_uid', $this->object_uid]);
//        $query->andFilterWhere(['between', 'created_at', $this->start_time, $this->finish_time]);
        $query->andFilterWhere(['>=', 'created_at', $this->start_time]);
        $query->andFilterWhere(['<', 'created_at', $this->finish_time]);
        $query->andFilterWhere(['=', 'codetype_uid', $this->codetype_uid]);
        $query->with('object');
        $query->with('product');
        $query->with('product.nomenclature');
        $query->with('product.nomenclature.manufacturer');
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
}