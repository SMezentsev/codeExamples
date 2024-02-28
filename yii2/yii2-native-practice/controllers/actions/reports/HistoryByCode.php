<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\controllers\actions\reports;

use app\modules\itrack\models\History;
use app\modules\itrack\models\Report;
use app\modules\og\models\Code;
use Yii;
use yii\base\Action;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;

class HistoryByCode extends Action
{
    public function run($codes, $download = false, $product_uid = null, $operation_uid = null, $created_by = null, $object_uid = null, $text = null, $invoice_number = null, $invoice_date = null)
    {
        if (!\Yii::$app->user->can('report-nomenclature-movement')) {
            throw new NotAcceptableHttpException("Запрет на выполнение операции");
        }
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'HistoryByCode',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$codes, $product_uid, $operation_uid, $created_by, $object_uid, $text, $invoice_number, $invoice_date]),
                "created_by"  => Yii::$app->user->getId(),
            ], '');
            $report->save();
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, 'Заказ выгрузки отчета Движение номенклатуры по коду', [
                ["field" => "Список кодов", "value" => $codes],
                ["field" => "Идентификатор товарной карты", "value" => $product_uid],
                ["field" => "Тип операции", "value" => $operation_uid],
                ["field" => "Кто создал", "value" => $created_by],
                ["field" => "Идентификатор объекта", "value" => $object_uid],
                ["field" => "Текст", "value" => $text],
                ["field" => "Номер накладной", "value" => $invoice_number],
                ["field" => "Дата накладной", "value" => $invoice_date],
            ]);
            
            return [
                'report' => $report,
            ];
        } else {
            $codes = explode(',', $codes);
            $codes = array_map(function ($item) {
                return \app\modules\itrack\models\Code::stripCode(htmlentities($item));
            }, $codes);
            
            $query = History::historyForReport()
                ->andWhere(['in', 'f.code', $codes]);
            if (!empty($product_uid)) {
                $query->andWhere(['g.id' => $product_uid]);
            }
            if (!empty($operation_uid)) {
                $query->andWhere(['b.id' => $operation_uid]);
            }
            if (!empty($created_by)) {
                $query->andWhere(['d.id' => $created_by]);
            }
            if (!empty($object_uid)) {
                $query->andWhere(['e.id' => $object_uid]);
            }
            if (!empty($text)) {
                $query->andWhere(['ilike', 'a.data', $text]);
            }
            if (!empty($invoice_number)) {
                $query->andWhere(['i.invoice_number' => $invoice_number]);
            }
            if (!empty($invoice_date)) {
                $query->andWhere(['i.invoice_date' => $invoice_date]);
            }
            
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, 'Просмотр отчета Движение номенклатуры по коду', [
                ["field" => "Список кодов", "value" => $codes],
                ["field" => "Идентификатор товарной карты", "value" => $product_uid],
                ["field" => "Тип операции", "value" => $operation_uid],
                ["field" => "Кто создал", "value" => $created_by],
                ["field" => "Идентификатор объекта", "value" => $object_uid],
                ["field" => "Текст", "value" => $text],
                ["field" => "Номер накладной", "value" => $invoice_number],
                ["field" => "Дата накладной", "value" => $invoice_date],
            ]);
            
            //поиск всех кодов , для фильтрации истории по датам.... - оптимизация скорости выполнения запросов
            $c = Code::find();
            $res = $c->select(new Expression('min(coalesce(activate_date,product.created_at,current_date)) as mind,max(date(coalesce(lmtime,release_date,current_date))) as maxd'))->leftJoin('product', 'product_uid=product.id')->andWhere(['in', 'code', $codes])->asArray()->one();
            if (!empty($res["mind"]) && !empty($res["maxd"])) {
                $query->andWhere(['between', 'a.created_at', $res["mind"], $res["maxd"] . " 23:59:59"]);
            } else {
                throw new NotFoundHttpException("Коды не найдены: " . implode(",", $codes), 403);
            }
            \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
            
            return new ActiveDataProvider([
                'query' => $query,
            ]);
        }
    }
}