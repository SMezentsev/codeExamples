<?php

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\ActiveDataProvider;

class Rafarma4Report extends Action
{
    
    public function run($series = null, $download = false)
    {
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'RafarmaReport4',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$series]),
                "created_by"  => \Yii::$app->user->getId(),
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета pallet-data', [["field" => "Серия", "value" => $series]]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = \app\modules\itrack\models\rafarma\Report4::report($series);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета pallet-data', [["field" => "Серия", "value" => $series]]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
