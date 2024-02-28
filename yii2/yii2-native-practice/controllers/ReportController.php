<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 26.05.15
 * Time: 14:14
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\controllers\actions\reports\BalanceInStock;
use app\modules\itrack\controllers\actions\reports\HistoryByCode;
use app\modules\itrack\controllers\actions\reports\HistoryByDate;
use app\modules\itrack\controllers\actions\reports\HistoryCheckCode;
use app\modules\itrack\controllers\actions\reports\HistoryOfCheckMan;
use app\modules\itrack\controllers\actions\reports\Invoice;
use app\modules\itrack\controllers\actions\reports\Manufacturers;
use app\modules\itrack\controllers\rafarma\Rafarma1Report;
use app\modules\itrack\controllers\rafarma\Rafarma2Report;
use app\modules\itrack\controllers\rafarma\Rafarma3Report;
use app\modules\itrack\controllers\rafarma\Rafarma4Report;
use app\modules\itrack\controllers\rafarma\Rafarma5Report;
use app\modules\itrack\controllers\rafarma\Rafarma6Report;
use app\modules\itrack\controllers\rafarma\Rafarma7_2Report;
use app\modules\itrack\controllers\rafarma\Rafarma7Report;
use app\modules\itrack\controllers\rafarma\RafarmaEndOfShiftReport;
use app\modules\itrack\models\Nomenclature;
use yii\data\ActiveDataProvider;
use yii\rest\Controller;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;

/**
 * @OA\Get(
 *  path="/reports",
 *  tags={"Отчет"},
 *  description="Получение списка отчетов",
 *  @OA\Response(
 *      response="200",
 *      description="Отчеты",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="report",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Report")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/reports/{id}",
 *  tags={"Отчет"},
 *  description="Получение отчета",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Отчет",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Report")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/reports/{id}",
 *  tags={"Отчет"},
 *  description="Удаление отчета",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="204",
 *      description="",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class ReportController extends Controller
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Report';
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'report',
    ];
    
    public function t($models)
    {
        foreach ($models as &$model) {
            if (isset($model['code'])) {
            }
            
            if (isset($model['created_at'])) {
                $model['created_at'] = \Yii::$app->formatter->asDatetime($model['created_at']);
            }
            
            if (isset($model['invoice_date'])) {
                $model['invoice_date'] = \Yii::$app->formatter->asDate($model['invoice_date']);
            }
        }
        
        return $models;
    }
    
    public function invoice($models)
    {
        $return = [];
        
        foreach ($models as $model) {
            $row = [
                'invoice_number'  => $model['invoice_number'],
                'invoice_date'    => \Yii::$app->formatter->asDate($model['invoice_date']),
                'fio'             => $model['fio'],
                'name'            => $model['name'],
                'dest_address'    => $model['dest_address'],
                'dest_consignee'  => (empty($model['dest_consignee'])) ? $model['o2name'] : $model['dest_consignee'],
                'dest_settlement' => $model['dest_settlement'],
                'created_at'      => $model['created_at'],
                'code'            => $model["cont"],
            ];
            
            $return[] = $row;
        }
        
        return $return;
    }
    
    public function actions()
    {
        $this->serializer['afterSerializeModels'] = [$this, 't'];
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            return [
                'endofseries'          => Rafarma1Report::class,
                'endofshift'           => RafarmaEndOfShiftReport::class,
                'eventlog'             => Rafarma2Report::class,
                'box-data'             => Rafarma3Report::class,
                'pallet-data'          => Rafarma4Report::class,
                'auditlog'             => Rafarma5Report::class,
                'brak'                 => Rafarma6Report::class,
                'serialization'        => Rafarma7Report::class,
                'serializationByShift' => Rafarma7_2Report::class,
                
                'index'  => [
                    'class'               => 'yii\rest\IndexAction',
                    'modelClass'          => $this->modelClass,
                    'prepareDataProvider' => [$this, 'prepareDataProvider'],
                ],
                'view'   => [
                    'class'      => 'yii\rest\ViewAction',
                    'modelClass' => $this->modelClass,
                ],
                'delete' => [
                    'class'      => 'yii\rest\DeleteAction',
                    'modelClass' => $this->modelClass,
                ],
            ];
        }
        
        return array_merge(parent::actions(), [
            'historyByCode'        => HistoryByCode::class,
            'historyByDate'        => HistoryByDate::class,
            'balanceInStock'       => BalanceInStock::class,
            'historyOfCheckMan'    => HistoryOfCheckMan::class,
            'historyCheckCode'     => HistoryCheckCode::class,
            'endofseries'          => Rafarma1Report::class,
            'endofshift'           => RafarmaEndOfShiftReport::class,
            'eventlog'             => Rafarma2Report::class,
            'box-data'             => Rafarma3Report::class,
            'pallet-data'          => Rafarma4Report::class,
            'auditlog'             => Rafarma5Report::class,
            'brak'                 => Rafarma6Report::class,
            'serialization'        => Rafarma7Report::class,
            'serializationByShift' => Rafarma7_2Report::class,
            'manufacturers'        => Manufacturers::class,
            
            'index'  => [
                'class'               => 'yii\rest\IndexAction',
                'modelClass'          => $this->modelClass,
                'prepareDataProvider' => [$this, 'prepareDataProvider'],
            ],
            'view'   => [
                'class'      => 'yii\rest\ViewAction',
                'modelClass' => $this->modelClass,
            ],
            'delete' => [
                'class'      => 'yii\rest\DeleteAction',
                'modelClass' => $this->modelClass,
            ],
        ]);
    }
    
    public function actionInvoice($invoice = null, $dateStart = null, $dateEnd = null, $objectUid = null, $consignee = null, $download = false)
    {
        if (!\Yii::$app->user->can('report-invoices')) {
            throw new NotAcceptableHttpException('Доступ запрещен');
        }
        $action = new Invoice('invoice', 'report');
        $data = $action->run($invoice, $dateStart, $dateEnd, $objectUid, $consignee, $download);
        
        $this->serializer['afterSerializeModels'] = [$this, 'invoice'];
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT, "Просмотр отчета по накладным", [["field" => "Номер накладной", "value" => $invoice], ["field" => "Период с ", "value" => $dateStart], ["field" => "Период по", "value" => $dateEnd], ["field" => "Идентификатор объекта", "value" => $objectUid], ["field" => "Грузополучатель", "value" => $consignee]]);
        
        return $data;
    }
    
    public function prepareDataProvider()
    {
        $modelClass = $this->modelClass;
        $query = $modelClass::find()->orderBy('created_at DESC');
        if (!\Yii::$app->user->can('rfAdmin')) {
            $query->andWhere(['created_by' => \Yii::$app->user->getId()]);
        }
        
        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
    
    public function actionCancel($id)
    {
        $model = $this->findModel($id);
        if (!empty($model)) {
            if ($model->status != 'READY') {
                $model->status = 'CANCEL';
            }
        } else {
            throw new NotFoundHttpException('Отчет с идентификатором ' . $id . ' - не найден');
        }
        
        return $model;
    }
    
    public function actionDownload($id)
    {
        $model = $this->findModel($id);
        $fileName = $model->getFileName() . ".xls";
        
        if ($model->status == 'READY') {
            \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_REPORT_FILE, "Сохранение файла выгрузки отчета $id", [["field" => "Идентификатор выгрузки", "value" => $id]]);
            
            if (file_exists($fileName)) {
                \Yii::$app->getResponse()->sendFile($fileName);
            } else {
                $fileName = $model->getFileName() . ".csv";
                if (file_exists($fileName)) {
                    \Yii::$app->getResponse()->sendFile($fileName);
                } else {
                    $fileName = $model->getFileName() . ".docx";
                    if (file_exists($fileName)) {
                        \Yii::$app->getResponse()->sendFile($fileName);
                    } else {
                        $fileName = $model->getFileName() . ".pdf";
                        if (file_exists($fileName)) {
                            \Yii::$app->getResponse()->sendFile($fileName);
                        } else {
                            $fileName = $model->getFileName() . ".json";
                            if (file_exists($fileName)) {
                                \Yii::$app->getResponse()->sendFile($fileName);
                            } else {
                                \Yii::info("Report file not found: $fileName");
                                throw new NotFoundHttpException("Отчет не найден");
                            }
                        }
                    }
                }
            }
        } else {
            \Yii::info("Report file not found: $fileName");
            throw new NotFoundHttpException("Отчет еще не сгенерирован, попробуйте позже");
        }
    }
    
    public function actionManufacturersLibrary($mid = null)
    {
        if (!\Yii::$app->user->can('report-manufacturer')) {
            throw new NotAcceptableHttpException('Доступ запрещен');
        }
        if (\Yii::$app->user->can('manufacturer')) {
            $mid = \Yii::$app->user->identity->manufacturer_uid;
        }
        
        $gen = Nomenclature::find()->select(['gtin', 'name', 'object_uid', 'manufacturer_uid'])->andFilterWhere(['manufacturer_uid' => $mid])->groupBy(['gtin', 'name', 'object_uid', 'manufacturer_uid'])->orderBy('name')->all();
        $this->serializer['collectionEnvelope'] = 'manufacturerLibrary';
        $result = ['gtin' => [], 'name' => []];
        foreach ($gen as $item) {
            if ($item->gtin) {
                $result['gtin'][$item->gtin] = ['gtin' => $item->gtin, 'object_uid' => $item->object_uid, 'manufacturer_uid' => $item->manufacturer_uid, 'name' => $item->name];
            }
            if ($item->name) {
                $result['name'][$item->gtin . $item->name] = ['gtin' => $item->gtin, 'object_uid' => $item->object_uid, 'manufacturer_uid' => $item->manufacturer_uid, 'name' => $item->name];
            }
        }
        $result['gtin'] = array_values($result['gtin']);
        $result['name'] = array_values($result['name']);
        
        return $result;
    }
    
}
