<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 24/02/16
 * Time: 16:36
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\Helper;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Class UserSort
 *
 * @package app\modules\itrack\models
 */
class EquipSort extends Equip
{
    
    public function rules()
    {
        return [
            [['object_uid', 'login', 'fio', 'email'], 'safe'],
        ];
    }
    
    public function attributes()
    {
        return ['object_uid', 'login', 'fio', 'email'];
    }
    
    public function searchUser($params)
    {
        /** @var ActiveQuery $query */
        $query = Equip::find();
        
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
        
        $query->andFilterWhere(['in', 'object_uid', $this->object_uid])
            ->andFilterWhere(['ilike', 'login', $this->login]);
        $query->with('object');
        $query->with('manufacturer');
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
}
