<?php

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\models\rafarma\Report1;
use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\ActiveDataProvider;

class RafarmaEndOfShiftReport extends Action
{
    
    public function run($series, $bdate, $edate, $withWeight = true, $download = false, $type = '')
    {
        if (empty($series)) {
            throw new \yii\web\BadRequestHttpException('Задайте поле series');
        }
        if (empty($bdate)) {
            throw new \yii\web\BadRequestHttpException('Задайте поле bdate');
        }
        if (empty($edate)) {
            throw new \yii\web\BadRequestHttpException('Задайте поле edate');
        }
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'RafarmaReportEndOfShift',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$bdate, $edate, $series, $withWeight]),
                "created_by"  => \Yii::$app->user->getId(),
                "typeof"      => $type,
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Конец смены', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["field" => "Серия", "value" => $series],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = Report1::reportEndOfShift($bdate, $edate, $series);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Конец смены', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["field" => "Серия", "value" => $series],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
