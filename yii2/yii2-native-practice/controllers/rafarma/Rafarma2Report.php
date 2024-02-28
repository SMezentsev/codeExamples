<?php

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\ActiveDataProvider;

class Rafarma2Report extends Action
{
    
    public function run($bdate = null, $edate = null, $download = false, $type = '')
    {
        if (empty($bdate)) {
            $bdate = date("Y-m-d 00:00:00");
        }
        if (empty($edate)) {
            $edate = date("Y-m-d 23:59:59");
        }
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'RafarmaReport2',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$bdate, $edate]),
                "created_by"  => \Yii::$app->user->getId(),
                "typeof"      => $type,
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки по отчету журнал событий', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = \app\modules\itrack\models\rafarma\Report2::report(null, $bdate, $edate);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета журнал событий', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
