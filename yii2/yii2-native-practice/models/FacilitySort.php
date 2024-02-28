<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 25/02/16
 * Time: 12:51
 */

namespace app\modules\itrack\models;


use app\modules\itrack\components\boxy\Helper;
use yii\data\ActiveDataProvider;

class FacilitySort extends Facility
{
    static function find()
    {
        $q = parent::find();
        $q->where('1=1');
        
        return $q;
    }
    
    public function search($params)
    {
        /** @var \yii\db\ActiveQuery $query */
        $query = Facility::find();
        if (isset($params["external"]) && $params["external"]) {
            $query->where(new \yii\db\Expression('(external = true or external = false)'));
        }
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        if (!($this->load($params, ''))) {
            return $dataProvider;
        }
        
        $query->andFilterWhere(['ilike', 'name', $this->name]);
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
}