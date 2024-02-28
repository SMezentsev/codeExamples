<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 16.06.16
 * Time: 15:20
 */

namespace app\modules\itrack\models;


use app\modules\itrack\components\boxy\Helper;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class ProductSort extends Model
{
    public $nomenclature_uid;
    public $series;
    public $cdate;
    public $expdate;
    public $code1c;
    
    public function rules()
    {
        return [
            [['nomenclature_uid', 'series', 'cdate', 'expdate', 'code1c'], 'safe'],
        ];
    }
    
    // Номенклатура	Серия	Дата выпуска	Срок годности до
    public function search($params)
    {
        /** @var \yii\db\ActiveQuery $query */
        $query = Product::find();
        
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
                ],
            ],
        ], $pag));
        
        if (!($this->load($params, '') && $this->validate())) {
            return $dataProvider;
        }
        $query->leftJoin('nomenclature', 'nomenclature.id = nomenclature_uid');
        
        $query->andFilterWhere(['in', 'nomenclature_uid', $this->nomenclature_uid]);
        $query->andFilterWhere(['in', 'series', $this->series]);
        $query->andFilterWhere(['ilike', 'cdate', $this->cdate]);
        $query->andFilterWhere(['ilike', 'expdate', $this->expdate]);
//        $query->leftJoin('nomenclature','nomenclature.id = product.nomenclature_uid');
        $query->andFilterWhere(['in', 'nomenclature.code1c', $this->code1c]);
        $query->with('nomenclature');
        $query->with('nomenclature.manufacturer');
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
}