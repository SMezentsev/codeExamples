<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\AuditLog;
use app\modules\itrack\models\AuditOperation;
use app\modules\itrack\models\Code;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Invoice;
use Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;

/**
 * @OA\Post(
 *   path="/invoices",
 *   tags={"Накладные"},
 *   description="Создание накладной",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Накладная",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/invoices",
 *  tags={"Накладные"},
 *  description="Получение списка накладных",
 *  @OA\Response(
 *      response="200",
 *      description="Накладные",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="invoices",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/invoices/{id}",
 *  tags={"Накладные"},
 *  description="Получение накладной",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Накладная",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/invoices/{id}",
 *  tags={"Накладные"},
 *  description="Изменение накладной",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Накладная",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/invoices/{id}",
 *  tags={"Накладные"},
 *  description="Удаление накладной",
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

/**
 * @OA\Get(
 *  path="/invoices/check?number={number}",
 *  tags={"Накладные"},
 *  description="Получение накладной",
 *  @OA\Parameter(
 *      in="path",
 *      name="number",
 *      required=true,
 *      description="Номер накладной",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Накладные",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="invoices",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Invoice")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class InvoiceController extends ActiveController
{

    use ControllerTrait;

    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'invoices',
    ];

    public $modelClass = Invoice::class;

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        $actions['update']['scenario'] = 'update';

        unset($actions['create']);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['delete'], $actions['update'], $actions['confirm416']);
        }

        return $actions;
    }

    public function actionContent($id)
    {
        $modelClass = $this->modelClass;
        $invoice = $modelClass::findOne(['id' => $id]);

        $extraParams = $this->extraParams();
        if (in_array('missedCodes', $extraParams) || in_array('wasteCodes', $extraParams) || in_array(
                'errorCodes',
                $extraParams
            )) {
            $invoice->compare601();
        }

        AuditLog::Audit(
            AuditOperation::OP_INVOICE,
            "Просмотр содержимого накладной $invoice->invoice_number",
            [
                ['field' => 'Номер накладной', 'value' => $invoice->invoice_number],
                ['field' => 'Дата накладной', 'value' => $invoice->invoice_date]
            ]
        );

        return [
            'invoice' => $invoice->toArray(
                array_merge($invoice->fields(), ['codes', 'invoice_date']),
                $extraParams,
                true
            ),
        ];
    }

    /**
     * Апдейт накладной по запросу от аксапты
     *
     * @return type
     * @throws BadRequestHttpException
     */
    public function actionAxUpdate()
    {
        try {
            $params = \Yii::$app->request->getBodyParams();
            $blockMdlp = $params['BlockMDLP'] ?? '0';

            if ($blockMdlp == '0') {
                $invoices = Invoice::findAll(['invoice_number' => $params['invoice_number'], 'updated' => false]);
                foreach ($invoices as $invoice) {
                    $invoice->updateExternal();
                    $operations = Fns::find()->andWhere(
                        ['invoice_uid' => $invoice->id, 'state' => Fns::STATE_CHECKING]
                    )->andWhere(
                        ['>=', 'created_at', new Expression('created_at - interval \'1 day\'')]
                    )->all();
                    foreach ($operations as $operation) {
                        $operation->state = Fns::STATE_READY;
                        $operation->save(false, ['state']);
                    }
                }
            }
        } catch (\Exception $ex) {
            throw new BadRequestHttpException('Ошибка обработки документа');
        }

        return ['message' => 'Ok'];
    }

    /**
     * Статистккика для аксапты , по номеру накадной вернуть уникальные коды по отгрузкам - в разрезе товарная карта: количество
     *
     * @param type $invoice_number
     * @param type $gtin
     * @param type $series
     *
     * @return type
     */
    public function actionAx($invoice_number, $gtin, $series)
    {
        try {
            $ret = Yii::$app->db->createCommand(
                'select count(*) as cnt from (
                                                    select distinct codes.code from (
                                                        select unnest(codes) as code from invoices where invoice_number=:invoice
                                                    ) as a
                                                    LEFT JOIN codes ON a.code=codes.code
                                                    LEFT JOIN generations ON generation_uid=generations.id
                                                    LEFT JOIN product ON codes.product_uid = product.id
                                                    LEFT JOIN nomenclature ON product.nomenclature_uid = nomenclature.id
                                                    WHERE codetype_uid = 1 and product.series = :series and nomenclature.gtin=:gtin and codes.id is not null
                                                ) as a
                                        ',
                [
                    ':invoice' => $invoice_number,
                    ':series'  => $series,
                    ':gtin'    => $gtin,
                ]
            )->queryScalar();
        } catch (\Exception $ex) {
            throw new BadRequestHttpException('Ошибка обработки запроса, обратитесь к администрации ресурса');
        }

        return [
            'invoice_number' => $invoice_number,
            'series'         => $series,
            'gtin'           => $gtin,
            'cnt'            => $ret,
        ];
    }

    /**
     * Статистика для аксапты, возвращаем есть такая товарнка или нет и статусы кодов по накладной
     *
     * @param type $invoice_number
     * @param type $gtin
     * @param type $series
     */
    public function actionAxInfo($invoice_number, $gtin, $series)
    {
        $has = false;
        $found = false;
        $info = ['in_move' => 0, 'on_object' => 0];
        $res = Yii::$app->db->createCommand(
            'SELECT product.* FROM product LEFT JOIN nomenclature ON nomenclature.id = nomenclature_uid WHERE series=:series and gtin=:gtin',
            [
                ':series' => $series,
                ':gtin'   => $gtin,
            ]
        )->queryOne();
        if (!empty($res)) {
            $has = true;
            $product = $res;
        }
        $res = Yii::$app->db->createCommand(
            'SELECT codes FROM invoices WHERE invoice_number=:inv ORDER by created_at desc limit 1',
            [':inv' => $invoice_number]
        )->queryOne();
        if (!empty($res)) {
            $found = true;
        }
        if ($found) {
            $res = Yii::$app->db->createCommand(
                'SELECT is_released(flag) as released,count(*) as cnt FROM _get_codes_array(:arr) as codes
                                                        LEFT JOIN generations on generation_uid=generations.id
                                                        WHERE codetype_uid = :codetype and codes.product_uid = :product
                                                        GROUP by 1
                                                    ',
                [
                    ':arr'      => $res['codes'],
                    ':codetype' => CodeType::CODE_TYPE_INDIVIDUAL,
                    ':product'  => $product['id'] ?? null,
                ]
            )->queryAll();
            foreach ($res as $r) {
                if ($r['released']) {
                    $info['in_move'] = $r['cnt'];
                } else {
                    $info['on_object'] = $r['cnt'];
                }
            }
        }

        return [
            'gtin:series'   => $has,
            'invoice_found' => $found,
            'invoice'       => $info,
        ];
    }

    /*
     * Запрос черновика
     */
    public function actionDraft($invoice_number, $invoice_date = null)
    {
        $mc = $this->modelClass;
        $model = $mc::find()->andWhere(
            ['invoice_number' => $invoice_number, 'typeof' => [1, 2], 'invoice_date' => $invoice_date]
        )->one();
        if (empty($model)) {
            throw new NotFoundHttpException('Черновик не найден');
        }
        AuditLog::Audit(
            AuditOperation::OP_INVOICE,
            "Поиск черновика (номер: $invoice_number, дата: $invoice_date)",
            []
        );

        return $model;
    }

    /**
     * метод перевода накладной из карантина в обычные накладные
     */
    public function actionConfirm416($id)
    {
        $mc = $this->modelClass;
        $model = $mc::findOne(['id' => $id]);
        if (empty($model)) {
            throw new NotFoundHttpException('Накладная не найдена');
        }
        if ($model->typeof != 2) {
            throw new BadRequestHttpException('Накладная не находиться на карантине');
        }
        $params = Yii::$app->request->getBodyParams();
        $op = Fns::findOne(
            ['operation_uid' => Fns::OPERATION_416, 'invoice_uid' => $model->id, 'state' => Fns::STATE_RESPONCE_SUCCESS]
        );
        if (empty($op)) {
            throw new BadRequestHttpException('Не найден 416 документ');
        }

        $trans = Yii::$app->db->beginTransaction();
        $model->typeof = 0;
        $model->save(false, ['typeof']);

        $ret = Code::incomeReverseCodes(pghelper::pgarr2arr($model->realcodes), $model->object_uid, $model->id);

        if ($ret[0]) {
            $trans->rollBack();
            throw new BadRequestHttpException($ret[1]);
        }
        $trans->commit();

        AuditLog::Audit(
            AuditOperation::OP_INVOICE,
            "Подтверждение накладной в карантине $model->invoice_number",
            [
                ['field' => 'Номер накладной', 'value' => $model->invoice_number],
                ['field' => 'Дата накладной', 'value' => $model->invoice_date],
                ['field' => 'Параметры', 'value' => $params]
            ]
        );

        return $model;
    }

    /*
     * Создание черновика накладной
     */
    public function actionSave()
    {
        $params = Yii::$app->request->getBodyParams();
        $params['realcodes'] = $params['codes'];
        $mc = $this->modelClass;
        $model = $mc::findOne(['invoice_number' => $params['invoice_number'], 'typeof' => 1]);
        if (empty($model)) {
            $model = new $mc;
        } elseif (SERVER_RULE == SERVER_RULE_SKLAD) {
            $p = ArrayHelper::toArray($model);
            $model = new $mc;
            $model->load($p);
            $model->id = $p['uid'];
            $model->created_at = $p['created_at'];
            $model->is_gover = $p['is_gover'];
            $model->invoice_date = $p['invoice_date'];
            $model->typeof = 1;
            $model->sended = false;
        }

        $model->scenario = "temp";
        if ($model->load($params, '')) {
            if ($model->save()) {
                $model->refresh();
                AuditLog::Audit(
                    AuditOperation::OP_INVOICE,
                    "Создание черновика $model->invoice_number",
                    [
                        ['field' => 'Номер черновика', 'value' => $model->invoice_number],
                        ['field' => 'Дата накладной', 'value' => $model->invoice_date],
                        ['field' => 'Коды', 'value' => $params['codes']]
                    ]
                );

                return $model;
            } else {
                throw new BadRequestHttpException(
                    'Ошибка сохранения: ' . implode(
                        '| ',
                        array_map(
                            function ($v) {
                                return implode(', ', $v);
                            },
                            $model->errors
                        )
                    )
                );
            }
        } else {
            throw new BadRequestHttpException(
                'Ошибка сохранения: ' . implode(
                    '| ',
                    array_map(
                        function ($v) {
                            return implode(', ', $v);
                        },
                        $model->errors
                    )
                )
            );
        }
    }

    /**
     * Проверка накладной во внешней системе заказчика (Аксапта)
     *
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionCheckExternal()
    {
        $number = Yii::$app->request->getQueryParam('number');
        $date = Yii::$app->request->getQueryParam('date');

        $invoice = new Invoice();
        $invoice->invoice_number = $number;
        $invoice->invoice_date = $date;
        $invoice->object_uid = Yii::$app->user->getIdentity()->object_uid ?? null;

        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            throw new NotFoundHttpException('Некорректные настройки подключения');
        }

        try {
            $invoiceCodes = $invoice->updateExternal(false);
        } catch (Exception $ex) {
        }

        AuditLog::Audit(AuditOperation::OP_INVOICE, "Проверка накладной в системе заказчика $number/$date", []);
        if (empty($invoice->vatvalue)) {
            $invoiceProducts = [];
            if (empty($invoiceCodes)) {
                $invoiceCodes = [];
            }
            foreach ($invoiceCodes as $series => $qnt) {
                $invoiceProducts[] = ['series' => $series, 'count' => $qnt];
            }

            return ['status' => 200, 'products' => $invoiceProducts];
        } else {
            throw new NotFoundHttpException('Накладная не найдена');
        }
    }

    /**
     * Проверка существования накладной при приемке кодов - номер накладной в Query String
     *
     * @return type
     */
    public function actionCheck2()
    {
        $number = Yii::$app->request->getQueryParam('number');

        return $this->actionCheck($number);
    }

    /**
     * Првоерка существования накладной при приемке кодов - номер накладной в УРЛ
     *
     * @param type $number
     *
     * @return type
     * @throws NotFoundHttpException
     */
    public function actionCheck($number)
    {
        $query = Invoice::find()->andWhere(['invoice_number' => $number]);
        $query->andWhere(new Expression('newobject_uid is not null'));

        $query->andWhere(
            [
                'or',
                ['object_uid' => Yii::$app->user->identity->object_uid],
                ['newobject_uid' => Yii::$app->user->identity->object_uid],
            ]
        )->orderBy('created_at DESC');

        $model = $query->all();
        if (!$model) {
            throw new NotFoundHttpException('Накладная не найдена');
        }
        AuditLog::Audit(AuditOperation::OP_INVOICE, "Проверка накладной в iTrack $number", []);

        return ['invoices' => $model];
    }

    public function afterSerializeModels($data)
    {
        foreach ($data as &$item) {
            unset($item['codes'], $item['realcodes'], $item['content']);
        }

        return $data;
    }


    /**
     * Список накладных (typeof = 0 накладная, 1 черновик, 2 карантин)
     *
     * @return ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $this->serializer['afterSerializeModels'] = [$this, 'afterSerializeModels'];
        $modelClass = $this->modelClass;
        $query = $modelClass::find()->select(
            new \yii\db\Expression('distinct on(invoice_number,invoice_date) *')
        )->orderBy('invoice_number,invoice_date,created_at desc');
        $query = $modelClass::find()->select('*')->from(['a' => $query])->orderBy('created_at DESC');


        $date = Yii::$app->getRequest()->get('invoice_date');
        $number = Yii::$app->getRequest()->get('invoice_number');
        $object_uid = Yii::$app->getRequest()->get('object_uid');
        $typeof = Yii::$app->getRequest()->get('typeof');
        $dest_inn = Yii::$app->getRequest()->get('dest_inn');
        if (!empty($number)) {
            $query->andWhere('invoice_number ilike :invN', [':invN' => $number . '%']);
        }
        if (!empty($object_uid)) {
            $query->andWhere(new \yii\db\Expression(pg_escape_string($object_uid) . '= object_uid'));
        }
        if (!empty($dest_inn)) {
            $query->andWhere(['dest_inn' => $dest_inn]);
        }
        //            $query->andWhere(new \yii\db\Expression(pg_escape_string($object_uid) . '= object_uid or newobject_uid = '. pg_escape_string($object_uid)));
        if (!empty($date)) {
            $query->andWhere('invoice_date = :date', [':date' => $date]);
        }

        $query->andWhere(['typeof' => intval($typeof)]);


        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query, $modelClass);

        //        $query->with('object.user');
        //        $query->with('object.user.manufacturer');
        $query->with('object');
        $query->with('createdBy');
        $query->with('newObject');
        //        $query->with('newObject.user');
        //        $query->with('newObject.user.manufacturer');
        if (!Yii::$app->user->can('see-all-objects')) {
            $query->andWhere(
                '(object_uid = ' . Yii::$app->user->identity->object_uid . ' or newobject_uid = ' . Yii::$app->user->identity->object_uid . ')'
            );
        }

        AuditLog::Audit(AuditOperation::OP_INVOICE, 'Просмотр списка накладных', []);

        return new ActiveDataProvider(
            [
                'query' => $query,
            ]
        );
    }

    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'update':
                if (!Yii::$app->user->can('reference-invoice-crud')) {
                    throw new NotAcceptableHttpException('Запрет на выполнение операции');
                }
                break;
            case 'view':
            case 'index':
            case 'check2':
            case 'check':
                if (!Yii::$app->user->can('reference-invoice') && !Yii::$app->user->can(
                        'codeFunction-income-prod'
                    ) && !Yii::$app->user->can('codeFunction-income-log')) {
                    throw new NotAcceptableHttpException('Запрет на выполнение операции');
                }
                break;
            case 'save':
            case 'draft':
                break;
            case 'confirm416':
                if (!Yii::$app->user->can('codeFunction-incomeReverse')) {
                    throw new NotAcceptableHttpException('Запрет на выполнение операции');
                }
                break;
        }

        return parent::checkAccess($action, $model, $params);
    }
}