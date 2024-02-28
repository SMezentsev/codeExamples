<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\controllers\actions\reports;

use app\modules\itrack\models\History;
use app\modules\itrack\models\Report;
use Yii;
use yii\base\Action;
use yii\data\ActiveDataProvider;

/**
 * Class HistoryByCode
 *
 * @package app\modules\itrack\controllers\actions\reports
 *
 * Отчет по накладным
 */
class Invoice extends Action
{
    public function run($invoice = null, $dateStart = null, $dateEnd = null, $objectUid = null, $consignee = null, $download = false)
    {
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'Invoice',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$invoice, $dateStart, $dateEnd, $objectUid, $consignee]),
                "created_by"  => Yii::$app->user->getId(),
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета По накладным', [
                ["field" => "Номер накладной", "value" => $invoice],
                ["field" => "Период с", "value" => $dateStart],
                ["field" => "Период по", "value" => $dateEnd],
                ["field" => "Идентификатор объекта", "value" => $objectUid],
                ["field" => "Грузополучатель", "value" => $consignee],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = History::invoice($invoice, $dateStart, $dateEnd, $objectUid, $consignee);
            
            //\app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Просмотр отчета По накладным', [
                ["field" => "Номер накладной", "value" => $invoice],
                ["field" => "Период с", "value" => $dateStart],
                ["field" => "Период по", "value" => $dateEnd],
                ["field" => "Идентификатор объекта", "value" => $objectUid],
                ["field" => "Грузополучатель", "value" => $consignee],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
}