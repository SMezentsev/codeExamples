<?php
/**
 * @link http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\ArrayHelper;
use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\NTLMSoapClient;
use app\modules\itrack\models\connectors\Connector;
use Exception;
use Yii;
use yii\db\Expression;
use yii\log\Logger;
use app\modules\itrack\components\boxy\ActiveRecord;
use app\modules\itrack\components\pghelper;

/**
 * This is the model class for table "invoices".
 *
 * @property integer $id
 * @property string $invoice_number
 * @property string $invoice_date
 * @property string $codes
 * @property string $realcodes
 * @property integer $created_by
 * @property string $created_at
 * @property boolean $is_gover
 * @property string $dest_address
 * @property string $dest_consignee
 * @property string $dest_settlement
 * @property integer $object_uid
 * @property integer $newobject_uid
 * @property integer $codes_cnt
 * @property integer $updated_by
 * @property string $updated_at
 * @property string $updated_ext_at
 * @property string $updated
 * @property string $turnover_type
 * @property string $cost
 * @property string $vatvalue
 * @property string $dest_kpp
 * @property string $dest_inn
 * @property string $dest_fns
 * @property string $contract_type
 * @property string $cust_name
 * @property string $cust_address
 * @property string $cust_settlement
 * @property string $cust_kpp
 * @property string $cust_inn
 * @property string $ismdata
 * @property string $errorCodes
 * @property string $contract_num   номер гос контракта
 * @property string $source
 * @property string $returned_codes
 * @property bool $blockMdlp
 *
 * @property Facility $object
 * @property User $createdBy
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Invoice",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="string", example="9d15d8ad-c948-410a-9c90-82f458f61686"),
 *          @OA\Property(property="invoice_number", type="string", example="1202"),
 *          @OA\Property(property="invoice_date", type="string", example="2020-02-12"),
 *          @OA\Property(property="is_gover", type="boolean", example=false),
 *          @OA\Property(property="createdBy", ref="#/components/schemas/app_modules_itrack_models_User"),
 *          @OA\Property(property="created_at", type="string", example="2020-02-19 11:43:17+0300"),
 *          @OA\Property(property="dest_address", type="string", example=""),
 *          @OA\Property(property="dest_consignee", type="string", example="Байер"),
 *          @OA\Property(property="dest_settlement", type="string", example=""),
 *          @OA\Property(property="dest_inn", type="string", example=null),
 *          @OA\Property(property="dest_kpp", type="string", example=null),
 *          @OA\Property(property="object_uid", type="integer", example="350"),
 *          @OA\Property(property="newobject_uid", type="string", example="2"),
 *          @OA\Property(property="object", type="string", example=null),
 *          @OA\Property(property="source", type="string", example=null),
 *          @OA\Property(property="newObject", ref="#/components/schemas/app_modules_itrack_models_Facility"),
 *          @OA\Property(property="typeof", type="integer", example="0"),
 *      }
 * )
 */
class Invoice extends ActiveRecord
{


    const INVOICE_NORM = 0;
    const INVOICE_DRAFT = 1;
    const INVOICE_QUARANTINE = 2;

    protected $wasteCodes;
    protected $missedCodes;
    protected $errorCodes;
    /**
     * @var bool блокировка отправки, если внешняя система вернула запрет
     */
    public $blockMdlp = false;

    public $infotxt;

    /**
     * @inheritdoc
     */

    public static function tableName()
    {
        return 'invoices';
    }

    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }

    public function init()
    {
        parent::init();

        $this->on(
            self::EVENT_BEFORE_UPDATE,
            function ($event) {
                /** @var $event ModelEvent */

                $event->sender->updated_at = 'NOW()';
                if (!empty(\Yii::$app->user)) {
                    $event->sender->updated_by = \Yii::$app->user->getId();
                }
            }
        );
        $this->on(
            self::EVENT_BEFORE_INSERT,
            function ($event) {
                /** @var $event ModelEvent */
                $event->sender->updated_at = 'NOW()';
                if (!empty(\Yii::$app->user)) {
                    $id = \Yii::$app->user->getId();
                    if (!empty($id)) {
                        $event->sender->created_by = $id;
                    }
                    if (isset(\Yii::$app->user->identity->object_uid)) {
                        $event->sender->object_uid = \Yii::$app->user->identity->object_uid;
                    }
                }
            }
        );
        $this->on(
            self::EVENT_BEFORE_VALIDATE,
            function ($event) {
                /** @var $event ModelEvent */
                $event->sender->codes = \app\modules\itrack\components\pghelper::arr2pgarr($event->sender->codes);
                $event->sender->realcodes = \app\modules\itrack\components\pghelper::arr2pgarr(
                    $event->sender->realcodes
                );
            }
        );
    }

    public function delete()
    {
        if ($this->typeof == 0) {
            throw new \yii\web\BadRequestHttpException('Запрет на удаление накладных');
        }

        return parent::delete();
    }

    public function scenarios()
    {
        return array_merge(
            parent::scenarios(),
            [
                'external' => [
                    'invoice_number',
                    'invoice_date',
                    'codes',
                    'created_by',
                    'object_uid',
                    'newobject_uid',
                    'realcodes',
                    'updated',
                    'turnover_type',
                    'contract_type',
                    'content',
                    'dest_consignee',
                    'dest_address',
                    'contract_num',
                    'source'
                ],
                'update'   => ['is_gover', 'dest_address', 'dest_consignee', 'dest_settlement'],
                'temp'     => [
                    'invoice_number',
                    'invoice_date',
                    'codes',
                    'created_by',
                    'object_uid',
                    'typeof',
                    'realcodes'
                ],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['invoice_number', 'codes', 'typeof'], 'required'],
            [
                [
                    'invoice_number',
                    'codes',
                    'dest_address',
                    'dest_consignee',
                    'dest_settlement',
                    'contract_num',
                    'source'
                ],
                'string'
            ],
            [['invoice_date', 'created_at', 'updated_at', 'updated_ext_at'], 'safe'],
            [['created_by', 'object_uid', 'updated_by', 'typeof'], 'integer'],
            [['typeof'], 'in', 'range' => [1, 2]],
            [['is_gover'], 'boolean']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'invoice_number'  => 'Invoice Number',
            'invoice_date'    => 'Invoice Date',
            'codes'           => 'Codes',
            'created_by'      => 'Created By',
            'created_at'      => 'Created At',
            'is_gover'        => 'Is Gover',
            'dest_address'    => 'Dest Address',
            'dest_consignee'  => 'Dest Consignee',
            'dest_settlement' => 'Dest Settlement',
            'object_uid'      => 'Object Uid',
            'updated_by'      => 'Updated By',
            'updated_at'      => 'Updated At',
        ];
    }

    //    public static function find() {
    //        return parent::find(); //->andWhere('invoices.typeof = 0');
    //    }

    public function fields()
    {
        return [
            'uid'          => 'id',
            'invoice_number',
            'invoice_date' => function () {
                return Yii::$app->formatter->asDate($this->invoice_date);
            },
            'codes'        => function () {
                return \app\modules\itrack\components\pghelper::pgarr2arr($this->realcodes);
            },
            'is_gover',
            'createdBy',
            'created_at'   => function () {
                return Yii::$app->formatter->asDatetime($this->created_at);
            },
            'dest_address',
            'dest_consignee',
            'dest_settlement',
            'dest_inn',
            'dest_kpp',
            'object_uid',
            'newobject_uid',
            'object',
            'source',
            'newObject',
            'typeof',
        ];
    }

    public function extraFields()
    {
        return [
            'created_by',
            'invoiceContent',
            'wasteCodes',
            'missedCodes',
            'errorCodes',
        ];
    }

    /**
     * Поиск последней накладной по кодам
     * @param type $codes
     * @return type
     * @throws NotAcceptableHttpException
     */
    static function findByCodes($codes)
    {
        $res = \Yii::$app->db->createCommand(
            "select *,codes@>:codes2 as contain from invoices where :codes1 && codes ORDER by created_at desc LIMIT 1",
            [
                ":codes1" => pghelper::arr2pgarr($codes),
                ":codes2" => pghelper::arr2pgarr($codes),
            ]
        )->queryOne();
        if (empty($res)) {
            return null;
        }
        if (!$res["contain"]) {
            throw new \Exception("Коды должны быть с одной отгрузки");
        }

        $invoiceSrc = Invoice::findOne($res["id"]);

        return $invoiceSrc;
    }

    /**
     * Возвращает лишние коды для 601/416
     * @return type
     */
    public function getWasteCodes()
    {
        return $this->wasteCodes;
    }

    /**
     * Возвращает пропущенные коды для 601/416
     * @return type
     */
    public function getMissedCodes()
    {
        return $this->missedCodes;
    }

    /**
     * Возвращает массив кодов верхнего уровня с их содерижимым series, gtin, cnt
     * @return array [
     *                      'code'=>[['series' => Серия, 'gtin' => gtin, 'cnt'=>1]]
     *                      'code'=>[['series' => Серия, 'gtin' => gtin, 'cnt'=>1]]
     *               ]
     * @throws \Exception
     */
    public function getContentByCodes()
    {
        $invoice_content = [];
        foreach ($this->realcodes as $codestr) {
            $code = Code::findOneByCode($codestr);
            if (empty($code)) {
                throw new \Exception('Неизвестный код: ' . $codestr);
            }
            $invoice_content[$code->code] = $code->getContentByProduct();
        }

        return $invoice_content;
    }

    /**
     * Заполнение в накладной - полей по пропущенным кодам для сторонних кодов по прямому и обратном акцептированию
     */
    public function compare601()
    {
        $this->wasteCodes = [];
        $this->missedCodes = [];
        $this->errorCodes = "";

        //черновики
        if ($this->typeof == 1) {
            $op = \app\modules\itrack\models\Fns::findOne(
                [
                    'operation_uid' => \app\modules\itrack\models\Fns::OPERATION_601,
                    'data'          => \app\modules\itrack\components\pghelper::arr2pgarr(
                        $this->invoice_number,
                        $this->invoice_date
                    )
                ]
            );
            if (!empty($op)) {
                //парсим список кодов....
                //$cdata = \app\modules\itrack\components\pghelper::pgarr2arr($op["codes"]);
                $this->wasteCodes = array_diff($this->codes, $op->codes);
                $this->missedCodes = array_diff($op->codes, $this->codes);
            } else {
                //не нашли накладной - помечаем все коды в waste
                $this->wasteCodes = $this->codes;
            }
        } //карантин
        elseif ($this->typeof == 2) {
            $op = \app\modules\itrack\models\Fns::findOne(
                [
                    'operation_uid' => \app\modules\itrack\models\Fns::OPERATION_416,
                    'data'          => \app\modules\itrack\components\pghelper::arr2pgarr(
                        $this->invoice_number,
                        $this->invoice_date
                    )
                ]
            );
            if (!empty($op)) {
                $this->errorCodes = $op->note;
            } else {
                //не нашли накладной - помечаем все коды в waste
                $this->wasteCodes = $this->codes;
            }
        }
    }

    /**
     * Создание накладной -картанин + 416 документ для последующей обработки...
     *
     * @param array $codes
     * @param type $invoice
     * @param type $invoiceDate
     * @return string|\self
     */
    static function createQuarantine(array $codes, $invoice, $invoiceDate)
    {
        $model = new self;

        $result = ["0", "OK"];
        $model->scenario = "temp";
        if ($model->load(
            [
                //'realcodes' => $codes,
                'codes'          => $codes,
                'typeof'         => 2,
                'invoice_number' => $invoice,
                'invoice_date'   => $invoiceDate,
            ],
            ''
        )) {
            if ($model->save()) {
                $model->refresh();
            } else {
                $result = ["1", "Ошибка сохранения накладной"];
            }
        } else {
            $result = ["1", "Ошибка сохранения накладной"];
        }

        //        $fns = Fns::find()->andWhere(['operation_uid' => Fns::OPERATION_416, 'state' => Fns::STATE_CREATING, 'data' => \app\modules\itrack\components\pghelper::arr2pgarr([$invoice,$invoiceDate])])->one();
        //        if(empty($fns))
        $fns = new Fns();
        $fns->operation_uid = Fns::OPERATION_416;
        $fns->state = Fns::STATE_CREATING;
        $fns->invoice_uid = $model->id;
        $fns->created_by = \Yii::$app->user->getIdentity()->id;
        $fns->codes = \app\modules\itrack\components\pghelper::arr2pgarr($codes);
        $fns->data = \app\modules\itrack\components\pghelper::arr2pgarr(
            [
                $invoice,
                $invoiceDate,
            ]
        );
        $fns->indcnt = count($codes);
        $fns->object_uid = \Yii::$app->user->getIdentity()->object_uid;
        $fns->save(false);

        return $result;
    }

    /**
     * Возвращает все входящие коды в накладную
     * @return type
     */
    public function getInvoiceContent()
    {
        $result = [];
        $sql = Yii::$app->db->createCommand(
            "
                            select code,parent_code,flag,childrens,series,expdate,tnved,codetype_uid,
                              is_empty(flag) as empty,
                              is_claim(flag) as claim,
                              is_removed(flag) as removed,
                              is_released(flag) as released,
                              is_defected(flag) as defected,
                              is_retail(flag) as retail,
                              is_blocked(flag) as blocked,
                              is_paleta(flag) as paleta,
                              is_gover(flag) as gover,product.series,nomenclature.name
                            from _get_codes_array('" . (!empty($this->codes) ? $this->codes : "{}") . "') as codes
				LEFT JOIN generations ON generations.id = generation_uid
                                left join product on codes.product_uid=product.id
                                left join nomenclature on nomenclature_uid = nomenclature.id
        "
        );
        $result = $sql->queryAll();
        foreach ($result as $code) {
            if (!empty($code["childrens"])) {
                $result = array_merge(
                    $result,
                    \Yii::$app->db->createCommand(
                        "select code,parent_code,flag,childrens,series,expdate,tnved,codetype_uid,
                              is_empty(flag) as empty,
                              is_claim(flag) as claim,
                              is_removed(flag) as removed,
                              is_released(flag) as released,
                              is_defected(flag) as defected,
                              is_retail(flag) as retail,
                              is_blocked(flag) as blocked,
                              is_paleta(flag) as paleta,
                              is_gover(flag) as gover,product.series,nomenclature.name
                            from _get_codes_array(:codes) as codes
				LEFT JOIN generations ON generations.id = generation_uid
                                left join product on codes.product_uid=product.id
                                left join nomenclature on nomenclature_uid = nomenclature.id
                            
                    ",
                        [":codes" => $code["childrens"]]
                    )->queryAll()
                );
            }
        }

        return $result;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::className(), ['id' => 'object_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNewObject()
    {
        return $this->hasOne(Facility::className(), ['id' => 'newobject_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * Лог обмена данными с аксаптой
     * @param type $message
     * @param type $level
     */
    static private function log($message, $level = Logger::LEVEL_INFO)
    {
        Yii::getLogger()->log($message, $level, 'axapta');
    }

    public function updateVendor($needSave = true)
    {
        $invoice_codes = [];
        $i = \Yii::$app->params["invoice"];
        $axapta = connectors\Connector::getActive(['Axapta'], 1, $this->object_uid);
        if (!empty($axapta)) {
            $i = [
                "check"  => true,
                "login"  => $axapta->data["user"],
                'passwd' => $axapta->data["password"],
                'url'    => $axapta->data["url"]
            ];
        }
        if (empty($i) || !isset($i["check"]) || $i["check"] != true) {
            return;
        }
        try {
            $this->log('Checking: ' . $this->invoice_number);
            $invoice_params = $i;
            define('USERPWD', $invoice_params["login"] . ':' . $invoice_params["passwd"]);
            stream_wrapper_unregister("http");
            stream_wrapper_register("http", "app\modules\itrack\components\NTLMStream");
            $params = [
                'stream_context'     => stream_context_create(
                    [
                        'ssl' => [
                            'ciphers'           => 'RC4-SHA',
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ]
                    ]
                ),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'soap_version'       => SOAP_1_1,
                'trace'              => 1,
                'connection_timeout' => 180,
                'features'           => SOAP_SINGLE_ELEMENT_ARRAYS
            ];
            $soap = new NTLMSoapClient($invoice_params["url"], $params);
            $data = $soap->getVendInvoice(
                array(
                    "_invoiceId"   => $this->invoice_number,
                    "_invoiceDate" => $this->invoice_date,
                    "_inn"         => $this->dest_inn
                )
            );
        } catch (\Exception $ex) {
            error_clear_last();
            $this->vatvalue = 'Ошибка получения данных по накладной';
            $this->log('Error: ' . $this->invoice_number . " (Soap Exception: " . $ex->getMessage() . ")");

            return;
        }
        $this->log('Received: ' . print_r($data, true));

        $data = get_object_vars($data);
        $response = get_object_vars($data["response"]);
        if ($response["result"]) {
            $this->vatvalue = "";
            $series = [];
            if (is_object(
                    $response['InvoiceTrans']
                ) && isset($response['InvoiceTrans']->NV_WSDLIntVendInvoiceTransContract) && is_array(
                    $response['InvoiceTrans']->NV_WSDLIntVendInvoiceTransContract
                )) {
                foreach ($response['InvoiceTrans']->NV_WSDLIntVendInvoiceTransContract as $o) {
                    $series[$o->SerialId] = ['Price' => $o->Price, 'VatValue' => $o->VatValue, 'GTIN' => $o->GTIN];
                    if (!isset($invoice_codes[$o->SerialId])) {
                        $invoice_codes[$o->SerialId] = 0;
                    }
                    if (isset($o->Qty)) {
                        $invoice_codes[$o->SerialId] += floatval($o->Qty);
                    }
                }
            }
            if (empty($this->dest_fns)) {
                $this->dest_fns = $response["receiverId"];
            }
            $this->contract_type = empty($response["agreementType"]) ? "1" : $response["agreementType"];
            $this->turnover_type = ($response["recievedType"] == 'Recieved') ? '1' : '2';
            $this->cust_name = $response["vendName"];

            $this->cost = serialize($series);
            $this->codes_cnt = 0;
            foreach ($invoice_codes as $cc) {
                $this->codes_cnt += ceil($cc);
            }
        } else {
            $this->vatvalue = 'Нет данных по накладной во внешней системе';
        }
        if ($needSave) {
            $this->save(false);
        }
    }


    /**
     * Запрос к аксапте для получения данных по накладной
     * @param type $invoice_number
     * @return type
     */
    static function axGetInvoice($invoice_number, $axapta)
    {
        static::log('Checking: ' . $invoice_number);

        if (!defined('USERPWD')) {
            define('USERPWD', $axapta->data["user"] . ':' . $axapta->data["password"]);
        }
        stream_wrapper_unregister("http");
        stream_wrapper_register("http", "app\modules\itrack\components\NTLMStream");
        $params = [
            'stream_context'     => stream_context_create(
                [
                    'ssl' => [
                        'ciphers'           => 'RC4-SHA',
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ]
                ]
            ),
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'soap_version'       => SOAP_1_1,
            'trace'              => 1,
            'connection_timeout' => 180,
            'features'           => SOAP_SINGLE_ELEMENT_ARRAYS
        ];
        $soap = new NTLMSoapClient($axapta->data['url'], $params);
        $data = $soap->getInvoice(array("_invoiceId" => $invoice_number));

        static::log('Received: ' . print_r($data, true));

        return $data;
    }

    /*
     * Проверка ответа аксапты на наличие трансферных данных по накладной
     */
    static function checkTransfer($invoice_number, $invoice_date)
    {
        $ret = ['current' => [], "transfers" => [], 'sender' => ''];
        $i = \Yii::$app->params["invoice"];
        $axapta = connectors\Connector::getActive(['Axapta'], 1);
        if (!empty($axapta)) {
            $i = [
                "check"  => true,
                "login"  => $axapta->data["user"],
                'passwd' => $axapta->data["password"],
                'url'    => $axapta->data["url"]
            ];
        }
        if (empty($i) || !isset($i["check"]) || $i["check"] != true) {
            return $ret;
        }
        try {
            $data = static::axGetInvoice($invoice_number, $axapta);
            $data = get_object_vars($data);
            $response = get_object_vars($data["response"]);
            if ($response['result']) {
                $series = [];
                if (is_object(
                        $response["InvoiceTrans"]
                    ) && isset($response["InvoiceTrans"]->NV_WSDLIntegrationInvoiceTransContract) && is_array(
                        $response["InvoiceTrans"]->NV_WSDLIntegrationInvoiceTransContract
                    )) {
                    foreach ($response["InvoiceTrans"]->NV_WSDLIntegrationInvoiceTransContract as $o) {
                        if (!isset($series[$o->SerialId])) {
                            $series[$o->SerialId] = 0;
                        }
                        if (isset($o->Qty)) {
                            $series[$o->SerialId] += ceil(floatval($o->Qty));
                        }
                    }
                }
                if (isset($response["TransferSources"]->TransferSource)) {
                    $sender_id = (string)$response["Sender_Id"];
                    $transfers = $response["TransferSources"]->TransferSource;
                    foreach ($transfers as $tr) {
                        $det = [];
                        if (isset($tr->SourceDetails) && is_array($tr->SourceDetails->SourceTransferDetail)) {
                            foreach ($tr->SourceDetails->SourceTransferDetail as $std) {
                                if (!isset($det[$std->GTIN . $std->SerialId])) {
                                    $det[$std->GTIN . '/' . $std->SerialId] = 0;
                                }
                                $det[$std->GTIN . '/' . $std->SerialId] += $std->Qty;
                            }
                        }

                        $ret["transfers"][] = [
                            "CodeQty" => $tr->CodeQty,
                            'invoice' => (string)$tr->InvoiceSource,
                            'detail'  => $det
                        ];
                    }
                }
                $ret["current"] = $series;
                $ret["sender"] = (string)$response["Sender_Id"];
            } else {
                throw new \yii\web\BadRequestHttpException ('Нет данных по накладной во внешней системе');
            }
        } catch (\Exception $ex) {
            static::log('Error: ' . $invoice_number . " (Soap Exception: " . $ex->getMessage() . ")");
            error_clear_last();
            throw new \yii\web\BadRequestHttpException('Ошибка получения данных по накладной');
        }

        return $ret;
    }

    /**
     * Проверка накладной по аксапте для перемещения
     *
     * @param type $needSave
     * @param type $codes_cnt
     * @return type
     */
    public function updateTransfer($needSave = true, $codes_cnt = [])
    {
        $invoice_codes = [];
        $this->infotxt = "";
        $this->vatvalue = "";

        $i = \Yii::$app->params["invoice"];
        $axapta = connectors\Connector::getActive(['Axapta'], 1, $this->object_uid);
        if (!empty($axapta)) {
            $i = [
                "check"  => true,
                "login"  => $axapta->data["user"],
                'passwd' => $axapta->data["password"],
                'url'    => $axapta->data["url"]
            ];
        }
        if (empty($i) || !isset($i["check"]) || $i["check"] != true) {
            return;
        }
        try {
            $this->log('Checking: ' . $this->invoice_number);
            $invoice_params = $i;
            if (!defined('USERPWD')) {
                define('USERPWD', $invoice_params["login"] . ':' . $invoice_params["passwd"]);
            }
            stream_wrapper_unregister("http");
            stream_wrapper_register("http", "app\modules\itrack\components\NTLMStream");
            $params = [
                'stream_context'     => stream_context_create(
                    [
                        'ssl' => [
                            'ciphers'           => 'RC4-SHA',
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ]
                    ]
                ),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'soap_version'       => SOAP_1_1,
                'trace'              => 1,
                'connection_timeout' => 180,
                'features'           => SOAP_SINGLE_ELEMENT_ARRAYS
            ];
            $soap = new NTLMSoapClient($invoice_params['url'], $params);
            $data = $soap->getTransfer(array('_invoiceId' => $this->invoice_number));
        } catch (\Exception $ex) {
            error_clear_last();
            $this->vatvalue = 'Ошибка получения данных по накладной';
            $this->log('Error: ' . $this->invoice_number . " (Soap Exception: " . $ex->getMessage() . ")");

            return;
        }
        $this->log('Received: ' . print_r($data, true));

        $data = get_object_vars($data);
        $response = get_object_vars($data["response"]);

        if ($response["result"]) {
            if (is_object(
                    $response["TransferTrans"]
                ) && isset($response["TransferTrans"]->NV_WSDLIntegrationTransferTransContract) && is_array(
                    $response["TransferTrans"]->NV_WSDLIntegrationTransferTransContract
                )) {
                foreach ($response["TransferTrans"]->NV_WSDLIntegrationTransferTransContract as $o) {
                    if (!isset($invoice_codes[$o->SerialId])) {
                        $invoice_codes[$o->SerialId] = 0;
                    }
                    if (isset($o->Qty)) {
                        $invoice_codes[$o->SerialId] += floatval($o->Qty);
                    }
                }
            }

            $this->dest_fns = $response["Receiver_Id"]; //проверить куда перемещение???7
            $obj = Facility::findOne(['id' => $this->newobject_uid]);
            if (empty($obj)) {
                $this->log('Error: не найден объект с ид = ' . $this->newobject_uid);
                $this->vatvalue = 'Ошибка: не найден объект с ид = ' . $this->newobject_uid;

                return;
            }
            if ($obj->fns_subject_id != trim($response["Receiver_Id"])) {
                $this->log('Error: subject_id != ' . $this->dest_fns);
                $this->vatvalue = 'Ошибка: получатель не соответствует с данными из АХ';

                return;
            }

            foreach ($codes_cnt as $res) {
                if (!isset($invoice_codes[$res["series"]])) {
                    $invoice_codes[$res["series"]] = 0;
                }
                if ($res["cnt"] > 0 && $res["cnt"] > ceil($invoice_codes[$res["series"]])) {
                    $this->vatvalue .= "\n - Количество отгружаемых кодов по серии " . $res["series"] . " больше, чем в накладной (" . $res["cnt"] . "/" . ceil(
                            $invoice_codes[$res["series"]]
                        ) . ")";
                }
                if ($res["cnt"] < ceil($invoice_codes[$res["series"]])) {
                    $this->infotxt .= "\n - Количество отгружаемых кодов по серии " . $res["series"] . " меньше, чем в накладной (" . $res["cnt"] . "/" . ceil(
                            $invoice_codes[$res["series"]]
                        ) . ")";
                }
                unset($invoice_codes[$res["series"]]);
            }
            foreach ($invoice_codes as $s => $c) {
                $this->infotxt .= "\n - Количество отгружаемых кодов по серии " . $s . " меньше, чем в накладной (0/" . ceil(
                        $c
                    ) . ")";
            }

            $this->infotxt = preg_replace("#^\n - #si", '', $this->infotxt);
            $this->vatvalue = preg_replace("#^\n - #si", '', $this->vatvalue);
        } else {
            $this->vatvalue = 'Нет данных по накладной во внешней системе';
        }

        if (empty($this->vatvalue)) {
            $this->log('Ok: ' . $this->invoice_number);
        } else {
            $this->log('Error: ' . $this->invoice_number . " " . $this->vatvalue);
        }
    }

    /**
     * Проверка накладной в АКСАПТЕ - по отгрузке
     *
     * @param bool $needSave
     * @param array $codes_cnt
     * @return array
     */
    public function updateExternal($needSave = true, $codes_cnt = []): array
    {
        $invoice_codes = [];
        $this->infotxt = '';
        $this->vatvalue = '';
        $result = [];

        $i = Yii::$app->params['invoice'];
        $axapta = Connector::getActive(['Axapta'], 1, $this->object_uid);

        if (!empty($axapta)) {
            $i = [
                'check'  => true,
                'login'  => $axapta->data['user'],
                'passwd' => $axapta->data['password'],
                'url'    => $axapta->data['url']
            ];
        }

        if (empty($i) || !isset($i['check']) || $i['check'] != true) {
            throw new Exception('Проверка накладных отключена');
        }

        try {
            $this->log('Checking: ' . $this->invoice_number);
            $invoice_params = $i;
            if (!defined('USERPWD')) {
                define('USERPWD', $invoice_params['login'] . ':' . $invoice_params['passwd']);
            }
            stream_wrapper_unregister('http');
            stream_wrapper_register('http', 'app\modules\itrack\components\NTLMStream');
            $params = [
                'stream_context'     => stream_context_create(
                    [
                        'ssl' => [
                            'ciphers'           => 'RC4-SHA',
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ]
                    ]
                ),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'soap_version'       => SOAP_1_1,
                'trace'              => 1,
                'connection_timeout' => 180,
                'features'           => SOAP_SINGLE_ELEMENT_ARRAYS
            ];
            $soap = new NTLMSoapClient($invoice_params['url'], $params);
            $data = $soap->getInvoice(array('_invoiceId' => $this->invoice_number));
        } catch (Exception $ex) {
            error_clear_last();
            $this->vatvalue = 'Ошибка получения данных по накладной';
            $this->log('Error: ' . $this->invoice_number . ' (Soap Exception: ' . $ex->getMessage() . ')');

            return [];
        }
        $this->log('Received: ' . print_r($data, true));

        $data = get_object_vars($data);
        $response = get_object_vars($data['response']);
        if ($response['result']) {
            $series = [];
            if (is_object(
                    $response['InvoiceTrans']
                ) && isset($response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract) && is_array(
                    $response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract
                )) {
                foreach ($response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract as $o) {
                    $series[$o->SerialId] = ['Price' => $o->Price, 'VatValue' => $o->VatValue];
                    if (!isset($invoice_codes[$o->SerialId])) {
                        $invoice_codes[$o->SerialId] = 0;
                    }
                    if (isset($o->Qty)) {
                        $invoice_codes[$o->SerialId] += floatval($o->Qty);
                    }
                }

                $result = ArrayHelper::cloneArray($invoice_codes);
            }
            $this->dest_address = $response['consigneeAddress'];
            $this->dest_consignee = $response['consigneeName'];
            $this->dest_settlement = $response['consigneeRegion'];
            $this->turnover_type = $response['turnover_type'];
            $this->cost = serialize($series);
            $this->dest_kpp = $response['consigneeKPP'];
            $this->dest_inn = $response['consigneeINN'];
            if (empty($this->dest_inn)) {
                $this->dest_inn = $response['custINN'];
                $this->dest_kpp = $response['custKPP'];
            }
            if (empty($this->dest_fns)) {
                $this->dest_fns = $response['Receiver_Id'];
            }
            $this->contract_type = $response['contract_type'];
            $this->source = $response['source'] ?? '';
            $this->contract_num = $response['contract_num'] ?? '';
            $this->contract_num = trim($this->contract_num);
            if (preg_match('#^.{19}[01]{1}$#si', $this->contract_num)) {
                $this->contract_num = substr($this->contract_num, 0, 19);
            }
            $this->cust_name = $response['custName'];
            $this->cust_address = $response['custAddress'];
            $this->cust_settlement = $response['custRegion'];
            $this->cust_kpp = $response['custKPP'];
            $this->cust_inn = $response['custINN'];
            $this->invoice_date = Yii::$app->formatter->asDate($response['doc_Date'], 'php:Y-m-d');
            $this->updated = true;
            if ($response['BlockMDLP'] == '1') {
                $needSave = false;
                $this->blockMdlp = true;
            }

            //а все ли серии у нас пришли
            $invoice_series = Yii::$app->db->createCommand(
                'SELECT * FROM get_series_by_invoice(:id)',
                [':id' => $this->id]
            )->queryAll();
            foreach ($invoice_series as $is) {
                if (!isset($series[$is['serie']])) {
                    try {
                        $data = $soap->getInvoiceTransList(
                            ['_invoiceId' => $this->invoice_number, '_serialId' => $is['serie']]
                        );
                    } catch (Exception $ex) {
                        $this->vatvalue = 'Ошибка получения данных по накладной';
                        $this->log('Error2: ' . $this->invoice_number . ' (Soap Exception: ' . $ex->getMessage() . ')');

                        return [];
                    }
                    $data = get_object_vars($data);
                    $response = get_object_vars($data['response']);
                    $this->log('Received be Series ' . $is['serie'] . ': ' . print_r($data, true));
                    $chseries = [];
                    if (is_object(
                            $response['InvoiceTrans']
                        ) && isset($response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract) && is_array(
                            $response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract
                        )) {
                        foreach ($response['InvoiceTrans']->NV_WSDLIntegrationInvoiceTransContract as $o) {
                            $chseries[$o->SerialId] = ['Price' => $o->Price, 'VatValue' => $o->VatValue];
                            if (!isset($invoice_codes[$o->SerialId])) {
                                $invoice_codes[$o->SerialId] = 0;
                            }
                            if (isset($o->Qty)) {
                                $invoice_codes[$o->SerialId] += floatval($o->Qty);
                            }
                        }
                    }

                    if (!empty($chseries[$is['serie']])) {
                        $series[$is['serie']] = [
                            'Price'    => $chseries[$is['serie']]['Price'],
                            'VatValue' => $chseries[$is['serie']]['VatValue']
                        ];
                        $this->cost = serialize($series);
                    } else {
                        $this->vatvalue .= 'Нет данных по серии: ' . $is['serie'] . ' | ';
                        $this->updated = false;
                        $this->log('Error: ' . $this->invoice_number . ' ' . $this->vatvalue);
                        break;
                    }
                }
            }
            $this->codes_cnt = 0;
            foreach ($invoice_codes as $cc) {
                $this->codes_cnt += ceil($cc);
            }
            foreach ($codes_cnt as $res) {
                if (!isset($invoice_codes[$res['series']])) {
                    $invoice_codes[$res['series']] = 0;
                }
                if ($res['cnt'] > 0 && $res['cnt'] > ceil($invoice_codes[$res['series']])) {
                    $this->vatvalue .= "\n - Количество отгружаемых кодов по серии " . $res['series'] . ' больше, чем в накладной (' . $res['cnt'] . '/' . ceil(
                            $invoice_codes[$res['series']]
                        ) . ')';
                }
                if ($res['cnt'] < ceil($invoice_codes[$res["series"]])) {
                    $this->infotxt .= "\n - Количество отгружаемых кодов по серии " . $res['series'] . ' меньше, чем в накладной (' . $res['cnt'] . '/' . ceil(
                            $invoice_codes[$res['series']]
                        ) . ')';
                }
                unset($invoice_codes[$res['series']]);
            }
            foreach ($invoice_codes as $s => $c) {
                $this->infotxt .= "\n - Количество отгружаемых кодов по серии " . $s . ' меньше, чем в накладной (0/' . ceil(
                        $c
                    ) . ')';
            }
            $this->infotxt = preg_replace("#^\n - #si", '', $this->infotxt);
            $this->vatvalue = preg_replace("#^\n - #si", '', $this->vatvalue);

            if (empty($this->dest_fns) && !empty($this->dest_inn) && $needSave) {
                //идентификатор ФНС в аксапте не известен
                $this->updateFromISM();
            }
        } else {
            $this->vatvalue = 'Нет данных по накладной во внешней системе';
            $this->updateTransfer($needSave);
        }

        if (SERVER_RULE != SERVER_RULE_SKLAD && $needSave) {
            $this->updated_ext_at = new Expression('now()');
            $this->save(false);
            $this->refresh();
        }

        if (empty($this->vatvalue)) {
            $this->log('Ok: ' . $this->invoice_number);
        } else {
            $this->log('Error: ' . $this->invoice_number . " " . $this->vatvalue);
        }

        return $result;
    }

    /**
     * если в аксапте данные неизвестны - то делаем запрос к МДЛП для истории
     */
    public function updateFromISM()
    {
        try {
            $connectionId = isset($this->object->uso_uid) ? $this->object->uso_uid : \Yii::$app->params['ism']['default'];
            $ism = new \app\modules\itrack\components\ISMarkirovka($connectionId);
            $result = $ism->getPartners(['inn' => $this->dest_inn, 'reg_entity_type' => 1]);
            if (is_array($result)) {
                if ($result["filtered_records_count"] == 1) {
                    //найден 1
                    //при пустом branches & safe_warehouse - делаем 552
                    if (!count($result["filtered_records"][0]["branches"]) && !count(
                            $result["filtered_records"][0]["safe_warehouses"]
                        )) {
                        if (isset($result["filtered_records"][0]["system_subj_id"]) && !empty($result["filtered_records"][0]["system_subj_id"])) {
                            $this->vatvalue = "#441-" . $result["filtered_records"][0]["system_subj_id"];
                        } else {
                            $this->vatvalue = '#552';
                        }
                    }
                } elseif ($result["filtered_records_count"] < 1) {
                    //не найдены
                    //оставляем 415, но ответ маркировки сохраняем для оповещения
                } else {
                    //найдено много - что тоже ошибка
                    //оставляем 415, но ответ маркировки сохраняем для оповещения
                }
                $this->ismdata = json_encode($result);
            } else {
                throw new \Exception("Некорректный ответ МДЛП шлюза на проверку контрагента по инн");
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }
}
