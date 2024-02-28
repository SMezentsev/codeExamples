<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 26.05.15
 * Time: 15:59
 */

namespace app\modules\itrack\controllers\actions\reports;


use app\modules\itrack\models\History;
use yii\base\Action;
use yii\data\ActiveDataProvider;

class BalanceInStock extends Action
{
    public function run($object_uid = null)
    {
        $query = History::balanceInStock($object_uid);
        
        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
}