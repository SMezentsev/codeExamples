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
use yii\web\NotAcceptableHttpException;
use \app\modules\itrack\components\pghelper;
use \app\modules\itrack\models\AuditLog;
use \app\modules\itrack\components\boxy\Helper;
use \app\modules\itrack\models\AuditOperation;

class HistoryByDate extends Action
{
    
    public function run($bdate, $edate, $series = '', $object_uid = null, $product_uid = null, $invoice = '', $download = false, $operation_uid = null, $created_by = null, $text = null, $invoice_date = null)
    {
        if (!Yii::$app->user->can('report-nomenclature-movement')) {
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        
        $bdate = Yii::$app->formatter->asDate($bdate);
        $edate = Yii::$app->formatter->asDate($edate);
        
        if ($download) {
            $report = new Report();
            $report->load([
                'report_type' => 'HistoryByDate',
                'params'      => pghelper::arr2pgarr([$bdate, $edate, $series, $object_uid, $product_uid, $invoice, $operation_uid, $created_by, $text, $invoice_date]),
                'created_by'  => Yii::$app->user->getId(),
            ], '');
            $report->save();
            AuditLog::Audit(AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Движение номенклатуры по дате упаковки', [
                ['field' => 'Период с', 'value' => $bdate],
                ['field' => 'Период по', 'value' => $edate],
                ['field' => 'Серия', 'value' => $series],
                ['field' => 'Идентификатор объекта', 'value' => $object_uid],
                ['field' => 'Идентификатор товарной карты', 'value' => $product_uid],
                ['field' => 'Номер накладной', 'value' => $invoice],
                ['field' => 'Тип операции', 'value' => $operation_uid],
                ['field' => 'Кто создал', 'value' => $created_by],
                ['field' => 'Текст', 'value' => $text],
                ['field' => 'Дата накладной', 'value' => $invoice_date],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $query = History::historyByDate($bdate, $edate, $series, $object_uid, $product_uid, $invoice);
            
            if (!empty($operation_uid)) {
                $query->andWhere(['a.operation_uid' => $operation_uid]);
            }
            if (!empty($created_by)) {
                $query->andWhere(['a.created_by' => $created_by]);
            }
            if (!empty($text)) {
                $query->andWhere(['ilike', 'a.data', $text]);
            }
            if (!empty($invoice_date)) {
                $query->andWhere(['i.invoice_date' => $invoice_date]);
            }
            
            
            Helper::sortAndFilterQuery($query);
            AuditLog::Audit(AuditOperation::OP_REPORT, 'Просмотр отчета Движение номенклатуры по дате упаковки', [
                ['field' => 'Период с', 'value' => $bdate],
                ['field' => 'Период по', 'value' => $edate],
                ['field' => 'Серия', 'value' => $series],
                ['field' => 'Идентификатор объекта', 'value' => $object_uid],
                ['field' => 'Идентификатор товарной карты', 'value' => $product_uid],
                ['field' => 'Номер накладной', 'value' => $invoice],
                ['field' => 'Тип операции', 'value' => $operation_uid],
                ['field' => 'Кто создал', 'value' => $created_by],
                ['field' => 'Текст', 'value' => $text],
                ['field' => 'Дата накладной', 'value' => $invoice_date],
            ]);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
    
}
