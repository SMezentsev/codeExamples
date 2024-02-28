<?php

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\models\rafarma\Report1;
use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\ActiveDataProvider;


class Rafarma1Report extends Action
{
    
    public function run($series, $download = false, $type = '', $online = false)
    {
        if (empty($series)) {
            throw new \yii\web\BadRequestHttpException('Заполните поле series');
        }
        
        if ($online) {
            $filename = tempnam(\Yii::$aliases["@runtime"] . "/", "endOfSeries");
            Report1::generateReportEndOfSeries(null, $filename, $series, "json");
            $json = file_get_contents($filename);
            @unlink($filename);
            
            return $json;
        }
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'RafarmaReportEndOfSeries',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$series]),
                "created_by"  => \Yii::$app->user->getId(),
                "typeof"      => $type,
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Конец серии', [["field" => "Серия", "value" => $series]]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = Report1::reportEndOfSeries($series);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Конец серии', [["field" => "Серия", "value" => $series]]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
