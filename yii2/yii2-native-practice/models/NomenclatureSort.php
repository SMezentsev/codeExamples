<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 16.06.16
 * Time: 16:05
 */

namespace app\modules\itrack\models;


use app\modules\itrack\components\boxy\Helper;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class NomenclatureSort extends Model
{
    public $name;
    public $manufacturer_uid;
    public $gtin;
    public $ean13;
    public $code1c;
    public $tnved;
    public $full;
    
    public function rules()
    {
        return [
            [['name', 'gtin', 'ean13', 'manufacturer_uid', 'code1c', 'tnved', 'full'], 'safe'],
            [['tnved'], 'match', 'pattern' => '#^\d{4}$#'],
            [['ean13'], 'match', 'pattern' => '#^\d{13}$#'],
        ];
    }
    
    public function search($params)
    {
        $query = Nomenclature::find();
        
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
            throw new \yii\web\BadRequestHttpException(implode(" ", array_map(function ($v) {
                return implode(" ", $v);
            }, $this->errors)));
            
            return null;
        }
        
        $query->andFilterWhere(['ilike', 'name', $this->name]);
        $query->andFilterWhere(['in', 'code1c', $this->code1c]);
        $query->andFilterWhere(['ilike', 'tnved', $this->tnved]);
        $query->andFilterWhere(['in', 'manufacturer_uid', $this->manufacturer_uid]);
        $query->andFilterWhere(['ilike', 'gtin', $this->gtin]);
        $query->andFilterWhere(['in', 'ean13', $this->ean13]);
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
}