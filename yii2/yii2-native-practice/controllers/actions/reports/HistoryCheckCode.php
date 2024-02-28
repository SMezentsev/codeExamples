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
use app\modules\itrack\models\Report;
use Yii;
use yii\base\Action;
use yii\data\ActiveDataProvider;

/**
 * история проверок покупателями
 */
class HistoryCheckCode extends Action
{
    public function run($bdate, $edate, $user_uid = null, $download = false)
    {
        $bdate = \Yii::$app->formatter->asDate($bdate);
        $edate = \Yii::$app->formatter->asDate($edate);
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'HistoryCheckCode',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$bdate, $edate]),
                "created_by"  => Yii::$app->user->getId(),
            ], '');
            $report->save();
            
            return [
                'report' => $report,
            ];
        } else {
            $query = History::historyCheckCode()
                ->andWhere(['between', 'date(checks.created_at)', $bdate, $edate]);
            
            app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
}