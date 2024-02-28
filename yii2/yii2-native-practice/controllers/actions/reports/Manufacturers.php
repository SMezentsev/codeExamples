<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 25/01/16
 * Time: 16:44
 */

namespace app\modules\itrack\controllers\actions\reports;


use app\modules\itrack\models\HistorySort;
use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\web\NotAcceptableHttpException;

class Manufacturers extends Action
{
    public function run($dateStart, $dateEnd, $manufacturer = null, $download = false)
    {
        if (!\Yii::$app->user->can('report-manufacturer')) {
            throw new NotAcceptableHttpException('Доступ запрещен');
        }
        $params = \Yii::$app->request->getQueryParams();
        if (\Yii::$app->user->can('manufacturer')) {
            $manufacturer = \Yii::$app->user->identity->manufacturer_uid;
        }
//        else
//            $manufacturer = $params["manufacturer"];
        if (empty($manufacturer)) {
            throw new NotAcceptableHttpException('К Вашей учетной записи не привязан не один производитель');
        }
        $params['manufacturer'] = $manufacturer;
        
        $dateStart = $params['dateStart'] = date('Y-m-d', strtotime($dateStart . '-01'));
        $dateEnd = $params['dateEnd'] = date('Y-m-t', strtotime($dateEnd . '-01'));
        
        $this->controller->serializer['afterSerializeModels'] = null;
//        select
//  nomenclature.gtin,
//  nomenclature.name,
//  product.series,
//  to_char(codes.release_date,'TMMonth YYYY') as release_date,
//  codes.code,
//  case when history.operation_uid=14 then 'Отгрузка' else 'Возврат' end as operation,
//  to_char(history.created_at,'TMMonth YYYY') as created_at,
//  invoices.dest_consignee,
//  invoices.dest_address
// from history
// LEFT JOIN history_data ON (history_data.history_uid = history.id)
// LEFT JOIN invoices ON (history_data.invoice_uid = invoices.id)
// LEFT JOIN codes ON (history.code_uid = codes.id)
// LEFT JOIN generations ON (codes.generation_uid = generations.id)
// LEFT JOIN product ON (product.id = codes.product_uid)
// LEFT JOIN nomenclature ON (nomenclature.id = product.nomenclature_uid)
// where
//  history.operation_uid IN (14,18)
//    and date(history.created_at) between '2014-01-01' and '2016-01-01'
//    and generations.codetype_uid = 1
//    and nomenclature.manufacturer_uid in (1,2)
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'Manufacturers',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([array_keys($params), array_values($params)]),
                "created_by"  => \Yii::$app->user->getId(),
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Деятельность контролеров', [
                ["field" => "Период с", "value" => $dateStart],
                ["field" => "Период по", "value" => $dateEnd],
                ["field" => "Идентификатор производителя", "value" => $manufacturer],
            ]);
            
            return [
                'report' => $report,
            ];
        }
        
        $model = new HistorySort();
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Деятельность контролеров', [
            ["field" => "Период с", "value" => $dateStart],
            ["field" => "Период по", "value" => $dateEnd],
            ["field" => "Идентификатор производителя", "value" => $manufacturer],
        ]);
        
        $dataProvider = $model->searchManufacturers($params);
        
        return $dataProvider;
    }
}