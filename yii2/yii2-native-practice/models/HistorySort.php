<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 03/02/16
 * Time: 12:01
 */

namespace app\modules\itrack\models;

use yii\data\SqlDataProvider;

class HistorySort extends History
{
//    public $gtin;
//    public $name;
//    public $series;
//    public $release_date;
//    public $code;
//    public $operation;
//    public $created_at;
//    public $dest_consigne;
//    public $dest_address;
    
    public function rules()
    {
        return [
            [['gtin', 'name', 'series', 'release_date', 'code', 'operation_uid', 'created_at', 'dest_consigne', 'dest_address'], 'safe'],
        ];
    }
    
    public function attributes()
    {
        return ['gtin', 'name', 'series', 'release_date', 'code', 'operation_uid', 'created_at', 'dest_consigne', 'dest_address'];
    }
    
    public function searchManufacturers($params)
    {
        $query = History::manufacturers();
        
        $query->andWhere(['>=', 'history.created_at', $params['dateStart'] . " 00:00:00"]);
        $query->andWhere(['<=', 'history.created_at', $params['dateEnd'] . " 23:59:59"])
            ->andWhere(['in', 'nomenclature.manufacturer_uid', $params['manufacturer']]);
        
        $dataProvider = new SqlDataProvider([
            'sql' => $query->createCommand()->getRawSql(),
        ]);

//        unset($dataProvider->sort->attributes['data_types'], $dataProvider->sort->attributes['use_types']);
//        $dataProvider->sort->attributes['type.name'] = [
//            'asc' => ['user_devices_type_uid' => 'ASC'],
//            'desc' => ['user_devices_type_uid' => 'DESC'],
//        ];
//        
//        if (!($this->load($params, '') && $this->validate())) {
//            return $dataProvider;
//        }
        
        if (isset($params["gtin"])) {
            $query->andFilterWhere(['in', 'nomenclature.gtin', $params['gtin']]);
        }
        if (isset($params["name"])) {
            $query->andFilterWhere(['ilike', 'nomenclature.name', $params["name"]]);
        }
        if (isset($params["series"])) {
            $query->andFilterWhere(['in', 'product.series', $params['series']]);
        }
        if (isset($params["code"])) {
            $query->andFilterWhere(['in', 'codes.code', $params['code']]);
        }
        if (isset($params["release_date"])) {
            $query->andFilterWhere(['ilike', 'product.cdate', $params['release_date']]);
        }
//            ->andFilterWhere(['=', 'history.operation_uid', $this->getAttribute('operation_uid')]);
        
        //Helper::sortAndFilterQuery($query);
        
        $q = "SELECT ROW_NUMBER() OVER() as i, * FROM (" . $query->createCommand()->getRawSql() . ") t";
        
        $count = \Yii::$app->db->createCommand("SELECT COUNT(*) FROM (" . $query->createCommand()->getRawSql() . ") t")->queryScalar();
        
        $dataProvider->sql = $q;
        $dataProvider->totalCount = $count;
        
        return $dataProvider;
    }
}