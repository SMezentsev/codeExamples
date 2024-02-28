<?php

namespace app\modules\itrack\controllers\rafarma;

use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\ActiveDataProvider;

class Rafarma7_2Report extends Action
{
    
    public function run($equipid, $bdate, $edate, $nomenclature = null, $shift = null, $download = false, $type = '')
    {
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $bdate)) {
            $bdate .= " 00:00:00";
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $edate)) {
            $edate .= " 23:59:59";
        }
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'RafarmaReport7' . (!empty($shift) ? "_2" : ""),
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$equipid, $bdate, $edate, $nomenclature, $shift]),
                "created_by"  => \Yii::$app->user->getId(),
                "typeof"      => $type,
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Сериализация за смену', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["equipid" => "Идентификатор оборудования", "value" => $equipid],
                ["nomenclature" => "Номенклатура", "value" => $nomenclature],
                ["shift" => "Смена", "value" => $shift],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = \app\modules\itrack\models\rafarma\Report7::report($equipid, $bdate, $edate, $nomenclature, $shift);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Сериализация за смену', [
                ["field" => "Период с", "value" => $bdate],
                ["field" => "Период по", "value" => $edate],
                ["equipid" => "Идентификатор оборудования", "value" => $equipid],
                ["nomenclature" => "Номенклатура", "value" => $nomenclature],
                ["shift" => "Смена", "value" => $shift],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
