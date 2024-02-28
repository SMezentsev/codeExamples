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
use yii\web\NotAcceptableHttpException;

class HistoryOfCheckMan extends Action
{
    public function run($bdate, $edate, $user_uid = null, $download = false)
    {
        if (!\Yii::$app->user->can('report-checkman')) {
            throw new NotAcceptableHttpException("Запрет на выполнение операции");
        }
        
        $bdate = \Yii::$app->formatter->asDate($bdate);
        $edate = \Yii::$app->formatter->asDate($edate);
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'HistoryOfCheckMan',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$bdate, $edate, $user_uid]),
                "created_by"  => Yii::$app->user->getId(),
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Деятельность контроллеров', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["field" => "Идентификатор контролера", "value" => $user_uid],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = History::historyOfCheckMan()
                ->andWhere(['between', 'history.created_at', \Yii::$app->formatter->asDatetime($bdate), \Yii::$app->formatter->asDatetime($edate . " 23:59:59")])
                ->andFilterWhere(['users.id' => $user_uid]);
            
            \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Деятельность контроллеров', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["field" => "Идентификатор контролера", "value" => $user_uid],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
}