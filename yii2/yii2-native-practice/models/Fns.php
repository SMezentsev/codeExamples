<?php

namespace app\modules\itrack\models;

use app\models\sklad\models\cache\Invoices;
use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\boxy\ActiveRecord;
use app\modules\itrack\components\FnsParse;
use app\modules\itrack\components\NTLMSoapClient;
use app\modules\itrack\components\OdinS;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\components\ServerHelper;
use app\modules\itrack\components\TQS;
use app\modules\itrack\events\Fns\FnsNotifyEvent;
use app\modules\itrack\models\traits\CreateModelFromArray;
use app\modules\itrack\models\connectors\Connector;
use DateTime;
use Exception;
use SimpleXMLElement;
use \yii\db\Exception as DbException;
use Throwable;
use Yii;
use yii\base\Event;
use yii\behaviors\BlameableBehavior;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Transaction;
use yii\log\Logger;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;


/**
 * @OA\Schema(schema="app_modules_itrack_models_Fns",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example="123"),
 *          @OA\Property(property="created_by", type="integer", example=0),
 *          @OA\Property(property="state", type="integer", example=10),
 *          @OA\Property(property="code", type="string", example="*"),
 *          @OA\Property(property="product_uid", type="integer", example=null),
 *          @OA\Property(property="object_uid", type="integer", example=17),
 *          @OA\Property(property="newobject_uid", type="integer", example=null),
 *          @OA\Property(property="fns_state", type="string", example="not send"),
 *          @OA\Property(property="operation_uid", type="integer", example=34),
 *          @OA\Property(property="created_at", type="string", example="2020-03-10"),
 *          @OA\Property(property="created_time", type="string", example="10:55:09+0300"),
 *          @OA\Property(property="fns_params", ref="#/components/schemas/app_modules_itrack_models_Fns_Params"),
 *          @OA\Property(property="data", type="string", example="{рф12345, 2020-03-10}"),
 *          @OA\Property(property="invoice_uid", type="string", example=null),
 *          @OA\Property(property="fns_start_send", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="fnsid", type="string", example="111"),
 *          @OA\Property(property="products", type="string", example=null),
 *          @OA\Property(property="code_flag", type="integer", example=0),
 *          @OA\Property(property="sended_at", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="updated_at", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="internal", type="boolean", example=true),
 *          @OA\Property(property="dbschema", type="string", example="c1"),
 *          @OA\Property(property="indcnt", type="integer", example=1),
 *          @OA\Property(property="grpcnt", type="integer", example=0),
 *          @OA\Property(property="docid", type="string", example=null),
 *          @OA\Property(property="note", type="string", example=""),
 *          @OA\Property(property="is_uploaded", type="boolean", example=true),
 *          @OA\Property(property="queue", type="integer", example=0),
 *          @OA\Property(property="regen", type="boolean", example=false),
 *          @OA\Property(property="replaced", type="boolean", example=true),
 *          @OA\Property(property="upd", type="boolean", example=true),
 *          @OA\Property(property="uploaded_at", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="prev_uid", type="integer", example=null),
 *          @OA\Property(property="dirname", type="string", example="2020-03-10"),
 *          @OA\Property(property="cdt", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="paleta", type="string", example="Короб"),
 *          @OA\Property(property="l3", type="boolean", example=false),
 *          @OA\Property(property="operation", type="string", example="Продажа незарегистрированному"),
 *          @OA\Property(property="canParams", type="boolean", example=true),
 *          @OA\Property(property="fdata", type="array", @OA\Items()),
 *          @OA\Property(property="сdata", type="array", @OA\Items()),
 *          @OA\Property(property="product", ref="#/components/schemas/app_modules_itrack_models_Product"),
 *          @OA\Property(property="invoice", ref="#/components/schemas/app_modules_itrack_models_Invoice"),
 *          @OA\Property(property="canDelete", type="boolean", example=true),
 *          @OA\Property(property="object", ref="#/components/schemas/app_modules_itrack_models_Facility"),
 *          @OA\Property(property="stateinfo", type="string", example="Готов к отсылке"),
 *          @OA\Property(property="grp_cnt", type="integer", example=0),
 *          @OA\Property(property="codes_cnt", type="integer", example=1),
 *          @OA\Property(property="url", type="string", example="http://itrack-rf-api.dev-og.com/fns/123/download?tok=123"),
 *      }
 * )
 * @OA\Schema(schema="app_modules_itrack_models_Fns_Params",
 *      type="object",
 *      properties={
 *          @OA\Property(property="object_uid", type="integer", example=17),
 *          @OA\Property(property="subject_id", type="string", example="123"),
 *          @OA\Property(property="operation_date", type="string", example="2020-03-10 10:55:09+0300"),
 *          @OA\Property(property="newobject_uid", type="integer", example=null),
 *     }
 * )
 */

/**
 * This is the model class for table "{{%operations}}".
 *
 * @property int $id
 * @property int $created_by
 * @property int $state
 * @property string|null $code
 * @property string|null $codes
 * @property int|null $product_uid
 * @property int|null $object_uid
 * @property int|null $newobject_uid
 * @property string|null $fns_log
 * @property string $fns_state
 * @property int $operation_uid
 * @property string $created_at
 * @property string $created_time
 * @property string|null $fns_params
 * @property string $data
 * @property string|null $invoice_uid
 * @property string|null $fns_start_send
 * @property string|null $fnsid
 * @property string|null $codes_data
 * @property int|null $products
 * @property int $code_flag
 * @property string|null $sended_at
 * @property string|null $updated_at
 * @property bool $internal
 * @property string $dbschema
 * @property int $indcnt
 * @property int $grpcnt
 * @property string|null $docid
 * @property string|null $note
 * @property bool $is_uploaded
 * @property int $queue
 * @property bool $regen
 * @property bool $replaced
 * @property bool $upd
 * @property string $full_codes
 * @property string|null $uploaded_at
 * @property int|null $prev_uid
 * @property string $dirname
 * @property string $urlTicket
 *
 * @property Invoice $invoice
 * @property Facility $object
 * @property Product $product
 * @property User $user
 */
class Fns extends ActiveRecord
{
    use FnsParse, CreateModelFromArray;

    const FNS_VERSION = '1.35';
    const STATE_CREATING = 0;
    const STATE_CREATED = 1;
    const STATE_CHECKING = 2;
    const STATE_READY = 3;
    const STATE_SENDING = 4;
    const STATE_SENDING_SUZ = 44;
    const STATE_SENDED = 5;
    const STATE_SEND_ERROR = 6;
    const STATE_RESPONCE_PART = 7;
    const STATE_RESPONCE_SUCCESS = 8;
    const STATE_RESPONCE_ERROR = 9;
    const STATE_RECEIVED = 10;
    const STATE_COMPLETED = 11;
    const STATE_1CRECEIVED = 12;
    const STATE_1CCOMPLETED = 13;
    const STATE_1CCONFIRMED = 17;
    const STATE_1CDECLAINED = 18;
    const STATE_ERRORSTOPED = 14;
    const STATE_TQS_RECEIVED = 15;
    const STATE_TQS_COMPLETED = 16;
    const STATE_TQS_CONFIRMED = 19;
    const STATE_TQS_DECLAINED = 20;
    const STATE_FNS_WAITING = 21;
    const STATE_STOPED = 22;
    const STATE_TQS_INPUT = 23;
    const STATE_1CPREPARING = 24;
    const STATE_REFUSE = 25;
    const STATE_RESPONCE_SUCCESS_DECLINE = 88;
    const OPERATION_PACK = 'Окончательная упаковка';
    const OPERATION_PACK_ID = 9;
    const OPERATION_GROUP = 'Группировка';
    const OPERATION_GROUP_ID = 2;
    const OPERATION_UNGROUP = 'Разгруппировка';
    const OPERATION_UNGROUP_ID = 13;
    const OPERATION_GROUPADD = 'Добавление в группу';
    const OPERATION_GROUPADD_ID = 10;
    const OPERATION_GROUPSUB = 'Изъятие из группы';
    const OPERATION_GROUPSUB_ID = 3;
    const OPERATION_CONTROL = 'Изъятие контроль/архив';
    const OPERATION_CONTROL_2 = 'Изъятие декларирование/сертификация';
    const OPERATION_CONTROL_ID = 6;
    const OPERATION_EMISSION = 'Эмиссия продукции';
    const OPERATION_EMISSION_ID = 1;
    const OPERATION_INCOME = 'Приёмка';
    const OPERATION_INCOME_ID = 5;
    const OPERATION_OUTCOME = 'Передача собственнику';
    const OPERATION_OUTCOME_ID = 4;
    const OPERATION_OUTCOMERETAIL = 'Продажа';
    const OPERATION_OUTCOMERETAIL_ID = 14;
    const OPERATION_OUTCOMESELF = 'Перемещение собственное';
    const OPERATION_OUTCOMESELF_ID = 24;
    const OPERATION_WDEXT = 'Изъятие доп.';
    const OPERATION_WDEXT_ID = 8;
    const OPERATION_IMPORT_ID = 0;
    const OPERATION_IMPORTCSV_ID = -1;
    const OPERATION_IMPORT = 'Импорт из внешней системы';
    const OPERATION_DESTRUCTION_ID = 28;
    const OPERATION_DESTRUCTION = 'Уничтожение';
    const OPERATION_DESTRUCTIONACT_ID = 38;
    const OPERATION_DESTRUCTIONACT = 'Акт об уничтожении';
    const OPERATION_OUTCOMERETAILUNREG = 'Продажа незарегистрированному';
    const OPERATION_OUTCOMERETAILUNREG_ID = 34;
    const OPERATION_BACK = 'Возврат в оборот';
    const OPERATION_BACK_ID = 57;
    const OPERATION_RELABEL = 'Переупаковка';
    const OPERATION_RELABEL_ID = 58;
    const OPERATION_601 = 26;
    const OPERATION_211 = 27;
    const OPERATION_210 = 29;
    const OPERATION_415 = 14;
    const OPERATION_416 = 30;
    const OPERATION_552 = 8;
    const OPERATION_607 = 31;
    const OPERATION_605 = 32;
    const OPERATION_613 = 33;
    const OPERATION_252 = 35;
    const OPERATION_251 = 36;
    const OPERATION_606 = 37;
    const OPERATION_250 = 40;
    const OPERATION_461 = 39;
    const OPERATION_623 = 59;
    const OPERATION_615 = 60;
    const OPERATION_617 = 61;
    const OPERATION_609 = 62;
    const OPERATION_DEFAULT = 250;
    const OPERATION_1C_IN = 248;
    const OPERATION_1C_OUT = 249;
    const OPERATION_1C_RES = 254;
    const OPERATION_1C_INP = 253;
    const OPERATION_TQS_INP = 252;
    const OPERATION_UPLOADED = 250;                          //устаревшее
    const OPERATION_UPLOADED_NAME = 'Загруженный документ';  //Устаревшее

    static $file_prefix = 'fns';
    /**
     * @var string
     */
    public $urlTicket;

    public static $pages = [
        ['operation_id' => 9, 'url' => 'fns/outputFinished'],
        ['operation_id' => 1, 'url' => 'fns/output'],
        ['operation_id' => 2, 'url' => 'fns/grouping'],
        ['operation_id' => 6, 'url' => 'fns/withdrawal'],
        ['operation_id' => 3, 'url' => 'fns/groupsub'],
        ['operation_id' => 10, 'url' => 'fns/groupadd'],
        ['operation_id' => 13, 'url' => 'fns/ungroup'],
        ['operation_id' => 5, 'url' => 'fns/relabel'],
        ['operation_id' => 14, 'url' => 'fns/shipment'],
        ['operation_id' => 4, 'url' => 'fns/transfer'],
        ['operation_id' => 24, 'url' => 'fns/moving'],
        ['operation_id' => 8, 'url' => 'fns/WithdrawalOut'],
        ['operation_id' => 35, 'url' => 'fns/back'],
        ['operation_id' => 36, 'url' => 'fns/back'],
        ['operation_id' => 57, 'url' => 'fns/back'],
        ['operation_id' => 40, 'url' => 'fns/back'],
        ['operation_id' => 28, 'url' => 'fns/destruction'],
        ['operation_id' => 38, 'url' => 'fns/destructionAct'],
        ['operation_id' => 5, 'url' => 'fns/income'],
        ['operation_id' => 0, 'url' => 'fns-inp/incomingdoc'],
        ['operation_id' => self::OPERATION_601, 'url' => 'fns-inp/op601'],
        ['operation_id' => self::OPERATION_615, 'url' => 'fns-inp/op601'],
        ['operation_id' => self::OPERATION_613, 'url' => 'fns-inp/op601'],
        ['operation_id' => self::OPERATION_609, 'url' => 'fns-inp/op601'],
        ['operation_id' => 27, 'url' => 'fns-inp/op211'],
        ['operation_id' => 30, 'url' => 'fns-inp/reverseAccept'],
    ];
    public static $STATE_NAMES = [
        self::STATE_CREATING         => 'Подготавливается',
        self::STATE_CREATED          => 'Подготовлен',
        self::STATE_CHECKING         => 'Готов к отсылке',
        self::STATE_READY            => 'Ожидает отправки',
        self::STATE_SENDING          => 'Отправляется',
        self::STATE_SENDED           => 'Отправлен',
        self::STATE_SENDING_SUZ      => 'Отправлен в СУЗ',
        self::STATE_SEND_ERROR       => 'Ошибка отправки',
        self::STATE_RESPONCE_PART    => 'Документ принят частично',
        self::STATE_RESPONCE_SUCCESS => 'Документ принят',
        self::STATE_RESPONCE_ERROR   => 'Документ отклонен',
        self::STATE_RECEIVED         => 'Получен',
        self::STATE_COMPLETED        => 'Обработан',
        self::STATE_ERRORSTOPED      => 'Остановлен из-за ошибок',

        self::STATE_1CRECEIVED  => 'Получен из 1С',
        self::STATE_1CCOMPLETED => 'Обработан из 1С',
        self::STATE_1CCONFIRMED => 'Принят 1С',
        self::STATE_1CDECLAINED => 'Отклонен 1С',
        self::STATE_1CPREPARING => 'Подготавливается',

        self::STATE_TQS_RECEIVED             => 'Получен для TQS',
        self::STATE_TQS_COMPLETED            => 'Отправлен в TQS',
        self::STATE_TQS_CONFIRMED            => 'Принят TQS',
        self::STATE_TQS_DECLAINED            => 'Отклонен TQS',
        self::STATE_STOPED                   => 'Готов к повторной отсылке',
        self::STATE_FNS_WAITING              => 'Уточняются коды в МДЛП',
        self::STATE_REFUSE                   => 'Отозван',
        self::STATE_RESPONCE_SUCCESS_DECLINE => 'Документ принят, создана заявка на отмену',
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'operations';
    }

    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }

    static function par_attributes()
    {
        return parent::attributes();
    }

    /**
     * Поиск последней операции по заданной накладной и типу  +пересечение codes
     *
     * @param array $types
     * @param string $invoice
     * @param int $days
     *
     * @return type
     */
    static function findByTypeInvoice(array $types, string $invoice, array $codes = [], int $days = 50)
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            return Yii::$app->db_main->createCommand(
                "SELECT * FROM operations
                                    LEFT JOIN invoices ON operations.invoice_uid = invoices.id
                                    WHERE operations.created_at>:date1 and invoice_number=:invoice
                                    and operation_uid in (" . implode(",", $types) . ")
                                        " . (!empty($codes) ? " and full_codes && '" . pghelper::arr2pgarr(
                        $codes
                    ) : "") . "'
                                   ORDER by operations.id desc",
                [
                    ':date1'   => date('Y-m-d', time() - 3600 * 24 * $days),
                    ':invoice' => $invoice,
                ]
            )->queryOne();
        }

        return Yii::$app->db->createCommand(
            "SELECT * FROM operations
                                    LEFT JOIN invoices ON operations.invoice_uid = invoices.id
                                    WHERE operations.created_at>:date1 and invoice_number=:invoice
                                    and operation_uid in (" . implode(",", $types) . ")
                                        " . (!empty($codes) ? " and full_codes && '" . pghelper::arr2pgarr(
                    $codes
                ) : "") . "'
                                   ORDER by operations.id desc",
            [
                ':date1'   => date('Y-m-d', time() - 3600 * 24 * $days),
                ':invoice' => $invoice,
            ]
        )->queryOne();
    }

    /**
     * @param array $connections
     *
     * @return array
     * @throws DbException
     */
    public static function getReadyOperations(array $connections): array
    {
        return Yii::$app->db->createCommand(
            "
                SELECT operations.*, objects.uso_uid, prev.state as prev_state, (operations.updated_at>now()) as now
                FROM operations
                    LEFT JOIN objects ON (objects.id = operations.object_uid)
                    LEFT JOIN operations as prev ON (operations.prev_uid = prev.id)
                WHERE operations.dbschema = get_constant('schema')
                    AND operations.internal = true
                    AND operations.state = :st
                    AND objects.uso_uid = ANY(:uso_uids)
                    AND (operations.created_at >= (current_date - 60) or operations.operation_uid = :op)
                ORDER by operations.fns_start_send nulls last, operations.created_at, operations.created_time
                FOR UPDATE OF operations",
            [
                ':st'       => Fns::STATE_READY,
                ':op'       => Fns::OPERATION_EMISSION_ID,
                ':uso_uids' => pghelper::arr2pgarr($connections),
            ]
        )->queryAll();
    }

    /**
     * если задана автоотсылка документов, без проверки на данном документе, переводим док в готов к отсылке
     * исключение операции в $this->ops
     *
     * @param       $ops
     * @param array $connections
     *
     * @throws DbException
     */
    public static function applyOperationToReady($ops, array $connections)
    {
        Yii::$app->db->createCommand(
            'UPDATE operations '
            . 'SET state=:st1 '
            . "WHERE dbschema = get_constant('schema') and operation_uid not in (" . implode(",", $ops) . ")
                and internal=true and state in (:st2) and object_uid in (select id from objects where fns_auto=true and uso_uid=ANY(:uso_uids))",
            [
                ':st1'      => Fns::STATE_READY,
                ':st2'      => Fns::STATE_CREATED,
                ':uso_uids' => pghelper::arr2pgarr($connections),
            ]
        )->execute();

        // у остальных доков меняем подготовлен -> проверка
        Yii::$app->db->createCommand(
            'UPDATE operations ' .
            'SET state=:st1 ' .
            "WHERE dbschema = get_constant('schema')
                    and internal=true
                    and state=:st2
                    and(operation_uid in (" . implode(",", $ops) . ")
                    or object_uid in (select id from objects where fns_auto=false and uso_uid = ANY(:uso_uids)))",
            [
                ':st1'      => Fns::STATE_CHECKING,
                ':st2'      => Fns::STATE_CREATED,
                ':uso_uids' => pghelper::arr2pgarr($connections),
            ]
        )->execute();
    }

    public static function getStateInfo($info = null)
    {
        return static::$STATE_NAMES[$info] ?? 'Unknown';
    }

    static function statuses($type = 'fns')
    {
        $ret = [];
        switch ($type) {
            case 'fns':
                $op_uid = Yii::$app->request->getQueryParam('operation_uid') ?? null;

                if (!in_array(
                    $op_uid,
                    [self::OPERATION_OUTCOME_ID, self::OPERATION_INCOME_ID, self::OPERATION_OUTCOMERETAIL_ID]
                )) {
                    $ret[] = ['id' => static::STATE_CREATING, 'name' => static::getStateInfo(static::STATE_CREATING)];
                }
                $ret[] = ['id' => static::STATE_CREATED, 'name' => static::getStateInfo(static::STATE_CREATED)];
                $ret[] = ['id' => static::STATE_CHECKING, 'name' => static::getStateInfo(static::STATE_CHECKING)];
                $ret[] = ['id' => static::STATE_READY, 'name' => static::getStateInfo(static::STATE_READY)];
                $ret[] = ['id' => static::STATE_SENDING, 'name' => static::getStateInfo(static::STATE_SENDING)];
                if ($op_uid == self::OPERATION_PACK_ID) {
                    $ret[] = [
                        'id'   => static::STATE_SENDING_SUZ,
                        'name' => static::getStateInfo(static::STATE_SENDING_SUZ)
                    ];
                }
                $ret[] = ['id' => static::STATE_SEND_ERROR, 'name' => static::getStateInfo(static::STATE_SEND_ERROR)];
                $ret[] = [
                    'id'   => static::STATE_RESPONCE_PART,
                    'name' => static::getStateInfo(static::STATE_RESPONCE_PART)
                ];
                $ret[] = [
                    'id'   => static::STATE_RESPONCE_SUCCESS,
                    'name' => static::getStateInfo(static::STATE_RESPONCE_SUCCESS)
                ];
                $ret[] = [
                    'id'   => static::STATE_RESPONCE_ERROR,
                    'name' => static::getStateInfo(static::STATE_RESPONCE_ERROR)
                ];
                $ret[] = ['id' => static::STATE_ERRORSTOPED, 'name' => static::getStateInfo(static::STATE_ERRORSTOPED)];
                //                $ret[] = ['id' => static::STATE_STOPED, 'name' => static::getStateInfo(static::STATE_STOPED)];
                break;
            case 'fnsin':
                $ret[] = ['id' => static::STATE_CREATING, 'name' => static::getStateInfo(static::STATE_CREATING)];
                $ret[] = ['id' => static::STATE_CREATED, 'name' => static::getStateInfo(static::STATE_CREATED)];
                $ret[] = ['id' => static::STATE_CHECKING, 'name' => static::getStateInfo(static::STATE_CHECKING)];
                $ret[] = ['id' => static::STATE_READY, 'name' => static::getStateInfo(static::STATE_READY)];
                $ret[] = ['id' => static::STATE_SENDING, 'name' => static::getStateInfo(static::STATE_SENDING)];
                if ($op_uid == self::OPERATION_601) {
                    $ret[] = [
                        'id'   => static::STATE_FNS_WAITING,
                        'name' => static::getStateInfo(static::STATE_FNS_WAITING)
                    ];
                }
                $ret[] = [
                    'id'   => static::STATE_RESPONCE_SUCCESS,
                    'name' => static::getStateInfo(static::STATE_RESPONCE_SUCCESS)
                ];
                $ret[] = [
                    'id'   => static::STATE_RESPONCE_ERROR,
                    'name' => static::getStateInfo(static::STATE_RESPONCE_ERROR)
                ];
                $ret[] = ['id' => static::STATE_RECEIVED, 'name' => static::getStateInfo(static::STATE_RECEIVED)];
                $ret[] = ['id' => static::STATE_COMPLETED, 'name' => static::getStateInfo(static::STATE_COMPLETED)];
                $ret[] = ['id' => static::STATE_REFUSE, 'name' => static::getStateInfo(static::STATE_REFUSE)];
                //                $ret[] = ['id' => static::STATE_STOPED, 'name' => static::getStateInfo(static::STATE_STOPED)];

                break;
            case 'tqs':
                $ret[] = [
                    'id'   => static::STATE_TQS_RECEIVED,
                    'name' => static::getStateInfo(static::STATE_TQS_RECEIVED)
                ];
                $ret[] = [
                    'id'   => static::STATE_TQS_COMPLETED,
                    'name' => static::getStateInfo(static::STATE_TQS_COMPLETED)
                ];
                $ret[] = [
                    'id'   => static::STATE_TQS_CONFIRMED,
                    'name' => static::getStateInfo(static::STATE_TQS_CONFIRMED)
                ];
                $ret[] = [
                    'id'   => static::STATE_TQS_DECLAINED,
                    'name' => static::getStateInfo(static::STATE_TQS_DECLAINED)
                ];
                break;
        }

        return $ret;
    }

    /*
     * 
     */

    /**
     * Поиск операции (отгрузки/перемещения) с данными кодами
     *
     * @param type $codes
     *
     * @return type
     */
    static function findLastOperation($codes, $operations = [])
    {
        $mindate = $maxdate = null;

        $rcodes = Yii::$app->db->createCommand(
            "
            SELECT *, date(lmtime - interval '30 days') as mindate,
                      date(lmtime + interval '1 days') as maxdate,
                      is_released(codes.flag) as released,
                      is_retail(flag) as retail
            FROM _get_codes_array(:codes) as codes",
            [
                ':codes' => pghelper::arr2pgarr($codes),
            ]
        )->queryAll();

        foreach ($rcodes as $code) {
            if (!$code['retail'] && !$code['released']) {
                throw new NotAcceptableHttpException(
                    sprintf(
                        'Код не находится в статусе отгрузки/перемещения: %d',
                        $code['code']
                    )
                );
            }  //код не отгружен операцию не ищем

            if (is_null($mindate)) {
                $mindate = $code['mindate'];
                $maxdate = $code['maxdate'];
            }

            if ($code['mindate'] < $mindate) {
                $mindate = $code['mindate'];
            }

            if ($code['maxdate'] > $maxdate) {
                $maxdate = $code['maxdate'];
            }
        }

        $q = self::find();

        if (!empty($operations)) {
            $q->andWhere(['operation_uid' => $operations]);
        }

        $q->andWhere(new Expression("created_at >= '$mindate'"));
        $q->andWhere(new Expression("created_at <= '$maxdate'"));
        $q->andWhere(new Expression("'" . pghelper::arr2pgarr($codes) . "' && full_codes"));
        $q->orderBy(['created_at' => SORT_DESC, 'created_time' => SORT_DESC]);
        $q->limit(1);

        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            $res = Yii::$app->db_main->createCommand($q->createCommand()->getRawSql())->queryOne();
            if (!empty($res)) {
                $m = self::instantiate($res);
                self::populateRecord($m, $res);

                return $m;
            }

            return null;
        }

        return $q->one();
    }

    public static function create1Cin($params)
    {
        $fns = new static();
        $fns->operation_uid = static::OPERATION_1C_INP;
        $fns->state = static::STATE_1CCOMPLETED;
        $fns->created_by = $params['created_by'];
        $fns->docid = $params['docId'];
        $fns->object_uid = $params['object']->id;
        $fns->data = pghelper::arr2pgarr(
            [
                serialize(
                    [
                        'docId'   => $params['docId'],
                        'docNum'  => $params['docNum'],
                        'docDate' => $params['docDate'],
                        'type'    => $params['type'],
                    ]
                ),
            ]
        );
        $fns->save(false);
        $fns->refresh();

        file_put_contents($fns->getFileName(), $params['body']);

        return $fns;
    }

    public static function create1Cinput($params)
    {
        $fns = new static();
        $fns->operation_uid = static::OPERATION_1C_IN;
        $fns->state = static::STATE_1CRECEIVED;
        $fns->created_by = $params['created_by'];
        $fns->save(false);
        $fns->refresh();

        file_put_contents($fns->getFileName(), $params['body']);

        return $fns;
    }

    /**
     * Создание нового импорт документа FNS
     *
     * @param string $body - xml документ
     *
     * @return Fns|null
     */
    static function createImport($body)
    {
        try {
            $xml = new SimpleXMLElement($body);

            $model = new static(
                [
                    'state'         => static::STATE_RECEIVED,
                    'created_by'    => 0,
                    'internal'      => false,
                    'operation_uid' => static::OPERATION_IMPORT_ID,
                ]
            );
            $model->save();
            $model->refresh();

            if ($xml->asXML($model->getFileName()) === false) {
                throw new BadRequestHttpException('Ошибка сохранения файла');
            }

            return $model;
        } catch (Exception $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
    }

    /**
     * Создание фнс дока, его поиск и возврат
     * так как таблица партиуированная returning * не работает после создания id не известен - рефреш не помогает
     * делаем отдельный поиск
     *
     * @param Expression $params
     *
     * @return Fns
     */
    static function createDoc($params)
    {
        $fns = new Self;

        if (!isset($params['created_at'])) {
            $params['created_at'] = new Expression('timeofday()::timestamptz::date');
        }
        if (!isset($params['created_time'])) {
            $params['created_time'] = new Expression('timeofday()::timestamptz::timetz');
        }

        $fns->load($params, '');

        if (isset($params['codes'])) {
            $fns->upd_product_uid(pghelper::pgarr2arr($params['codes']));
        }

        $fns->save(false);
        $fns->refresh();

        return $fns;
    }

    public static function create1C($params)
    {
        $fns = new static();
        $fns->operation_uid = static::OPERATION_1C_RES;
        $fns->state = static::STATE_1CRECEIVED;
        $fns->created_by = Yii::$app->user->getIdentity()->id;
        $fns->data = pghelper::arr2pgarr(
            [
                serialize(
                    [
                        'docId'      => $params['docId'],
                        'docNum'     => $params['docNum'],
                        'docDate'    => $params['docDate'],
                        'QR'         => $params['QR'],
                        'resultCode' => $params['resultCode'],
                        'errors'     => $params['errors'],
                        'type'       => $params['type'],
                    ]
                )
            ]
        );
        $fns->docid = $params['docId'];
        $fns->save(false);
        $fns->refresh();

        $xml = new SimpleXMLElement(
            '<document version="1.0" type="' . $params['type'] . '" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema"></document>'
        );
        $xml->addChild('docId', $params['docId']);
        $xml->addChild('resultCode', $params['resultCode']);
        if ($params['resultCode'] != 0 && is_array($params['errors'])) {
            $er = $xml->addChild('errors');
            foreach ($params['errors'] as $err) {
                $er->addChild('error', $err);
            }
        }
        $xml->saveXML($fns->getFileName());

        return $fns;
    }

    static function find()
    {
        return parent::find()
            ->select(
                new Expression(
                    "operations.*,case when operations.upd THEN operations.updated_at ELSE operations.created_at + operations.created_time end as cdt,is_paleta(operations.code_flag) as paleta,is_l3(operations.code_flag) as l3"
                )
            )
            ->andWhere(new Expression("operations.dbschema = get_constant('schema')"));
    }

    /**
     * Проверка накладных, для изменения типа документа с 415 на 441
     *
     * обновление данных по накладным. если в накладной нет идентификатора получаетля фнс, то документ меняет идентификатор с 415 на 441
     *
     */
    static function invoices415to441()
    {
        echo 'Проверка накладных' . PHP_EOL;

        $invoices = Invoice::find()->andWhere(
            new Expression(
                "updated = false and created_at + interval '30 days' > now() and updated_ext_at + interval '1 hour' < now()"
            )
        )->all();

        foreach ($invoices as $invoice) {
            echo $invoice->invoice_number . PHP_EOL;

            try {
                $invoice->updateExternal(false);
                $invoice->updated_ext_at = new Expression('now()');
                $invoice->save(false, ['updated_ext_at']);
                //узнаем фнс док по этой накладной
                $ops = Fns::find()->andWhere(['invoice_uid' => $invoice->id])->andWhere(
                    ['=', 'operation_uid', Fns::OPERATION_OUTCOMERETAIL_ID]
                )->all();
                foreach ($ops as $op) {
                    try {
                        if (empty($invoice->dest_fns) && !empty($invoice->dest_inn)) {
                            $invoice->updateFromISM();
                        }

                        if ($invoice->blockMdlp) {
                            $invoice->updated = false;
                            if ($op->state == Fns::STATE_READY) {
                                $op->state = Fns::STATE_CHECKING;
                                $op->save(false, ['state']);
                            }
                        }
                        $invoice->save(false);

                        if (preg_match('@^\#441\-(.*)$@', $invoice->vatvalue, $match)) {
                            $regNum = $match[1];

                            //стираем старый документ и генерим новый
                            @unlink($op->getFileName());

                            $op->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
                            $op->fnsid = '441';
                            $params = unserialize($op->fns_params);
                            if (empty($params)) {
                                $params = $op->createParams();
                            }
                            $params['regNum'] = $regNum;
                            $op->fns_params = serialize($params);
                            $op->save(false);

                            file_put_contents($op->getFileName(), $op->xml());
                            //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                            @chmod($op->getFileName(), 0666);
                        } elseif ($invoice->vatvalue == '#552') {
                            //стираем старый документ и генерим новый
                            @unlink($op->getFileName());

                            $op->operation_uid = static::OPERATION_WDEXT_ID;
                            $op->fnsid = '552';
                            $op->data = pghelper::arr2pgarr(
                                [
                                    'ext9',
                                    isset($invoice->invoice_number) ? $invoice->invoice_number : '',
                                    isset($invoice->invoice_date) ? $invoice->invoice_date : ''
                                ]
                            );
                            $op->note = 'Вывод из оборота КИЗ, накопленных в рамках эксперимента';
                            $op->save(false);

                            file_put_contents(
                                $op->getFileName(),
                                $op->xml(
                                    [
                                        'withdrawal_type' => 14,
                                        'doc_num'         => isset($invoice->invoice_number) ? $invoice->invoice_number : '',
                                        'doc_date'        => isset($invoice->invoice_date) ? $invoice->invoice_date : ''
                                    ]
                                )
                            );
                            //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                            @chmod($op->getFileName(), 0666);
                        } elseif (empty($invoice->vatvalue)) {
                            //апдейтнулось успешно
                            if (empty($invoice->dest_fns)) {
                                //стираем старый документ и генерим новый
                                @unlink($op->getFileName());

                                $op->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
                                $op->fnsid = '441';
                                $op->save(false);

                                file_put_contents($op->getFileName(), $op->xml());
                                //запускается от кронда, а его от рута.. а потом не перезаписать через веб
                                @chmod($op->getFileName(), 0666);
                            }
                        }
                    } catch (Exception $ex) {
                        echo 'Ошибка обработки накладной :' . $ex->getMessage();
                    }
                }
            } catch (Exception $ex) {
            }
        }
    }

    /**
     * Изменение статуса доков при изъятии
     *
     *  берем только операции с контроль/архив - берем все индивидуальные коды из этих операций
     *  и по товарной карте проводим все 9
     *
     * @return void
     */
    static function check4endOfpacking()
    {
        $op = Yii::$app->db->createCommand(
            "
                        SELECT * FROM operations
                        WHERE dbschema = get_constant('schema') AND internal=true AND state=:state AND operation_uid=:op and fns_start_send is null
                        ORDER by created_at,created_time,id",
            [
                ':state' => Fns::STATE_CREATING,
                ':op'    => Fns::OPERATION_CONTROL_ID,
            ]
        )->queryAll();

        foreach ($op as $operation) {
            $data = pghelper::pgarr2arr($operation['data']);

            switch ($operation['operation_uid']) {
                case Fns::OPERATION_CONTROL_ID:
                    if (in_array($data[0], [1, 2])) {
                        Yii::$app->db->createCommand(
                            "
                                    UPDATE operations
                                    SET state = :st1, fns_start_send = now()
                                    WHERE dbschema = get_constant('schema') AND
                                          internal = true AND
                                          state = :st2 AND
                                          operation_uid = :op AND
                                          product_uid = :pr AND
                                          queue = :queue",
                            [
                                ':st1'   => Fns::STATE_CREATED,
                                ':st2'   => Fns::STATE_CREATING,
                                ':op'    => Fns::OPERATION_PACK_ID,
                                ':pr'    => $operation['product_uid'],
                                ':queue' => $operation['queue'],
                            ]
                        )->execute();
                    }
                    break;
            }
        }
    }

    /**
     * Функция -смены статуса с 0(подготавливается) на 1(подготовлен)
     *
     * Проверяет все документы, если доки в статусе 0, а док с такой же товаркаой у операцией упаковка уже сменил статус, то меняем и у этого дока статус
     *
     * @return void
     * @throws DbException
     */
    static function checkStatusesForEndPacking()
    {
        $trans = Yii::$app->db->beginTransaction(Transaction::SERIALIZABLE);

        $op = Yii::$app->db->createCommand(
            "
                    SELECT operations.*,invoices.updated as inv
                        FROM operations
                        LEFT JOIN invoices ON (operations.invoice_uid = invoices.id)
                        WHERE dbschema = get_constant('schema') AND ((state=:st AND internal=true AND operation_uid NOT IN (1,30)) or (state=:st2 and internal=true and operation_uid=1 and fns_start_send is null))
                        ORDER by operations.created_at,operations.created_time,operations.id",
            [
                ':st'  => Fns::STATE_CREATING,
                ':st2' => Fns::STATE_CREATED,
            ]
        )->queryAll();

        $buf_products = [];

        foreach ($op as $operation) {
            if (OdinS::canEndPacking($operation['fnsid'])) {
                continue;
            }

            $products = [];

            if (empty($operation['product_uid'])) {
                $p = pghelper::pgarr2arr($operation['products']);
                if (empty($p)) {
                    $products = static::getProductsForFns($operation['codes'], $operation['id']);
                } else {
                    foreach ($p as $v) {
                        $products[$v] = 1;
                    }
                }
            } else {
                $products = [$operation['product_uid'] => 1];
            }

            $send = true;

            //ищем по всем товаркам операцию упаковки, если найдем хоть одну не готовую - то не отправляем
            if (is_array($products)) {
                foreach ($products as $product => $v) {
                    if (isset($buf_products[$product])) {
                        $send = $buf_products[$product];
                    } else {
                        //фича с товарками - если разный queue  - операция может подвиснуть...
                        //т.е. если у нас операция с несколькими товарками - у нее queue = 0 и значит проверка не на своем queue пройдет - хотя смешанные операции и не должны проверяться
                        //dbschema = get_constant('schema') and
                        $ops = Yii::$app->db->createCommand(
                            "
                                SELECT * FROM operations
                                WHERE state=:st AND operation_uid=:op AND product_uid=:pr AND internal=true AND queue=:queue",
                            [
                                ':st'    => Fns::STATE_CREATING,
                                ':op'    => Fns::OPERATION_PACK_ID,
                                ':pr'    => $product,
                                ':queue' => $operation['queue'],
                            ]
                        )->queryAll();

                        if (!empty($ops) && count($ops)) {
                            $send = false;
                            $buf_products[$product] = false;

                            break;
                        }

                        $buf_products[$product] = true;
                    }
                }
            }

            if ($send) {
                Yii::$app->db->createCommand(
                    "
                        UPDATE operations SET state = :st, fns_start_send = COALESCE(fns_start_send, timeofday()::timestamptz)
                        WHERE id = :id",
                    [
                        ':st' => Fns::STATE_CREATED,
                        ':id' => $operation['id'],
                    ]
                )->execute();
            }
        }

        $trans->commit();
    }

    /**
     * Возваращает список товарных карт по списку кодов, если задан ид операции - сохранит список для последующих проверок
     *
     * @param string $codes в формате pgarr
     * @param integer $id идентификатор документа
     *
     * @return void ассоциативный массив с товарными картами в ключах
     * @throws DbException
     */
    static function getProductsForFns(string $codes, $id = null)
    {
        $rcodes = Yii::$app->db->createCommand(
            "
                    SELECT distinct codes.product_uid
                    FROM _get_codes_array(:arr) AS codes
                    LEFT JOIN generations ON codes.generation_uid = generations.id",
            [':arr' => $codes]
        )
            ->queryAll();

        $products = [];

        foreach ($rcodes as $code) {
            if (!empty($code['product_uid'])) {
                $products[$code['product_uid']] = 1;
            }
        }

        unset($rcodes);

        Yii::$app->db->createCommand(
            'UPDATE operations SET products = :a1 WHERE id = :a2',
            [
                ':a1' => pghelper::arr2pgarr(array_keys($products)),
                ':a2' => $id,
            ]
        )->execute();
    }

    /**
     * Функция изменения статусов доков на остановлен - если предыдущие документы не были приняты УСО и вернулись с ошибкой
     * !!!не используется!!
     */
    static function stopChainOnErrors()
    {
        $op = Yii::$app->db->createCommand(
            "
                SELECT operations.*
                FROM operations
                WHERE dbschema = get_constant('schema') AND
                      state = :st AND
                      operation_uid != 1",
            [
                ':st' => Fns::STATE_CREATED,
            ]
        )->queryAll();

        foreach ($op as $operation) {
            if (empty($operation['product_uid'])) {
                $p = pghelper::pgarr2arr($operation['products']);

                if (empty($p)) {
                    $products = static::getProductsForFns($operation['codes'], $operation['id']);
                } else {
                    foreach ($p as $v) {
                        $products[$v] = 1;
                    }
                }
            } else {
                $products = [$operation['product_uid'] => 1];
            }

            $send = true;

            foreach ($products as $product => $v) {
                $ops = Yii::$app->db->createCommand(
                    '
                    SELECT *
                    FROM operations
                    WHERE queue=:queue AND
                          state in (:st, :st2, :st3, :st4) AND
                          product_uid = :pr AND
                          internal = true',
                    [
                        ':queue' => $operation['queue'],
                        ':st'    => Fns::STATE_ERRORSTOPED,
                        ':st2'   => Fns::STATE_SEND_ERROR,
                        ':st3'   => Fns::STATE_RESPONCE_ERROR,
                        ':st4'   => Fns::STATE_RESPONCE_PART,
                        ':pr'    => $product,
                    ]
                )->queryAll();

                if (count($ops)) {
                    $send = false;

                    break;
                }
            }

            if ($send == false) {
                Yii::$app->db->createCommand(
                    'UPDATE operations SET state=:st WHERE id=:id',
                    [
                        ':st' => Fns::STATE_ERRORSTOPED,
                        ':id' => $operation['id'],
                    ]
                )->execute();
            }
        }
    }

    /**
     * Запуск импорта у 601 доков
     */
    static function import601()
    {
        $docs = static::find()->andWhere(
            [
                'operation_uid' => [static::OPERATION_601, static::OPERATION_613, static::OPERATION_609, static::OPERATION_609, static::OPERATION_615],
                'state'         => static::STATE_FNS_WAITING,
            ]
        )->andWhere(['>=', 'created_at', Yii::$app->formatter->asDate(date('Y-m-d', time() - 3600 * 24 * 50))])
            ->all();

        foreach ($docs as $doc) {
            $usos = UsoCache::find()
                ->andWhere(['operation_uid' => $doc->id])
                ->andWhere(['!=', 'state', UsoCache::STATE_RECEIVED])
                ->one();

            if (empty($usos)) {
                $trans = Yii::$app->db->beginTransaction();
                echo 'try import: ' . $doc->id . PHP_EOL;
                try {
                    $doc->import();
                    $trans->commit();
                    echo '\t success' . PHP_EOL;
                } catch (Throwable $ex) {
                    echo '\t error' . $ex->getMessage() . PHP_EOL;
                    $trans->rollBack();
                }
            }
        }
    }

    /**
     * Обработка документов по обратному акцептированию
     */
    static function import416()
    {
        $docs = static::find()->andWhere(['operation_uid' => static::OPERATION_416, 'state' => static::STATE_CREATING])
            ->andWhere(new Expression('created_at> current_date - 14')) //ограничить проверку доков сроком - неделя/две
            ->all();

        foreach ($docs as $doc) {
            $codes = pghelper::pgarr2arr($doc->codes);
            $tree = [];

            foreach ($codes as $code) {
                if (!isset($tree[$code])) {
                    $tree[$code] = [];
                }
                //создаем 3 записи для sgtin sscc_up sscc_down
                $uc = UsoCache::find()->andWhere(
                    [
                        'codetype_uid'  => CodeType::CODE_TYPE_INDIVIDUAL,
                        'operation_uid' => $doc->id,
                        'code'          => $code,
                    ]
                )->one();

                if (empty($uc)) {
                    $uc = new UsoCache();
                    $uc->load(
                        [
                            'code'          => $code,
                            'codetype_uid'  => CodeType::CODE_TYPE_INDIVIDUAL,
                            'operation_uid' => $doc->id,
                            'object_uid'    => $doc->object_uid,
                        ],
                        ''
                    );

                    $uc->save();
                } else {
                    if ($uc->state == UsoCache::STATE_RECEIVED) {
                        //ответ sgtin
                        $tree[$code]['sgtin'] = unserialize($uc->answer);
                    }
                }

                $uc = UsoCache::find()->andWhere(
                    [
                        'codetype_uid'  => CodeType::CODE_TYPE_GROUP,
                        'operation_uid' => $doc->id,
                        'code'          => $code,
                    ]
                )->one();

                if (empty($uc)) {
                    $uc = new UsoCache();
                    $uc->load(
                        [
                            'code'          => $code,
                            'codetype_uid'  => CodeType::CODE_TYPE_GROUP,
                            'operation_uid' => $doc->id,
                            'object_uid'    => $doc->object_uid,
                        ],
                        ''
                    );
                    $uc->save();
                } else {
                    if ($uc->state == UsoCache::STATE_RECEIVED) {
                        //ответ sscc_down
                        $tree[$code]['down'] = unserialize($uc->answer);
                    }
                }

                $uc = UsoCache::find()->andWhere(
                    [
                        'codetype_uid'  => 0,
                        'operation_uid' => $doc->id,
                        'code'          => $code,
                    ]
                )->one();

                if (empty($uc)) {
                    $uc = new UsoCache();
                    $uc->load(
                        [
                            'code'          => $code,
                            'codetype_uid'  => 0,
                            'operation_uid' => $doc->id,
                            'object_uid'    => $doc->object_uid,
                        ],
                        ''
                    );

                    $uc->save();
                } else {
                    if ($uc->state == UsoCache::STATE_RECEIVED) {
                        //ответ sscc_up
                        $tree[$code]['up'] = unserialize($uc->answer);
                    }
                }
            }
            //проверка все ли ответы получены и надо ли добить 416 до отправки
            //не парсить пока нет файла!!!
            $errors = [];

            foreach ($tree as $code => $data) {
                if (isset($data['sgtin'])) {
                    if (isset($data['up']) || isset($data['down'])) {
                        $errors[] = $code . ': Некорректный ответ от Маркировки (Sgtin + SSCC)';
                    }
                    if (isset($data['sgtin']['sscc']) && !empty($data['sgtin']['sscc'])) {
                        $errors[] = $code . ': Не верхнего уровня, родитель ' . $data['sgtin']['sscc'];
                    }
                } else {
                    if (isset($data['up']) && isset($data['down'])) {
                        if (isset($data['up']['sscc']) && !empty($data['up']['sscc'])) {
                            $errors[] = $code . ': Не верхнего уровня, родитель ' . $data['up']['sscc'];
                        }
                    } else {
                        $errors[] = $code . ': Не полностью полученны данные по коду';
                    }
                }
            }

            if (empty($errors)) {
                echo 'Данные получены' . PHP_EOL;
                $doc->fns_state = 'Данные получены';
                $doc->note = 'Данные получены';

                $doc->invoice->updateVendor();

                if (empty($doc->invoice->vatvalue)) {
                    $trans = Yii::$app->db->beginTransaction();

                    try {
                        $params = unserialize($doc->fns_params);
                        $params = [
                            'subject_id'     => $doc->object->fns_subject_id,
                            'shipper_id'     => $doc->invoice->dest_fns,
                            'operation_date' => $doc->cdt,
                            'doc_num'        => $doc->invoice->invoice_number,
                            'doc_date'       => $doc->invoice->invoice_date,
                            'receive_type'   => $doc->invoice->turnover_type ?? 1,
                            'contract_type'  => $doc->invoice->contract_type ?? 1,
                            'source'         => 1,
                        ];

                        $xml = $doc->xml($params);
                        file_put_contents($doc->getFileName(), $xml);

                        $cnt = $doc->import();
                        //перегенерим файл, так как в предыдущем нет кодов в БД
                        $xml = $doc->xml($params);
                        file_put_contents($doc->getFileName(), $xml);
                        //$doc->state = Fns::STATE_READY;
                        $doc->state = Fns::STATE_CREATED;
                    } catch (Exception $ex) {
                        echo 'Ошибка импорта документа: ' . $doc->id . PHP_EOL;
                        echo $ex->getFile() . PHP_EOL;
                        echo $ex->getLine() . PHP_EOL;
                        echo $ex->getMessage() . PHP_EOL;
                        echo $ex->getTraceAsString() . PHP_EOL;

                        $trans->rollBack();

                        $doc->refresh();
                        $doc->fns_state = 'Ошибка формирования документа (не все поля заполнены)';
                        $doc->note = 'Ошибка формирования документа (не все поля заполнены)';
                        $doc->save(false, ['fns_state', 'note', 'indcnt']);
                        continue;
                    }

                    $cnt = intval($cnt);

                    if ($cnt != $doc->invoice->codes_cnt) {
                        echo 'откатываемся - не совпадает количество' . PHP_EOL;
                        $trans->rollBack();
                        $doc->refresh();
                        $doc->fns_state = 'Количество кодов не соответствует накладной (Накладная: $cnt/Axapta: ' . $doc->invoice->codes_cnt . ')';
                        $doc->note = 'Количество кодов не соответствует накладной (Накладная: $cnt/Axapta: ' . $doc->invoice->codes_cnt . ')';
                        $doc->indcnt = $cnt;
                        $doc->save(false, ['fns_state', 'note', 'indcnt']);
                    } else {
                        $trans->commit();
                    }

                    echo 'success' . PHP_EOL;

                    $doc->indcnt = $cnt;
                    $doc->save(false, ['fns_state', 'note', 'indcnt', 'state']);
                } else {
                    echo 'накладная некорректно вернула данные' . PHP_EOL;
                    $doc->fns_state = $doc->invoice->vatvalue;
                    $doc->note = $doc->invoice->vatvalue;
                    $doc->save(false, ['fns_state', 'note', 'indcnt']);
                }
            } else {
                $doc->fns_state = implode("\n", $errors);
                $doc->note = implode("\n", $errors);
                $doc->save(false, ['fns_state', 'note', 'indcnt']);
            }
        }
    }

    static function checkOld601()
    {
        $docs = self::find()
            ->andWhere(['operation_uid' => self::OPERATION_601])
            ->andWhere(
                new Expression(
                    "fns_state!='notified' AND ((created_at+created_time) <= now() - interval '1 hours') AND state IN (10,21)"
                )
            )->all();

        foreach ($docs as $doc) {
            $subject = "Оповещение о 601 документе ($doc->id)";

            //if (ServerHelper::isTestServer()) {
            $subject = (Yii::$app->params['monitoring']['notifyName'] ?? '') . $subject;
            //}

            if (isset(Yii::$app->params['monitoring']['notify'])) {
                $m = Yii::$app->mailer->compose()->setTextBody("Документ $doc->id(601) не обработался за 1 час")
                    ->setTo(Yii::$app->params['monitoring']['notify'])->setFrom(
                        Yii::$app->params['adminEmail'] ?? 'support@i-track.ru'
                    )->setSubject($subject);
                $m->send();
            }

            $doc->fns_state = 'notified';
            $doc->save(false, ['fns_state']);
        }
    }

    /**
     * Создание исходящего запроса к TQS
     *
     * @param type $type
     * @param type $params
     *
     * @return ActiveRecord|Fns|array|\yii\db\ActiveRecord|null
     */
    public static function createTQSoutput($type, $params)
    {
        $xml = TQS::generate($type, $params);

        if ($xml !== false) {
            //создаем operations
            //сохраняем файл
            //сохраняем текущий документ
            $fmodel = new static;
            $fmodel->scenario = 'results';
            $params['operation_uid'] = static::OPERATION_TQS_INP;
            $params['state'] = static::STATE_TQS_RECEIVED;
            //            $params["created_by"] = 0;
            //            if(isset($params["equip_uid"]))$params["created_by"] = $params["equip_uid"];

            $params['data'] = pghelper::arr2pgarr(
                [
                    serialize(
                        [
                            'type'        => $type,
                            'generation'  => isset($params['generation_uid']) ? $params['generation_uid'] : '',
                            'ocs'         => isset($params['ocs_uid']) ? $params['ocs_uid'] : '',
                            'tqs_session' => isset($params['tqs_session']) ? $params['tqs_session'] : '',
                            'equip_uid'   => isset($params['equip_uid']) ? $params['equip_uid'] : '',
                        ]
                    ),
                ]
            );

            if ($fmodel->load($params, '')) {
                $fmodel->save();

                $fmodel = static::find()->andWhere(
                    [
                        'state'         => static::STATE_TQS_RECEIVED,
                        'operation_uid' => static::OPERATION_TQS_INP,
                        'created_by'    => $params['created_by'],
                    ]
                )
                    ->orderBy(['created_at' => SORT_DESC, 'created_time' => SORT_DESC])
                    ->one();
            }

            file_put_contents($fmodel->getFileName(), preg_replace('#^<\?xml[^>]*>\s+#si', '', $xml->saveXML()));

            return $fmodel;
        }

        return null;
    }

    /**
     * Обработка входящих TQS документов
     *
     * при неизвестных ошибках документ скипается...
     */
    static function importTQS()
    {
        echo 'Обработка TQS входящих документов' . PHP_EOL;
        $ops = static::find()->andWhere(
            ['state' => static::STATE_TQS_INPUT, 'operation_uid' => static::OPERATION_TQS_INP]
        )->all();
        /** @var Fns $op */
        foreach ($ops as $op) {
            echo "\t -> $op[id]" . PHP_EOL;
            $body = json_decode(file_get_contents($op->getFileName()));

            $brak = [];
            $otbor = [];
            foreach ($body as $sn) {
                $code = (string)$sn->no;
                $c = Code::findOneByCode($code);
                if (empty($c)) {
                    echo "\tНеизвестный код $code" . PHP_EOL;
                    $op->note = "Неизвестный код $code";
                    $op->save(false);
                    continue 2;
                }
                $flag = (integer)$sn->flags;
                switch ($flag) {
                    case 2:
                        $otbor[$c->object_uid][] = $code;
                        break;
                    case 0:
                        $brak[$c->object_uid][] = $code;
                        break;
                }
            }


            if (!empty($brak)) {
                echo "\t -> brak" . PHP_EOL;
                foreach ($brak as $object => $codes) {
                    $identity = User::findOne([/* 'login' => $login, */ 'object_uid' => $object]);
                    if (empty($identity)) {
                        echo "Пользователь с логином login - находиться на другом объекте ($identity->object_uid / $object)" . PHP_EOL;
                        $op->note = "Пользователь с логином login - находиться на другом объекте ($identity->object_uid / $object)";
                        $op->save(false);
                        continue 2;
                    }
                    Yii::$app->user->setIdentity($identity);
                    Code::brak($codes);
                }
            }
            if (!empty($otbor)) {
                echo "\t -> otbor" . PHP_EOL;
                foreach ($otbor as $object => $codes) {
                    $identity = Equip::findOne(['login' => $login, 'object_uid' => $object]);
                    if (empty($identity)) {
                        echo "Пользователь с логином login - находиться на другом объекте ($identity->object_uid / $object)" . PHP_EOL;
                        $op->note = "Пользователь с логином login - находиться на другом объекте ($identity->object_uid / $object)";
                        $op->save(false);
                        continue 2;
                    }
                    Yii::$app->user->setIdentity($identity);
                    Code::withdrawal($otbor, 'На контроль');
                }
            }

            $op->state = static::STATE_TQS_COMPLETED;
            $op->save(false);
        }
    }

    /**
     * Создание входящего TQS документа
     *
     * @param array $params
     *
     * @return Fns
     */
    public static function createTQSinput($params)
    {
        $fns = new static();
        $fns->operation_uid = static::OPERATION_TQS_INP;
        $fns->state = static::STATE_TQS_INPUT;
        $fns->created_by = $params['created_by'];
        $fns->data = pghelper::arr2pgarr(
            [
                serialize(
                    [
                        'userid' => $params['userid'],
                    ]
                )
            ]
        );
        $fns->save(false);

        $fns = static::find()->andWhere(
            [
                'state'         => static::STATE_TQS_INPUT,
                'operation_uid' => static::OPERATION_TQS_INP,
                'created_by'    => $params['created_by']
            ]
        )
            ->orderBy(['created_at' => SORT_DESC, 'created_time' => SORT_DESC])
            ->one();

        file_put_contents($fns->getFileName(), json_encode($params['body']));

        return $fns;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['operation_uid', 'created_by'], 'required'],
            [
                [
                    'codes',
                    'code',
                    'fns_log',
                    'urlTicket',
                    'fns_state',
                    'fns_params',
                    'created_at',
                    'created_time',
                    'data',
                    'note',
                    'invoice_uid',
                    'codes_data',
                    'fnsid',
                    'full_codes'
                ],
                'string'
            ],
            [
                [
                    'operation_uid',
                    'state',
                    'product_uid',
                    'object_uid',
                    'newobject_uid',
                    'queue',
                    'prev_uid',
                    'indcnt',
                    'grpcnt',
                    'code_flag'
                ],
                'integer'
            ],
            [['internal'], 'boolean'],
            [['state', 'fns_log', 'urlTicket', 'fns_start_send'], 'safe'],
        ];
    }

    public function scenarios()
    {
        $sc = parent::scenarios();

        return array_merge(
            $sc,
            [
                'update601' => ['state', 'invoice_id', 'product_uid', 'products', 'fnsid', 'object_uid'],
                'update'    => ['state', 'fns_log'],
                'results'   => ['operation_uid', 'data', 'created_by', 'state'],
            ]
        );
    }

    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            [/*'params',*/ 'canParams', 'cdt', 'paleta', 'grp_cnt', 'cdata', 'operation', 'l3']
        );
    }

    /**
     * Таблица партицированная , не работает RETURNING
     * берем id из сиквенса
     *
     * @param type $runValidation
     * @param type $attributeNames
     *
     * @return type
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $ret = parent::save($runValidation, $attributeNames);
        if ($this->isNewRecord) {
            $this->id = Yii::$app->db->createCommand("SELECT currval('operations_id_seq')")->queryScalar();
        }

        return $ret;
    }

    public function getCanDelete()
    {
        if (Yii::$app->user->can('report-fns-delete') && !in_array(
                $this->state,
                [
                    static::STATE_RESPONCE_SUCCESS_DECLINE,
                    static::STATE_RESPONCE_SUCCESS,
                    static::STATE_RESPONCE_PART,
                    static::STATE_SENDING,
                    static::STATE_SENDED
                ]
            )) {
            return true;
        } else {
            return false;
        }
    }

    public function delete()
    {
        if ($this->canDelete) {
            Yii::$app->db->createCommand(
                "UPDATE operations set prev_uid=null WHERE prev_uid=:p and created_at>=:ca",
                [":p" => $this->id, ":ca" => $this->created_at]
            )->execute();
            $this->dbschema = 'deleted';
            if ($this->operation_uid == Fns::OPERATION_416) {
                //если удаляем 416 документ - то необходимо удалить накладную привязанную к этому документу
                $this->invoice->delete();
                //также удаляем все 210 в usoCache (FK Пока нет -чтобы автоматом удалять)
                UsoCache::deleteAll(['operation_uid' => $this->id]);
            }

            return $this->update(false, ['dbschema']);
        } else {
            throw new BadRequestHttpException('Нельзя удалить данный документ');
        }
    }

    public function init()
    {
        parent::init();
        $this->on(
            self::EVENT_BEFORE_INSERT,
            function ($event) {
                $event->sender->dirname = date('Y-m-d');
            }
        );
    }

    public function getFileName($path = true)
    {
        $f = static::$file_prefix . '-' . $this->id . '-' . ($this->fnsid ?? '') . '.xml';
        if ($this->fnsid == 411) {
            $f = static::$file_prefix . '-' . $this->id . '-415.xml';
        }
        if ($this->fnsid == 911) {
            $f = static::$file_prefix . '-' . $this->id . '-915.xml';
        }
        if ($path) {
            if (!empty($this->dirname)) {
                @mkdir(Yii::getAlias('@reportPath') . '/' . $this->dirname, 0777);
                @chmod(Yii::getAlias('@reportPath') . '/' . $this->dirname, 0777);

                return Yii::getAlias('@reportPath') . '/' . $this->dirname . '/' . $f;
            } else {
                return Yii::getAlias('@reportPath') . '/' . $f;
            }
        } else {
            return $f;
        }
    }

    public function fields()
    {
        return array_merge(
            parent::fields(),
            [
                'uid'        => 'id',
                'operation'  => function () {
                    switch ($this->operation_uid) {
                        case static::OPERATION_GROUP_ID:
                            $v = static::OPERATION_GROUP;
                            break;
                        case static::OPERATION_GROUPSUB_ID:
                            $v = static::OPERATION_GROUPSUB;
                            break;
                        case static::OPERATION_UNGROUP_ID:
                            $v = static::OPERATION_UNGROUP;
                            break;
                        case static::OPERATION_GROUPADD_ID:
                            $v = static::OPERATION_GROUPADD;
                            break;
                        case static::OPERATION_PACK_ID:
                            $v = static::OPERATION_PACK;
                            break;
                        case static::OPERATION_EMISSION_ID:
                            $v = static::OPERATION_EMISSION;
                            break;
                        case static::OPERATION_INCOME_ID:
                            $v = static::OPERATION_INCOME;
                            break;
                        case static::OPERATION_OUTCOME_ID:
                            $v = static::OPERATION_OUTCOME;
                            break;
                        case static::OPERATION_OUTCOMERETAIL_ID:
                            $v = static::OPERATION_OUTCOMERETAIL;
                            break;
                        case static::OPERATION_OUTCOMESELF_ID:
                            $v = static::OPERATION_OUTCOMESELF;
                            break;
                        case static::OPERATION_WDEXT_ID:
                            $v = static::OPERATION_WDEXT;
                            break;
                        case static::OPERATION_IMPORT_ID:
                            $v = static::OPERATION_IMPORT;
                            break;
                        case static::OPERATION_DESTRUCTION_ID:
                            $v = static::OPERATION_DESTRUCTION;
                            break;
                        case static::OPERATION_DESTRUCTIONACT_ID:
                            $v = static::OPERATION_DESTRUCTIONACT;
                            break;
                        case static::OPERATION_OUTCOMERETAILUNREG_ID:
                            $v = static::OPERATION_OUTCOMERETAILUNREG;
                            break;
                        case static::OPERATION_BACK_ID:
                            $v = static::OPERATION_BACK;
                            break;
                        case static::OPERATION_RELABEL_ID:
                            $v = static::OPERATION_RELABEL;
                            break;
                        case static::OPERATION_UPLOADED:
                            $v = static::OPERATION_UPLOADED_NAME;
                            break;
                        case static::OPERATION_TQS_INP:
                            $ret = [];
                            $a = pghelper::pgarr2arr($this->data);
                            try {
                                if (is_array($a)) {
                                    $ret = unserialize($a[0]);
                                }
                            } catch (Exception $ex) {
                            }
                            $v = $ret['type'] ?? 'Unknown';
                            break;
                        case static::OPERATION_CONTROL_ID:
                            $a = pghelper::pgarr2arr($this->data);
                            if ($a[0] == 1) {
                                $v = static::OPERATION_CONTROL;
                            } else {
                                $v = static::OPERATION_CONTROL_2;
                            }
                            break;
                        default:
                            $v = 'Операция №' . $this->operation_uid;
                    }

                    return $v;
                },
                'canParams'  => function () {
                    return $this->getCanParams();
                },
                //            'params' => function() {
                //                return $this->getParams();
                //            },
                'fdata'      => function () {
                    return pghelper::pgarr2arr($this->data);
                },
                'note'       => function () {
                    return nl2br(preg_replace('#"$#', '', preg_replace('#^"#', '', $this->note)));
                },
                'cdata'      => function () {
                    $ret = [];
                    $a = pghelper::pgarr2arr($this->data);
                    try {
                        if (is_array($a)) {
                            $ret = unserialize($a[0]);
                        }
                    } catch (Exception $ex) {
                    }

                    return $ret;
                },
                'cdt',
                'paleta'     => function () {
                    //$ret = 'Упаковка';
                    if (Constant::get('hasL3') == 'true') {
                        //                    if($this->code_flag)
                        $ret = 'Короб';
                        if ($this->paleta) {
                            $ret = 'Паллета';
                        }
                        if ($this->l3) {
                            $ret = 'Бандероль';
                        }
                    } else {
                        //                    if ($this->code_flag)
                        $ret = 'Короб';
                        if ($this->paleta) {
                            $ret = 'Паллета';
                        }
                    }

                    return $ret;
                },
                'l3',
                'product',
                'productAll' => function () {
                    $arr = pghelper::pgarr2arr($this->products);
                    $arr[] = $this->product_uid;

                    return Product::find()->andWhere(['in', 'id', $arr])->all();
                },
                'invoice',
                'canDelete',
                'object',
                'stateinfo'  => function () {
                    return static::getStateInfo($this->state);
                },
                'code'       => function () {
                    $ar = [];
                    if (preg_match('#^{.*}$#si', $this->codes_data)) {
                        $ar = pghelper::pgarr2arr($this->codes_data);
                    }
                    if (count($ar) > 1) {
                        return '*';
                    } elseif (count($ar) == 1) {
                        $j = json_decode($ar[0]);
                        if ($j && isset($j->grp)) {
                            return $j->grp;
                        } else {
                            return '*';
                        }
                    }

                    return '*';
                },
                'grp_cnt'    => function () {
                    if (preg_match('#^{.*}$#si', $this->codes_data)) {
                        return count(pghelper::pgarr2arr($this->codes_data));
                    } else {
                        return $this->grpcnt;
                    }
                },
                'codes_cnt'  => function () {
                    if (preg_match('#^{.*}$#si', $this->codes)) {
                        return count(pghelper::pgarr2arr($this->codes));
                    } else {
                        return $this->indcnt;
                    }
                },
                'codes'      => function () {
                    if (preg_match('#^{.*}$#si', $this->codes)) {
                        return pghelper::pgarr2arr($this->codes);
                    } else {
                        return [];
                    }
                },
                'url'        => function () {
                    if ($this->state == static::STATE_CREATING) {
                        return '';
                    }

                    return Yii::$app->urlManager->createAbsoluteUrl(
                        [
                            'itrack/fns/download',
                            'id'  => $this->id,
                            'tok' => md5($this->created_at . $this->created_time . $this->id)
                        ]
                    );
                },
                'urlTicket'  => function () {
                    if ($this->fns_log) {
                        return \Yii::$app->urlManager->createAbsoluteUrl(
                            ['api/v1/fns/download-ticket', 'operationId' => $this->operation_uid]
                        );
                    }
                }
            ]
        );
    }

    public function extraFields()
    {
        return [
            'newobject',
            'fullProduct',
            'gtin'               => function () {
                if (!empty($this->product_uid)) {
                    return isset($this->product->nomenclature->gtin) ? $this->product->nomenclature->gtin : '';
                }

                $gtin = [];
                $fp = $this->fullProduct;

                if (is_array($fp)) {
                    foreach ($fp as $p) {
                        $gtin[$p->nomenclature->gtin] = 1;
                    }
                } else {
                    return '';
                }

                if (count($gtin) > 1) {
                    return '*';
                }

                return array_keys($gtin)[0] ?? null;
            },
            'prev_operation_uid' => function () {
                $op = null;

                if (!empty($this->prev_uid)) {
                    $f = self::findOne(['id' => $this->prev_uid]);
                    $op = $f->operation_uid ?? null;
                }

                return $op;
            },
        ];
    }

    public function getCanParams()
    {
        if (isset(Yii::$app->params['FnsParams']) && Yii::$app->params['FnsParams'] === false) {
            return false;
        }

        if (in_array(
            $this->operation_uid,
            [
                static::OPERATION_211,
                static::OPERATION_601,
            ]
        )) {
            return false;
        }

        if ((
                /* ($this->operation_uid == Fns::OPERATION_EMISSION_ID && in_array($this->state, [Fns::STATE_CREATING, Fns::STATE_CREATED])) || */
            in_array(
                $this->state,
                [
                    Fns::STATE_RESPONCE_PART,
                    Fns::STATE_CHECKING,
                    /* Fns::STATE_SENDING, */
                    Fns::STATE_STOPED,
                    Fns::STATE_SEND_ERROR,
                    Fns::STATE_READY,
                    Fns::STATE_RESPONCE_ERROR,
                    Fns::STATE_ERRORSTOPED,
                ]
            )
            ) && $this->internal) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Обработка ответа(статуса) Маркировки
     *
     * @param $params
     *
     * @return Fns
     * @throws BadRequestHttpException
     */
    public function answer($params)
    {
        $tok = $params['tok'];

        if (md5($this->created_at . $this->created_time . $this->id) != $tok) {
            throw new BadRequestHttpException('Ошибка, доступа к файлу');
        }

        if (in_array(
            $this->state,
            [
                static::STATE_SENDING,
                static::STATE_SENDED,
                static::STATE_SEND_ERROR,
                static::STATE_1CCOMPLETED,
                static::STATE_TQS_COMPLETED,
            ]
        )) {
            $this->scenario = 'update';

            if (!in_array($this->state, [static::STATE_1CCOMPLETED, static::STATE_TQS_COMPLETED])) {
                switch ($params['state']) {
                    case 'export_success':
                        $params['state'] = Fns::STATE_SENDED;
                        break;
                    case 'export_reject':
                        $params['state'] = Fns::STATE_SEND_ERROR;
                        break;
                    case 'response_success':
                        $params['state'] = Fns::STATE_RESPONCE_SUCCESS;
                        break;
                    case 'response_reject':
                        $params['state'] = Fns::STATE_RESPONCE_ERROR;
                        break;
                    case 'response_partial':
                        $params['state'] = Fns::STATE_RESPONCE_PART;
                        break;
                }
            }

            // фича по 210 доку, коннектор получает ответ 21 и считает что это ответ на 211 и присылает 'response_reject'
            if ($this->operation_uid == static::OPERATION_210
                /* && $params["state"] == static::STATE_RESPONCE_ERROR */
                && preg_match('#kiz_info action_id#si', $params['fns_log'])
            ) {
                $params['state'] = static::STATE_RESPONCE_SUCCESS;
                try {
                    static::createImport($params['fns_log']);
                } catch (Exception $ex) {
                }
            }

            // фича по 1с
            if ($this->state == static::STATE_1CCOMPLETED) {
                //отправлено в 1с
                switch ($params['state']) {
                    case 'response_success':
                        $params['state'] = static::STATE_1CCONFIRMED;
                        break;
                    case 'response_reject':
                        $params['state'] = static::STATE_1CDECLAINED;
                        break;
                }
            }

            // фича по TQS
            if ($this->state == static::STATE_TQS_COMPLETED) {
                //отправлено в TQS
                switch ($params['state']) {
                    case 'response_success':
                        $params['state'] = static::STATE_TQS_CONFIRMED;
                        break;
                    case 'response_reject':
                        $params['state'] = static::STATE_TQS_DECLAINED;
                        break;
                }
            }

            if (!in_array(
                $params['state'],
                [
                    Fns::STATE_SENDED,
                    Fns::STATE_SEND_ERROR,
                    Fns::STATE_RESPONCE_PART,
                    Fns::STATE_RESPONCE_SUCCESS,
                    Fns::STATE_RESPONCE_ERROR,
                    Fns::STATE_TQS_CONFIRMED,
                    Fns::STATE_TQS_DECLAINED,
                    Fns::STATE_1CCONFIRMED,
                    Fns::STATE_1CDECLAINED,
                ]
            )) {
                throw new BadRequestHttpException('Неизвестный статус операции');
            }

            $this->state = $params['state'];
            $this->fns_log = $params['fns_log'];
            $this->fns_state = $params['fns_state'] ?? '';
            $this->save(false);
            $this->refresh();


            if (in_array($this->state, [Fns::STATE_TQS_CONFIRMED, Fns::STATE_TQS_DECLAINED])) {
                //парсинг TQS ответа
                $this->parseTQSanswer($params);
                $this->fnsid = 'TQS';
                $this->makeNotify();
            }

            if (in_array($this->state, [Fns::STATE_RESPONCE_SUCCESS, Fns::STATE_RESPONCE_PART])) {
                $this->startSeries(
                ); //решили что если приходит саксесс , то надо остановленные доки этой серии запустить в отправку
                $this->makeNotify();
            }

            if (in_array($this->state, [Fns::STATE_SEND_ERROR, Fns::STATE_RESPONCE_ERROR])) {
                //$model->stopSeries();
                $this->makeNotify();
            }

            return $this;
        } else {
            throw new BadRequestHttpException('Ошибка, нельзя изменять данную операцию');
        }
    }

    public function getParams()
    {
        if (empty($this->fns_params)) {
            return $this->createParams();
        } else {
            return unserialize($this->fns_params);
        }
    }

    /**
     * ЗАполнение документов дефолтными занчениями
     *
     * @return array массив со значениями
     * @throws DbException
     */
    public function createParams()
    {
        $params = [];
        switch ($this->operation_uid) {
            case static::OPERATION_250:
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $prev = Fns::findOne($this->prev_uid);
                $params['operation_id'] = $prev->fns_state;
                $params['recall_action_id'] = $prev->fnsid;
                $params['reason'] = '';
                break;
            case static::OPERATION_251:
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $params['receiver_id'] = $this->invoice->dest_fns;
                $params['reason'] = $this->note;
                break;
            case static::OPERATION_252:
                $params['subject_id'] = $this->object->fns_subject_id ?? null;
                $params['operation_date'] = $this->cdt;
                //$params['newobject_uid'] = $this->newobject_uid;
                $params['shipper_id'] = $this->newobject->fns_subject_id ?? null;
                $params['reason'] = $this->note;
                break;
            case static::OPERATION_RELABEL_ID:
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                break;
            case static::OPERATION_WDEXT_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $d = pghelper::pgarr2arr($this->data);
                if ($d[0] == 'ext1') {
                    $params['withdrawal_type'] = '6';
                }
                if ($d[0] == 'ext2') {
                    $params['withdrawal_type'] = '7';
                }
                if ($d[0] == 'ext3') {
                    $params['withdrawal_type'] = '8';
                }
                if ($d[0] == 'ext4') {
                    $params['withdrawal_type'] = '9';
                }
                if ($d[0] == 'ext5') {
                    $params['withdrawal_type'] = '10';
                }
                if ($d[0] == 'ext6') {
                    $params['withdrawal_type'] = '11';
                }
                if ($d[0] == 'ext7') {
                    $params['withdrawal_type'] = '12';
                }
                if ($d[0] == 'ext8') {
                    $params['withdrawal_type'] = '13';
                }
                if ($d[0] == 'ext9') {
                    $params['withdrawal_type'] = '14';
                }
                if ($d[0] == 'ext15') {
                    $params['withdrawal_type'] = '15';
                }
                if ($d[0] == 'ext16') {
                    $params['withdrawal_type'] = '16';
                }
                if ($d[0] == 'ext17') {
                    $params['withdrawal_type'] = '17';
                }
                if ($d[0] == 'ext18') {
                    $params['withdrawal_type'] = '18';
                }
                $params['doc_num'] = $d[1] ?? '';
                $params['doc_date'] = $d[2] ?? '';
                break;
            case static::OPERATION_BACK_ID:
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $d = pghelper::pgarr2arr($this->data);
                $params['withdrawal_reason'] = $d[0];
                //                if ($d[0] == 'spis')
                //                    $params['withdrawal_reason'] = 1;
                //                if ($d[0] == 'reexp')
                //                    $params['withdrawal_reason'] = 2;
                //                if ($d[0] == 'otbor')
                //                    $params['withdrawal_reason'] = 3;
                break;
            case static::OPERATION_OUTCOMESELF_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;
                $params['operation_date'] = $this->cdt;
                //                if (!empty($this->invoice) && !empty($this->invoice->dest_fns))
                //                    $params['receiver_id'] = $this->invoice->dest_fns;
                //                else
                //                    $params['receiver_id'] = '';
                $params['newobject_uid'] = $this->newobject_uid;
                $params['receiver_id'] = $this->newobject->fns_subject_id;
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;
                break;
            case static::OPERATION_OUTCOMERETAILUNREG_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                if (!empty($this->invoice)) {
                    try {
                        if ($this->invoice->updated == false) {
                            $this->invoice->updateExternal(false);
                        }
                    } catch (Exception $ex) {
                    }

                    $params['receiver_address_aoguid'] = $this->invoice->dest_address;
                    $params['receiver_address_houseguid'] = $this->invoice->dest_settlement;
                    $params['receiver_address_flat'] = $this->invoice->dest_consignee;
                    $params['inn'] = $this->invoice->dest_inn;
                    $params['kpp'] = $this->invoice->dest_kpp;

                    if (!isset(Yii::$app->params['invoice']) || !isset(Yii::$app->params['invoice']['check']) || Yii::$app->params['invoice']['check'] != true) {
                        $params['gtins'] = [];
                        $invoice_gtins = Yii::$app->db->createCommand(
                            'SELECT serie as gtin FROM get_gtins_by_invoice(:id) as a',
                            [':id' => $this->invoice_uid]
                        )->queryAll();
                        foreach ($invoice_gtins as $gtin) {
                            $params['gtins'][$gtin['gtin']] = [0, 0];
                        }
                    }
                } else {
                    $params['receiver_address_aoguid'] = '';
                    $params['receiver_address_houseguid'] = '';
                    $params['receiver_address_flat'] = '';
                    $params['inn'] = '';
                    $params['kpp'] = '';
                }
                if (!empty($this->invoice) && !empty($this->invoice->contract_type)) {
                    $params['contract_type'] = $this->invoice->contract_type;
                } else {
                    $params['contract_type'] = 1;
                }
                $params['source'] = $this->invoice->source ?? '';
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;
                break;
            case static::OPERATION_461:
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $params['doc_num'] = $this->invoice->invoice_number ?? '';
                $params['doc_date'] = $this->invoice->invoice_date ?? '';
                $params['contract_type'] = $this->invoice->contract_type ?? 1;
                $params['info_org_eeu'] = $this->invoice->dest_fns ?? '';
                break;
            case static::OPERATION_416:
                $params['subject_id'] = $this->object->fns_subject_id;
                if (!empty($this->invoice) && !empty($this->invoice->dest_fns)) {
                    $params['shipper_id'] = $this->invoice->dest_fns;
                } else {
                    $params['shipper_id'] = '';
                }
                $params['operation_date'] = $this->cdt;
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;

                if (!empty($this->invoice) && !empty($this->invoice->turnover_type)) {
                    $params['receive_type'] = $this->invoice->turnover_type;
                } else {
                    $params['receive_type'] = 1;
                }

                if (!empty($this->invoice) && !empty($this->invoice->contract_type)) {
                    $params['contract_type'] = $this->invoice->contract_type;
                } else {
                    $params['contract_type'] = 1;
                }
                $params['source'] = $this->invoice->source ?? '1';
                break;
            case static::OPERATION_OUTCOMERETAIL_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;

                try {
                    if (!empty($this->invoice) && $this->invoice->updated == false) {
                        $this->invoice->updateExternal(false);
                    }
                } catch (Exception $ex) {
                }

                if (!empty($this->invoice) && !empty($this->invoice->dest_fns)) {
                    $params['receiver_id'] = $this->invoice->dest_fns;
                } else {
                    $params['receiver_id'] = '';
                }

                if (!empty($this->invoice)) {
                    if (!isset(Yii::$app->params['invoice']) || !isset(Yii::$app->params['invoice']['check']) || Yii::$app->params['invoice']['check'] != true) {
                        $params['gtins'] = [];
                        $invoice_gtins = Yii::$app->db->createCommand(
                            'SELECT serie as gtin FROM get_gtins_by_invoice(:id) as a',
                            [':id' => $this->invoice_uid]
                        )->queryAll();
                        foreach ($invoice_gtins as $gtin) {
                            $params['gtins'][$gtin['gtin']] = [0, 0];
                        }
                    }
                }
                $params['operation_date'] = $this->cdt;
                $params['accept_type'] = 1;
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;
                $params['turnover_type'] = $this->invoice->turnover_type ?? 1;
                $params['contract_type'] = $this->invoice->contract_type ?? 1;
                $params['source'] = $this->invoice->source ?? '';
                $params['contract_num'] = $this->invoice->contract_num ?? '';
                break;
            case static::OPERATION_OUTCOME_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                //                if (!empty($this->newobject))
                //                    $params['owner_id'] = $this->newobject->fns_subject_id;
                //                else {
                //                    if (!empty($this->invoice) && !empty($this->invoice->dest_fns))
                //                        $params['owner_id'] = $this->invoice->dest_fns;
                //                    else
                //                    {
                //                        if(!empty($this->product) && !empty($this->product->nomenclature))
                //                            $params['owner_id'] = $this->product->nomenclature->manufacturer->fnsid;
                //                        else
                //                            $params['owner_id'] = '';
                //                    }
                //                }
                $ow = $this->invoice->dest_fns ?? '';
                if (!empty($ow)) {
                    $params['owner_id'] = $ow;
                } else {
                    $params['owner_id'] = $this->product->nomenclature->owner->fnsid ?? $this->newobject->fns_subject_id ?? $this->invoice->dest_fns ?? '';
                }
                $params['operation_date'] = $this->cdt;
                $params['doc_num'] = $this->invoice->invoice_number;
                $params['doc_date'] = $this->invoice->invoice_date;
                break;
            case static::OPERATION_INCOME_ID:
                $params['newobject_uid'] = $this->newobject_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['object_uid'] = $this->object_uid;
                $o = $this->getNewobject()->where([])->one();
                $params['counterparty_id'] = isset($o->fns_subject_id) ? $o->fns_subject_id : '';
                $params['operation_date'] = $this->cdt;
                break;
            case static::OPERATION_EMISSION_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $params['confirm_doc'] = null;
                $params['doc_num'] = null;
                $params['doc_date'] = null;
                break;
            case static::OPERATION_PACK_ID:
                $params['order_type'] = $this->product->nomenclature->fns_order_type;
                if ($params['order_type'] == 2) {
                    $params['owner_id'] = $this->product->nomenclature->fns_owner_id;
                }
                $params['series_number'] = $this->product->series;
                $params['expiration_date'] = $this->product->expdate_full;
                $params['gtin'] = $this->product->nomenclature->gtin;
                $params['tnved'] = $this->product->nomenclature->tnved;
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                break;

            case static::OPERATION_DESTRUCTION_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $params['destruction'] = '';
                $params['doc_num'] = '';
                $params['doc_date'] = '';
                $params['act_number'] = '';
                $params['act_date'] = '';
                $params['type'] = '1';
                break;
            case static::OPERATION_DESTRUCTIONACT_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $params['destruction'] = '';
                $params['destruction_method'] = '';
                $params['destruction_org'] = '';
                $params['doc_num'] = '';
                $params['doc_date'] = '';
                $params['type'] = '1';
                break;
            case static::OPERATION_UNGROUP_ID:
            case static::OPERATION_GROUPSUB_ID:
            case static::OPERATION_GROUPADD_ID:
            case static::OPERATION_GROUP_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                break;

            case static::OPERATION_CONTROL_ID:
                $params['object_uid'] = $this->object_uid;
                $params['subject_id'] = $this->object->fns_subject_id;
                $params['operation_date'] = $this->cdt;
                $fdata = pghelper::pgarr2arr($this->data);
                $params['control_samples_type'] = $fdata[0];
                break;
        }

        return $params;
    }

    /**
     * Получение кодов в операции и запоминание их в operations.codes_data
     */
    public function saveCodes313()
    {
        $codes = pghelper::pgarr2arr($this->codes);
        $cc = [];
        foreach ($codes as $code) {
            $c = Code::findOneByCode($code);
            if (!$c->defected && !$c->removed) {
                if (!empty($c->parent_code)) {
                    $cp = Code::findOneByCode($c->parent_code);
                    if (!empty($cp->parent_code)) {
                        $cc[$cp->parent_code] = 1;
                    } else {
                        $cc[$c->parent_code] = 1;
                    }
                } else {
                    $cc[$code] = 1;
                }
            }
        }
        $this->codes_data = pghelper::arr2pgarr([json_encode($cc)]);

        $cur = $this->fns_start_send;
        if (empty($cur)) {
            $cur = $this->updated_at;
        }
        $max_date = Yii::$app->db->createCommand(
            'SELECT max(fns_start_send + interval \'0.1 sec\')
                                                            FROM operations
                                                            WHERE created_at>=:created and
                                                            product_uid=:product and
                                                            fnsid not in (\'701\', \'415\', \'441\')',
            //\'431\',  '381',
            [
                ':created' => Yii::$app->formatter->asDate($cur, 'php:Y-m-d'),
                ':product' => $this->product_uid,
            ]
        )->queryScalar();
        if (!empty($max_date)) {
            $this->fns_start_send = $max_date;
        }

        $this->save(false, ['codes_data', 'fns_start_send']);
    }

    /**
     * Генерация XML документа для ФНС
     *
     * @param array $params - массив с парамтерами документа
     *
     * @return string строка с xml документом
     * @throws BadRequestHttpException
     */
    public function xml($params = [])
    {
        try {
            if (!$this->internal) {
                return;
            }
            if (empty($params)) {
                $params = $this->getParams();
            }
            $xml = new SimpleXMLElement(
                '<documents xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="' . static::FNS_VERSION . '"></documents>',
                null,
                false
            );

            switch ($this->operation_uid) {
                case static::OPERATION_250:
                    $child = $xml->addChild('recall');
                    $child->addAttribute('action_id', 250);
                    $child->addChild('subject_id', $params['subject_id'] ?? '');
                    $child->addChild('operation_date', $this->dt($params['operation_date'] ?? $this->cdt));
                    $child->addChild('operation_id', $params['operation_id'] ?? '');
                    $child->addChild('recall_action_id', $params['recall_action_id'] ?? '');
                    if (!empty($params['reason'])) {
                        $child->addChild('reason', $params['reason']);
                    }
                    break;
                case static::OPERATION_252:
                    $child = $xml->addChild('refusal_receiver');
                    $child->addAttribute('action_id', 252);
                    $child->addChild('subject_id', $params['subject_id'] ?? '');
                    $child->addChild('operation_date', $this->dt($params['operation_date'] ?? $this->cdt));
                    $child->addChild('shipper_id', $params['shipper_id'] ?? '');
                    $child->addChild('reason', $params['reason'] ?? 1);
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_RELABEL_ID:
                    $child = $xml->addChild('relabeling');
                    $child->addAttribute('action_id', 811);
                    $child->addChild('subject_id', $params['subject_id'] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params['operation_date'] ?? $this->cdt));

                    $details = $child->addChild('relabeling_detail');
                    $codes = pghelper::pgarr2arr($this->codes_data);
                    foreach ($codes as $code) {
                        $detail = $details->addChild('detail');
                        $cc = json_decode($code);

                        $c1 = Code::findOneByCode($cc->f2);
                        if ($c1->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $detail->addChild(
                                'new_sgtin',
                                ((preg_match(
                                    '#^' . $c1->product->nomenclature->gtin . '#',
                                    $cc->f2
                                )) ? $cc->f2 : $c1->product->nomenclature->gtin . $cc->f2)
                            );
                        }
                        $c2 = Code::findOneByCode($cc->f1);
                        if ($c2->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $detail->addChild(
                                'old_sgtin',
                                ((preg_match(
                                    '#^' . $c2->product->nomenclature->gtin . '#',
                                    $cc->f1
                                )) ? $cc->f1 : $c2->product->nomenclature->gtin . $cc->f1)
                            );
                        }
                    }
                    break;
                case static::OPERATION_416:
                    $child = $xml->addChild('receive_order');
                    $child->addAttribute('action_id', 416);
                    $child->addChild('subject_id', $params['subject_id'] ?? $this->object->fns_subject_id);
                    if (empty($params['shipper_id']) && Yii::$app->request->getQueryParam('save') !== 'only') {
                        throw new BadRequestHttpException('Не все обязательные поля заполнены');
                    }
                    $child->addChild('shipper_id', $params['shipper_id'] ?? '');
                    $child->addChild('operation_date', $this->dt($params['operation_date'] ?? $this->cdt));
                    $child->addChild('doc_num', $params['doc_num']);
                    $child->addChild('doc_date', Yii::$app->formatter->asDate($params['doc_date'], 'php:d.m.Y'));
                    $child->addChild('receive_type', $params['receive_type']);
                    if (empty($params['source'])) {
                        $params['source'] = 1;
                    }
                    $child->addChild('source', $params['source']);
                    $child->addChild('contract_type', $params['contract_type']);

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    if (isset($this->invoice->cost)) {
                        $invoice_data = unserialize($this->invoice->cost);
                    } else {
                        $invoice_data = [];
                    }
                    foreach ($codes as $code) {
                        $union = $details->addChild('union');
                        $c = Code::findOneByCode($code);
                        if (!empty($c)) {
                            if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                                $union->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $c->product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $c->product->nomenclature->gtin . $code)
                                );
                                if (isset($params['gtins']) && isset($params['gtins'][$c->product->nomenclature->gtin]) && $params['gtins'][$c->product->nomenclature->gtin][0] > 0) {
                                    $cost = $params['gtins'][$c->product->nomenclature->gtin][0];
                                    $cost = round($cost, 2);
                                    if ($params['gtins'][$c->product->nomenclature->gtin][1] > 0) {
                                        $vat = round(
                                            $cost * $params['gtins'][$c->product->nomenclature->gtin][1] / (100 + $params['gtins'][$c->product->nomenclature->gtin][1]),
                                            2
                                        );
                                    } else {
                                        $vat = 0;
                                    }
                                } else {
                                    if (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]['Price'];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]['VatValue'] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]['VatValue'] / (100 + $invoice_data[$c->product->series]['VatValue']),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]['Price'];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]['VatValue'] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]['VatValue'] / (100 + $invoice_data[$c->product->nomenclature->gtin]['VatValue']),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        //                                throw new \BadMethodCallException('no data for fns 415 (cost/vat)');
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                }
                                if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            } else {
                                $detail = $union->addChild('sscc_detail');
                                $detail->addChild('sscc', (string)$code);
                                $res = Yii::$app->db->createCommand(
                                    "select distinct nomenclature.gtin as realgtin, product.series as gtin from _get_codes_array(:codes) as codes
                                                        left join generations on codes.generation_uid=generations.id
                                                        left join product on codes.product_uid = product.id
                                                        left join nomenclature on product.nomenclature_uid = nomenclature.id
                                                        WHERE codetype_uid = :codetype",
                                    [
                                        ":codes"    => pghelper::arr2pgarr(pghelper::pgarr2arr($c->childrens)),
                                        ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                                    ]
                                )->queryAll();
                                $cost = 0;
                                $vat = 0;
                                foreach ($res as $cd) {
                                    $d = $detail->addChild('detail');
                                    if (isset($params["gtins"]) && isset($params["gtins"][$cd["gtin"]]) && $params["gtins"][$cd["gtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["gtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["gtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["gtin"]][1] / (100 + $params["gtins"][$cd["gtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($params["gtins"]) && isset($params["gtins"][$cd["realgtin"]]) && $params["gtins"][$cd["realgtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["realgtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["realgtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["realgtin"]][1] / (100 + $params["gtins"][$cd["realgtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]["VatValue"] / (100 + $invoice_data[$c->product->nomenclature->gtin]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                    if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                        throw new BadRequestHttpException('Нет данных по стоимости');
                                    }
                                    $vat = round($vat ?? 0, 2);
                                    $d->addChild('gtin', $cd["realgtin"]);
                                    $d->addChild('series_number', $cd["gtin"]);
                                    $d->addChild('cost', $cost ?? 0);
                                    $d->addChild('vat_value', $vat);
                                }

                                if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            }
                        }
                    }
                    break;
                case static::OPERATION_BACK_ID:
                    $child = $xml->addChild('return_to_circulation');
                    $child->addAttribute('action_id', 391);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $child->addChild('withdrawal_reason', $params["withdrawal_reason"] ?? $this->note);
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_INCOME_ID:
                    $child = $xml->addChild('accept');
                    $child->addAttribute('action_id', 701);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('counterparty_id', $params["counterparty_id"] ?? $this->newobject->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_210:
                    $child = $xml->addChild('query_kiz_info');
                    $child->addAttribute('action_id', 210);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    if ($params["codetype_uid"] == CodeType::CODE_TYPE_INDIVIDUAL) {
                        $child->addChild('sgtin', $this->code);
                    } elseif ($params["codetype_uid"] == CodeType::CODE_TYPE_GROUP) {
                        $child->addChild('sscc_down', $this->code);
                    } else {
                        $child->addChild('sscc_up', $this->code);
                    }
                    break;

                case static::OPERATION_UNGROUP_ID:
                    $xml->addChild('unit_unpack');
                    $xml->unit_unpack->addAttribute('action_id', 912);
                    $xml->unit_unpack->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $xml->unit_unpack->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $xml->unit_unpack->addChild('sscc', $this->code);
                    $d = pghelper::pgarr2arr($this->data);
                    if (isset($d[0]) && $d[0] == "recursive") {
                        $xml->unit_unpack->addChild('is_recursive', 'true');
                    }
                    break;
                case static::OPERATION_GROUPSUB_ID:
                    $xml->addChild('unit_extract');
                    $xml->unit_extract->addAttribute('action_id', 913);
                    $xml->unit_extract->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $dt = new DateTime($params["operation_date"] ?? $this->cdt);
                    //$dt->modify('+1 day');
                    $xml->unit_extract->addChild('operation_date', $this->dt($dt->format("c")));
                    $xml->unit_extract->addChild('content');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $xml->unit_extract->content->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $xml->unit_extract->content->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_GROUPADD_ID:
                    $xml->addChild('unit_append');
                    $xml->unit_append->addAttribute('action_id', 914);
                    $xml->unit_append->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $xml->unit_append->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $xml->unit_append->addChild('sscc', $this->code);
                    $xml->unit_append->addChild('content');
                    $codes = pghelper::pgarr2arr($this->codes);
                    $d = pghelper::pgarr2arr($this->data);
                    if (empty($d) && Yii::$app->request->getQueryParam('save') !== 'only') {
                        throw new BadRequestHttpException(
                            'Ошибка сохранения операции (' . $this->id . '), обратитесь в техподдержку'
                        );
                    }
                    $product = $this->product;
                    foreach ($codes as $code) {
                        if ($d[0] == 1) {
                            //нужен gtin
                            if (!empty($product)) {
                                $xml->unit_append->content->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $product->nomenclature->gtin . $code)
                                );
                            } else {
                                $c = Code::findOneByCode($code);
                                $xml->unit_append->content->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $c->product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $c->product->nomenclature->gtin . $code)
                                );
                            }
                        } else {
                            $xml->unit_append->content->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_GROUP_ID:
                    $child = $xml->addChild('multi_pack');
                    $child->addAttribute('action_id', 915);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $d = pghelper::pgarr2arr($this->data);
                    if (empty($d) && Yii::$app->request->getQueryParam('save') !== 'only') {
                        throw new BadRequestHttpException(
                            'Ошибка сохранения операции (' . $this->id . '), обратитесь в техподдержку'
                        );
                    }

                    $hasl3 = $this->product->nomenclature->hasl3 ?? false;

                    if (($d[0] == 1 && !$hasl3) || ($hasl3 && $d[0] == 3)) {
                        $content = $child->addChild('by_sgtin');
                    } else {
                        $content = $child->addChild('by_sscc');
                    }

                    $codes = pghelper::pgarr2arr($this->codes_data);
                    $product = $this->product;
                    foreach ($codes as $code) {
                        $detail = $content->addChild('detail');
                        $cc = json_decode($code);
                        $detail->addChild('sscc', $cc->grp);
                        $cont = $detail->addChild('content');
                        foreach ($cc->codes as $c) {
                            if (($d[0] == 1 && !$hasl3) || ($hasl3 && $d[0] == 3)) {
                                if (!empty($product)) {
                                    $cont->addChild(
                                        'sgtin',
                                        ((preg_match(
                                            '#^' . $product->nomenclature->gtin . '#',
                                            $c
                                        )) ? $c : $product->nomenclature->gtin . $c)
                                    );
                                } else {
                                    $cd = Code::findOneByCode($c);
                                    $cont->addChild(
                                        'sgtin',
                                        ((preg_match(
                                            '#^' . $cd->product->nomenclature->gtin . '#',
                                            $c
                                        )) ? $c : $cd->product->nomenclature->gtin . $c)
                                    );
                                }
                            } else {
                                $cont->addChild('sscc', $c);
                            }
                        }
                    }
                    break;
                case static::OPERATION_EMISSION_ID:

                    $child = $xml->addChild('register_product_emission');
                    $child->addAttribute('action_id', 313);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $ri = $child->addChild('release_info');
                    $ri->addChild('doc_date', Yii::$app->formatter->asDate($params["doc_date"], 'php:d.m.Y'));
                    if (!empty($params["doc_num"])) {
                        $ri->addChild('doc_num', $params["doc_num"]);
                    }
                    $ri->addChild('confirmation_num', $params["confirm_doc"] ?? '');
                    $signs = $child->addChild('signs');
                    $a = pghelper::pgarr2arr($this->codes_data);
                    if (is_array($a)) {
                        $cc = json_decode(array_pop($a), true);
                    } else {
                        $cc = null;
                    }
                    if (empty($cc)) {
                        $codes = pghelper::pgarr2arr($this->codes);
                        $cc = [];
                        foreach ($codes as $code) {
                            $c = Code::findOneByCode($code);
                            if (!$c->defected && !$c->removed) {
                                if (!empty($c->parent_code)) {
                                    $cp = Code::findOneByCode($c->parent_code);
                                    if (!empty($cp->parent_code)) {
                                        $cc[$cp->parent_code] = 1;
                                    } else {
                                        $cc[$c->parent_code] = 1;
                                    }
                                } else {
                                    $cc[$code] = 1;
                                }
                            }
                        }
                    }
                    foreach ($cc as $k => $v) {
                        $signs->addChild('sscc', $k);
                    }
                    //                    $this->grpcnt = count($cc);
                    //                    $this->indcnt = $indcnt;
                    //                    $this->save(false);
                    break;
                case static::OPERATION_PACK_ID:
                    //$this->product->expdate_full

                    $xml->addChild('register_end_packing');
                    $xml->register_end_packing->addAttribute('action_id', 311);
                    $xml->register_end_packing->addChild(
                        'subject_id',
                        $params["subject_id"] ?? $this->object->fns_subject_id
                    );
                    $xml->register_end_packing->addChild(
                        'operation_date',
                        $this->dt($params["operation_date"] ?? $this->cdt)
                    );
                    $o = $params["order_type"] ?? $this->product->nomenclature->fns_order_type;
                    $xml->register_end_packing->addChild('order_type', $o);
                    if ($o == 2) {
                        $xml->register_end_packing->addChild(
                            'owner_id',
                            $params["owner_id"] ?? $this->product->nomenclature->fns_owner_id
                        );
                    }
                    $xml->register_end_packing->addChild(
                        'series_number',
                        $params["series_number"] ?? $this->product->series
                    );
                    $xml->register_end_packing->addChild(
                        'expiration_date',
                        str_replace(
                            " ",
                            ".",
                            $params["expiration_date"] ?? $this->product->expdate_full
                        )
                    );
                    $xml->register_end_packing->addChild('gtin', $params["gtin"] ?? $this->product->nomenclature->gtin);
                    //$xml->register_end_packing->addChild('tnved_code', $params["tnved"] ?? $this->product->nomenclature->tnved);
                    $xml->register_end_packing->addChild('signs');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $xml->register_end_packing->signs->addChild(
                            'sgtin',
                            $this->product->nomenclature->gtin . $code
                        );
                    }
                    break;
                case static::OPERATION_CONTROL_ID:
                    $child = $xml->addChild('withdrawal');
                    $child->addAttribute('action_id', 552);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);

                    $dt = new DateTime($params["operation_date"] ?? $this->cdt);
                    $child->addChild('operation_date', $this->dt($dt->format("c")));

                    $fdata = pghelper::pgarr2arr($this->data);
                    if (is_array($fdata) && isset($fdata[0])) {
                        $child->addChild('withdrawal_type', ($params["control_samples_type"] ?? $fdata[0]) + 18);
                    } else {
                        throw new BadRequestHttpException('Не задана переменная: control_samples_type');
                    }

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;

                case static::OPERATION_WDEXT_ID:
                    $child = $xml->addChild('withdrawal');
                    $child->addAttribute('action_id', 552);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);

                    $dt = new DateTime($params["operation_date"] ?? $this->cdt);
                    $child->addChild('operation_date', $this->dt($dt->format("c")));
                    if ((empty($params["doc_num"]) || empty($params["doc_date"])) && Yii::$app->request->getQueryParam(
                            'save'
                        ) !== 'only') {
                        throw new BadRequestHttpException('Нет данных по документу');
                    }
                    $child->addChild('doc_num', $params["doc_num"] ?? '');
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate($params["doc_date"] ?? date("Y-m-d"), 'php:d.m.Y')
                    );
                    $child->addChild('withdrawal_type', $params["withdrawal_type"]);

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_OUTCOME_ID:
                    $child = $xml->addChild('move_owner');
                    $child->addAttribute('action_id', 381);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('owner_id', $params["owner_id"] ?? $this->product->nomenclature->owner->fnsid);
                    //$child->addChild('owner_id', $params["owner_id"] ?? $this->newobject->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    //$child->addChild('storage_change', (isset($params["storage_change"]) ? $params["storage_change"] : "false"));
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate(
                            $params["doc_date"] ?? $this->invoice->invoice_date,
                            'php:d.m.Y'
                        )
                    );
                    $child->addChild('doc_num', $params["doc_num"] ?? $this->invoice->invoice_number);
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_OUTCOMERETAIL_ID:
                    $child = $xml->addChild('move_order');
                    $child->addAttribute('action_id', 415);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('receiver_id', $params["receiver_id"] ?? $this->invoice->dest_fns);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    //$child->addChild('accept_type', $params["accept_type"] ?? 1);
                    $child->addChild('doc_num', $params["doc_num"] ?? $this->invoice->invoice_number);
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate(
                            $params["doc_date"] ?? $this->invoice->invoice_date,
                            'php:d.m.Y'
                        )
                    );
                    $child->addChild('turnover_type', $params["turnover_type"] ?? 1);

                    $child->addChild('source', $params["source"] ?? $this->invoice->source ?? "");
                    $child->addChild('contract_type', $params["contract_type"] ?? "");

                    $contract_num = $params["contract_num"] ?? $this->invoice->contract_num ?? "";
                    if (!empty($contract_num) || ($params["contract_type"] ?? "") == "6" || in_array(
                            ($params["source"] ?? $this->invoice->source ?? ""),
                            ["2", "3"]
                        )) {
                        $child->addChild('contract_num', $contract_num);
                    }

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    if (isset($this->invoice->cost)) {
                        $invoice_data = unserialize($this->invoice->cost);
                    } else {
                        $invoice_data = [];
                    }
                    foreach ($codes as $code) {
                        $union = $details->addChild('union');
                        $c = Code::findOneByCode($code);
                        if (!empty($c)) {
                            if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                                $union->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $c->product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $c->product->nomenclature->gtin . $code)
                                );
                                if (isset($params["gtins"]) && isset($params["gtins"][$c->product->nomenclature->gtin]) && $params["gtins"][$c->product->nomenclature->gtin][0] > 0) {
                                    $cost = $params["gtins"][$c->product->nomenclature->gtin][0];
                                    $cost = round($cost, 2);
                                    if ($params["gtins"][$c->product->nomenclature->gtin][1] > 0) {
                                        $vat = round(
                                            $cost * $params["gtins"][$c->product->nomenclature->gtin][1] / (100 + $params["gtins"][$c->product->nomenclature->gtin][1]),
                                            2
                                        );
                                    } else {
                                        $vat = 0;
                                    }
                                } else {
                                    if (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]["VatValue"] / (100 + $invoice_data[$c->product->nomenclature->gtin]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        //                                throw new \BadMethodCallException('no data for fns 415 (cost/vat)');
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                }
                                if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            } else {
                                $detail = $union->addChild('sscc_detail');
                                $detail->addChild('sscc', (string)$code);
                                $res = Yii::$app->db->createCommand(
                                    "select distinct nomenclature.gtin as realgtin, product.series as gtin from _get_codes_array(:codes) as codes
                                                        left join generations on codes.generation_uid=generations.id
                                                        left join product on codes.product_uid = product.id
                                                        left join nomenclature on product.nomenclature_uid = nomenclature.id
                                                        WHERE codetype_uid = :codetype",
                                    [
                                        ":codes"    => pghelper::arr2pgarr(pghelper::pgarr2arr($c->childrens)),
                                        ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                                    ]
                                )->queryAll();
                                $cost = 0;
                                $vat = 0;
                                foreach ($res as $cd) {
                                    $d = $detail->addChild('detail');
                                    if (isset($params["gtins"]) && isset($params["gtins"][$cd["gtin"]]) && $params["gtins"][$cd["gtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["gtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["gtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["gtin"]][1] / (100 + $params["gtins"][$cd["gtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($params["gtins"]) && isset($params["gtins"][$cd["realgtin"]]) && $params["gtins"][$cd["realgtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["realgtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["realgtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["realgtin"]][1] / (100 + $params["gtins"][$cd["realgtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]["VatValue"] / (100 + $invoice_data[$c->product->nomenclature->gtin]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                    if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                        throw new BadRequestHttpException('Нет данных по стоимости');
                                    }
                                    $vat = round($vat ?? 0, 2);
                                    $d->addChild('gtin', $cd["realgtin"]);
                                    $d->addChild('series_number', $cd["gtin"]);
                                    $d->addChild('cost', $cost ?? 0);
                                    $d->addChild('vat_value', $vat);
                                }

                                if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            }
                        }
                    }
                    break;
                case static::OPERATION_461:
                    $child = $xml->addChild('move_eeu');
                    $child->addAttribute('action_id', 461);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $child->addChild('info_org_eeu', $params["info_org_eeu"] ?? $this->invoice->dest_fns);
                    $child->addChild('doc_num', $params["doc_num"] ?? $this->invoice->invoice_number);
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate(
                            $params["doc_date"] ?? $this->invoice->invoice_date,
                            'php:d.m.Y'
                        )
                    );
                    $child->addChild('contract_type', $params["contract_type"]);

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    if (isset($this->invoice->cost)) {
                        $invoice_data = unserialize($this->invoice->cost);
                    } else {
                        $invoice_data = [];
                    }
                    foreach ($codes as $code) {
                        $union = $details->addChild('union');
                        $c = Code::findOneByCode($code);
                        if (!empty($c)) {
                            if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                                $union->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $c->product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $c->product->nomenclature->gtin . $code)
                                );
                                if (isset($params["gtins"]) && isset($params["gtins"][$c->product->nomenclature->gtin]) && $params["gtins"][$c->product->nomenclature->gtin][0] > 0) {
                                    $cost = $params["gtins"][$c->product->nomenclature->gtin][0];
                                    $cost = round($cost, 2);
                                    if ($params["gtins"][$c->product->nomenclature->gtin][1] > 0) {
                                        $vat = round(
                                            $cost * $params["gtins"][$c->product->nomenclature->gtin][1] / (100 + $params["gtins"][$c->product->nomenclature->gtin][1]),
                                            2
                                        );
                                    } else {
                                        $vat = 0;
                                    }
                                } else {
                                    if (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]["VatValue"] / (100 + $invoice_data[$c->product->nomenclature->gtin]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        //                                throw new \BadMethodCallException('no data for fns 415 (cost/vat)');
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                }
                                if (empty($cost)) {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost);
                                $union->addChild('vat_value', $vat);
                            } else {
                                $detail = $union->addChild('sscc_detail');
                                $detail->addChild('sscc', (string)$code);
                                $res = Yii::$app->db->createCommand(
                                    "select distinct nomenclature.gtin as realgtin, product.series as gtin from _get_codes_array(:codes) as codes
                                                        left join generations on codes.generation_uid=generations.id
                                                        left join product on codes.product_uid = product.id
                                                        left join nomenclature on product.nomenclature_uid = nomenclature.id
                                                        WHERE codetype_uid = :codetype",
                                    [
                                        ":codes"    => pghelper::arr2pgarr(pghelper::pgarr2arr($c->childrens)),
                                        ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                                    ]
                                )->queryAll();
                                $cost = 0;
                                $vat = 0;
                                foreach ($res as $cd) {
                                    $d = $detail->addChild('detail');
                                    if (isset($params["gtins"]) && isset($params["gtins"][$cd["gtin"]]) && $params["gtins"][$cd["gtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["gtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["gtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["gtin"]][1] / (100 + $params["gtins"][$cd["gtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($params["gtins"]) && isset($params["gtins"][$cd["realgtin"]]) && $params["gtins"][$cd["realgtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["realgtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["realgtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["realgtin"]][1] / (100 + $params["gtins"][$cd["realgtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->nomenclature->gtin])) {
                                        $cost = $invoice_data[$c->product->nomenclature->gtin]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->nomenclature->gtin]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->nomenclature->gtin]["VatValue"] / (100 + $invoice_data[$c->product->nomenclature->gtin]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                    if (empty($cost)) {
                                        throw new BadRequestHttpException('Нет данных по стоимости');
                                    }
                                    $vat = round($vat, 2);
                                    $d->addChild('gtin', $cd["realgtin"]);
                                    $d->addChild('series_number', $cd["gtin"]);
                                    $d->addChild('cost', $cost);
                                    $d->addChild('vat_value', $vat);
                                }

                                if (empty($cost)) {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            }
                        }
                    }
                    break;
                case static::OPERATION_OUTCOMERETAILUNREG_ID:
                    $child = $xml->addChild('move_unregistered_order');
                    $child->addAttribute('action_id', 441);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $rinfo = $child->addChild('receiver_info');
                    if (isset($params["regNum"]) && !empty($params["regNum"])) {
                        $rinfo->addChild('receiver_id', $params["regNum"]);
                    } else {
                        $rinn = $rinfo->addChild('receiver_inn');
                        if (preg_match('#^\d{10}$#', $params["inn"])) {
                            $rinn->addChild('ul');
                            $rinn->ul->addChild('inn', $params["inn"]);
                            $rinn->ul->addChild('kpp', $params["kpp"]);
                        } else {
                            $rinn->addChild('fl');
                            $rinn->fl->addChild('inn', $params["inn"]);
                        }
                    }
                    if (!in_array($params["contract_type"], [1, 2, 3, 4])) {
                        $params["contract_type"] = 1;
                    }
                    $child->addChild('contract_type', $params["contract_type"]);
                    $child->addChild('doc_num', $params["doc_num"] ?? $this->invoice->invoice_number);
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate(
                            $params["doc_date"] ?? $this->invoice->invoice_date,
                            'php:d.m.Y'
                        )
                    );

                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    if (isset($this->invoice->cost)) {
                        $invoice_data = unserialize($this->invoice->cost);
                    } else {
                        $invoice_data = [];
                    }
                    foreach ($codes as $code) {
                        $union = $details->addChild('union');
                        $c = Code::findOneByCode($code);
                        if (!empty($c)) {
                            if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                                $union->addChild(
                                    'sgtin',
                                    ((preg_match(
                                        '#^' . $c->product->nomenclature->gtin . '#',
                                        $code
                                    )) ? $code : $c->product->nomenclature->gtin . $code)
                                );
                                if (isset($params["gtins"]) && isset($params["gtins"][$c->product->nomenclature->gtin]) && $params["gtins"][$c->product->nomenclature->gtin][0] > 0) {
                                    $cost = $params["gtins"][$c->product->nomenclature->gtin][0];
                                    $cost = round($cost, 2);
                                    if ($params["gtins"][$c->product->nomenclature->gtin][1] > 0) {
                                        $vat = round(
                                            $cost * $params["gtins"][$c->product->nomenclature->gtin][1] / (100 + $params["gtins"][$c->product->nomenclature->gtin][1]),
                                            2
                                        );
                                    } else {
                                        $vat = 0;
                                    }
                                } else {
                                    if (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                }
                                if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                    throw new BadRequestHttpException('Нет данных по стоимости');
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            } else {
                                $detail = $union->addChild('sscc_detail');
                                $detail->addChild('sscc', (string)$code);
                                $res = Yii::$app->db->createCommand(
                                    "select distinct nomenclature.gtin as realgtin, product.series as gtin from _get_codes_array(:codes) as codes
                                                        left join generations on codes.generation_uid=generations.id
                                                        left join product on codes.product_uid = product.id
                                                        left join nomenclature on product.nomenclature_uid = nomenclature.id
                                                        WHERE codetype_uid = :codetype",
                                    [
                                        ":codes"    => pghelper::arr2pgarr(pghelper::pgarr2arr($c->childrens)),
                                        ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                                    ]
                                )->queryAll();
                                $cost = 0;
                                $vat = 0;
                                foreach ($res as $cd) {
                                    $d = $detail->addChild('detail');
                                    if (isset($params["gtins"]) && isset($params["gtins"][$cd["gtin"]]) && $params["gtins"][$cd["gtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["gtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["gtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["gtin"]][1] / (100 + $params["gtins"][$cd["gtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($params["gtins"]) && isset($params["gtins"][$cd["realgtin"]]) && $params["gtins"][$cd["realgtin"]][0] > 0) {
                                        $cost = $params["gtins"][$cd["realgtin"]][0];
                                        $cost = round($cost, 2);
                                        if ($params["gtins"][$cd["realgtin"]][1] > 0) {
                                            $vat = round(
                                                $cost * $params["gtins"][$cd["realgtin"]][1] / (100 + $params["gtins"][$cd["realgtin"]][1]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } elseif (isset($invoice_data[$c->product->series])) {
                                        $cost = $invoice_data[$c->product->series]["Price"];
                                        $cost = round($cost, 2);
                                        if ($invoice_data[$c->product->series]["VatValue"] > 0) {
                                            $vat = round(
                                                $cost * $invoice_data[$c->product->series]["VatValue"] / (100 + $invoice_data[$c->product->series]["VatValue"]),
                                                2
                                            );
                                        } else {
                                            $vat = 0;
                                        }
                                    } else {
                                        $cost = 0;
                                        $vat = 0;
                                    }
                                    if (empty($cost) && Yii::$app->request->getQueryParam('save') !== 'only') {
                                        throw new BadRequestHttpException('Нет данных по стоимости');
                                    }
                                    //                                $vat = round($vat, 4);
                                    $d->addChild('gtin', $cd["realgtin"]);
                                    $d->addChild('series_number', $cd["gtin"]);
                                    $d->addChild('cost', $cost ?? 0);
                                    $d->addChild('vat_value', (string)($vat ?? 0));
                                }
                                $union->addChild('cost', $cost ?? 0);
                                $union->addChild('vat_value', $vat ?? 0);
                            }
                        }
                    }
                    break;
                case static::OPERATION_OUTCOMESELF_ID:
                    $child = $xml->addChild('move_place');
                    $child->addAttribute('action_id', 431);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('receiver_id', $params["receiver_id"] ?? $this->newobject->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $child->addChild('doc_num', $params["doc_num"] ?? $this->invoice->invoice_number);
                    $child->addChild(
                        'doc_date',
                        Yii::$app->formatter->asDate(
                            $params["doc_date"] ?? $this->invoice->invoice_date,
                            'php:d.m.Y'
                        )
                    );
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_251:
                    $child = $xml->addChild('refusal_sender');
                    $child->addAttribute('action_id', 251);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $child->addChild('receiver_id', $params["receiver_id"] ?? $this->invoice->dest_fns);
                    $child->addChild('reason', $params["reason"] ?? $this->note);
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $details->addChild('sscc', $code);
                        }
                    }
                    break;
                case static::OPERATION_DESTRUCTION_ID:
                    $child = $xml->addChild('move_destruction');
                    $child->addAttribute('action_id', 541);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $descructor = null;
                    if (!empty($params["destruction"])) {
                        $destructor = Destructor::findOne(["id" => $params["destruction"]]);
                    }

                    $org = $child->addChild('destruction_org');
                    $org->addChild('fias_addr', isset($destructor) ? $destructor->aoguid : "");

                    $orgtype = $org->addChild('ul');
                    $orgtype->addChild('inn', isset($destructor) ? $destructor->inn : "");
                    $orgtype->addChild('kpp', isset($destructor) ? $destructor->kpp : "");

                    $child->addChild('doc_num', $params["doc_num"]);
                    $child->addChild('doc_date', Yii::$app->formatter->asDate($params["doc_date"], 'php:d.m.Y'));
                    $child->addChild('act_number', $params["act_number"]);
                    $child->addChild('act_date', Yii::$app->formatter->asDate($params["act_date"], 'php:d.m.Y'));
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        $detail = $details->addChild('detail');
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            $detail->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                        } else {
                            $detail->addChild('sscc', $code);
                        }
                        if ($params["type"] == 2) {
                            $detail->addChild('decision', $params["decision"] ?? '');
                        }
                        $detail->addChild('destruction_type', $params["type"]);
                    }
                    break;
                case static::OPERATION_DESTRUCTIONACT_ID:
                    $child = $xml->addChild('destruction');
                    $child->addAttribute('action_id', 542);
                    $child->addChild('subject_id', $params["subject_id"] ?? $this->object->fns_subject_id);
                    $child->addChild('operation_date', $this->dt($params["operation_date"] ?? $this->cdt));
                    $child->addChild('destruction_method', $params["destruction_method"]);
                    $destructor = null;
                    if (!empty($params["destruction"])) {
                        $destructor = Destructor::findOne(["id" => $params["destruction"]]);
                    }

                    $org = $child->addChild('destruction_org');
                    //                    $adr = $org->addChild('addres');
                    //                    $adr->addChild('aoguid', isset($destructor)?$destructor->aoguid:"");
                    //                    $adr->addChild('houseguid', isset($destructor)?$destructor->houseguid:"");
                    //                    $adr->addChild('flat', isset($destructor)?$destructor->flat:"");
                    $orgtype = $org->addChild('ul');
                    $orgtype->addChild('inn', isset($destructor) ? $destructor->inn : "");
                    $orgtype->addChild('kpp', isset($destructor) ? $destructor->kpp : "");

                    $child->addChild('doc_num', $params["doc_num"]);
                    $child->addChild('doc_date', Yii::$app->formatter->asDate($params["doc_date"], 'php:d.m.Y'));
                    $details = $child->addChild('order_details');
                    $codes = pghelper::pgarr2arr($this->codes);
                    foreach ($codes as $code) {
                        $c = Code::findOneByCode($code);
                        if ($c->codeTypeId == CodeType::CODE_TYPE_INDIVIDUAL) {
                            //$detail = $details->addChild('detail');
                            $details->addChild(
                                'sgtin',
                                ((preg_match(
                                    '#^' . $c->product->nomenclature->gtin . '#',
                                    $code
                                )) ? $code : $c->product->nomenclature->gtin . $code)
                            );
                            //$detail->addChild('destruction_type', $params["type"]);
                        } else {
                            //$detail = $details->addChild('detail');
                            $details->addChild('sscc', $code);
                            //$detail->addChild('destruction_type', $params["type"]);
                        }
                    }
                    break;
                default:
                    throw new BadRequestHttpException('Неизвестный тип операции');
            }
        } catch (\yii\base\Exception $ex) {
            throw new BadRequestHttpException(
                "Ошибка формирования документа, заполните все поля корректно. (" . $ex->getLine() . ")"
            );
        }

        return $xml->asXML();
    }

    /**
     * @return ActiveQuery
     */
    public function getCache()
    {
        return $this->hasMany(UsoCache::className(), ['operation_uid' => 'id']);
    }

    public function getFullProduct()
    {
        $arr = pghelper::pgarr2arr($this->products);
        if (is_array($arr) && count($arr)) {
            return Product::find()->andWhere(['in', 'id', $arr])->all();
        }

        return [];
    }

    /**
     * @return ActiveQuery
     */
    public function getInvoice()
    {
        return $this->hasOne(Invoice::className(), ['id' => 'invoice_uid']);
    }

    /**
     * @return ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::className(), ['id' => 'object_uid'])->where('1=1');
    }

    public function getFullobject()
    {
        return Facility::find()->where(['id' => $this->object_uid])->one();
    }

    public function getNewobject()
    {
        return $this->hasOne(Facility::className(), ['id' => 'newobject_uid'])->where('1=1');
    }

    /**
     * @return ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::className(), ['id' => 'product_uid']);
    }

    /**
     * @return ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * Обработка входящего 601 + uso_cache ответы ФНС документа// попытаемся слепить с 416...
     */
    public function import()
    {
        $content = file_get_contents($this->getFileName());
        $xml = new SimpleXMLElement($content);

        foreach ($xml as $k => $xml_part) {
            break;
        }

        $fnsid = (string)$xml_part->attributes()['action_id'];

        if (in_array($fnsid, ['601', '613', '609', '615'])) {
            $subject_id = (string)$xml_part->subject_id;
            $receiver_id = (string)$xml_part->receiver_id;
        } elseif ($fnsid == '603') {
            $subject_id = (string)$xml_part->subject_id;
            $receiver_id = (string)$xml_part->owner_id;
        } else {
            //416
            $subject_id = (string)$xml_part->shipper_id;
            $receiver_id = (string)$xml_part->subject_id;
        }

        $operation_date = (string)$xml_part->operation_date;   //парсинг
        //$accept_type = (string) $xml_part->accept_type;
        $doc_num = (string)$xml_part->doc_num;
        $doc_date = (string)$xml_part->doc_date;

        if (preg_match('#^\d{2}\.\d{2}\.\d{4}$#i', $doc_date)) {
            $doc_date = preg_replace('#^(\d{2})\.(\d{2})\.(\d{4})$#i', '$3-$2-$1', $doc_date);
        }

        if (isset($xml_part->turnover_type)) {
            $turnover_type = (string)$xml_part->turnover_type;
        }

        if (isset($xml_part->source)) {
            $source = (string)$xml_part->source;
        }

        if (isset($xml_part->contract_type)) {
            $contract_type = (string)$xml_part->contract_type;
        }

        if (empty($contract_type)) {
            $contract_type = 1;
        }


        if (in_array($fnsid, ['613', '609', '615'])) {
            $object = Facility::find()->where(
                [
                    "external" => true,
                    "guid"     => $subject_id,
                ]
            )->orderBy('priority')->one();

            if (empty($object)) {
                $object = new Facility();
                $object->external = true;
                $object->guid = $subject_id;
                $object->name = "Внешний объект";
                $object->save(false);
            }
        } else {
            $object = Facility::find()->where(
                [
                    "external"       => true,
                    "fns_subject_id" => $subject_id,
                ]
            )->orderBy('priority')->one();

            if (empty($object)) {
                $object = new Facility();
                $object->external = true;
                $object->fns_subject_id = $subject_id;
                $object->name = "Внешний объект";
                $object->save(false);
            }
        }

        if (in_array($fnsid, ['613', '609'])) {
            $ourObject = Facility::find()->where(["guid" => $receiver_id])->orderBy('priority')->one();
        } else {
            $ourObject = Facility::find()->where(["fns_subject_id" => $receiver_id])->orderBy('priority')->one();
        }

        $codes = UsoCache::findAll(['operation_uid' => $this->id]);
        $generation = [];//$this->getGenerationForImport($object,CodeType::CODE_TYPE_INDIVIDUAL);
        $generationGrp = $this->getGenerationForImport(
            $object,
            CodeType::CODE_TYPE_GROUP,
            null,
            $doc_num . ' от ' . $doc_date
        );

        $ourGtins = Yii::$app->modules["itrack"]->ourGtins;
        $cont = $realcodes = $fullcodes = [];
        $product_cache = [];
        $code_product = [];
        $gencnt = [];
        $gengrpcnt = 0;
        $is_l3 = false;

        foreach ($codes as $code) {
            if ($code->codetype_uid == 0 || $code->state != 2) {
                $code->delete();

                continue;
            }

            $realcodes[] = $code->code;
            $cont[$code->code] = "Unknown";
            $fullcodes[$code->code] = 1;
            $answer = unserialize($code->answer);

            if ($code->codetype_uid == CodeType::CODE_TYPE_GROUP) {
                //групповой
                $childs = [];
                $grps = [];
                foreach ($answer as $tree) {
                    if (isset($tree["parent_sscc"])) {
                        $parent = $tree["parent_sscc"];
                    } else {
                        $parent = $code->code;
                    }

                    if (isset($tree["sscc"])) {
                        if (isset($tree["parent_sscc"]) && $tree["parent_sscc"] != $code->code) {
                            $is_l3 = true;
                        }

                        //групповой
                        $grps[$tree["sscc"]] = $parent;
                        $fullcodes[$tree["sscc"]] = 1;
                    } elseif (isset($tree["sgtin"])) {
                        //индивидуальный
                        $ind = $tree["sgtin"];
                        if (in_array($tree["gtin"], $ourGtins)) {
                            //к нам вернулся наш код, вырезаем gtin
                            $ind = preg_replace('#^' . $tree["gtin"] . '#si', '', $ind);
                            $table = Code::getRealTable($ind, CodeType::CODE_TYPE_INDIVIDUAL, true);
                        } else {
                            $table = Code::getRealTable($ind, CodeType::CODE_TYPE_INDIVIDUAL, false);
                        }

                        $fullcodes[$ind] = 1;
                        $a = [
                            "gtin"            => $tree["gtin"],
                            "series_number"   => $tree["series_number"],
                            //"tnved_code" => $tree["tnved_code"],
                            "expiration_date" => $tree["expiration_date"],
                        ];
                        $key = $a["gtin"] . $a["series_number"] /*. $a["tnved_code"]*/ . $a["expiration_date"];

                        if (empty($product_cache[$key])) {
                            $product_cache[$key] = $this->getProductForImport($a, $object, $contract_type);
                        }

                        $product = $product_cache[$key];
                        $code_product[$ind] = $product->id;

                        if (empty($generation[$product->id])) {
                            $generation[$product->id] = $this->getGenerationForImport(
                                $object,
                                CodeType::CODE_TYPE_INDIVIDUAL,
                                $product->id,
                                $doc_num . ' от ' . $doc_date
                            );
                        }

                        $gencnt[$generation[$product->id]->id]++;
                        //индивидуальный
                        $cex = Yii::$app->db->createCommand(
                            "SELECT * FROM codes WHERE code=:code",
                            [":code" => $ind]
                        )->queryOne();

                        if (empty($cex)) {
                            Yii::$app->db->createCommand(
                                "
                                INSERT INTO codes (/*activate_date,*/code,flag,product_uid,object_uid,generation_uid,parent_code)
                                VALUES (/*current_date,*/:code,0 | get_mask('NOT EMPTY') | get_mask('RELEASE'),:product,:object,:generation,:parent)",
                                [
                                    //                                    . "                     VALUES (/*current_date,*/:code,0 | get_mask('NOT EMPTY')".(($fnsid == "416")?"":"| get_mask('RELEASE')").",:product,:object,:generation,:parent)", [
                                    ":code"       => $ind,
                                    ":product"    => $product->id,
                                    ":object"     => ($fnsid == "416" ? $object->id : $ourObject->id),
                                    ":generation" => $generation[$product->id]->id,
                                    ":parent"     => $parent,
                                ]
                            )->execute();
                        } else {
                            Yii::$app->db->createCommand(
                                "
                                    UPDATE " . $table . "  SET  /*activate_date= current_date, */flag = 0 | get_mask('NOT EMPTY') | get_mask('RELEASE'), product_uid = :product,object_uid = :object,generation_uid = :generation,parent_code = :parent
                                    WHERE code = :code  "
                                ,
                                [
                                    ":product"    => $product->id,
                                    ":object"     => ($fnsid == "416" ? $object->id : $ourObject->id),
                                    ":generation" => $generation[$product->id]->id,
                                    ":parent"     => $parent,
                                    ":code"       => $ind,
                                ]
                            )->execute();
                        }

                        $childs[$parent][] = $ind;
                    } else {
                        // неизвестный код
                    }
                }

                $grps[$code->code] = "";

                //бежим по grps и добавляем их
                foreach ($grps as $g => $p) {
                    $ch = [];

                    do {
                        $f = false;
                        foreach ($grps as $g1 => $p1) {
                            if (($p1 == $g || in_array($p1, $ch)) && !in_array($g1, $ch)) {
                                $ch[] = $g1;
                                $f = true;
                            }
                        }
                    } while ($f);

                    foreach ($childs as $p1 => $v1) {
                        if (in_array($p1, $ch) || $p1 == $g) {
                            $ch = array_merge($ch, $v1);
                        }
                    }

                    $product_uid = $this->getGroupProductForImport($code_product, $ch);
                    $code_product[$g] = $product_uid;

                    //фича по определению паллеты - пока гвоздем если есть SSCC(18 цифр)
                    //                        $is_paleta = false;
                    //                        foreach($ch as $cc)
                    //                            if(preg_match('#^\d{18}$#',$cc))$is_paleta = true;
                    $code_flag = "";   //" | get_mask('PALETA')"  " | get_mask('L3')"
                    $lu = 0;
                    $gg = $g;

                    while (!empty($grps[$gg])) {
                        $lu++;
                        $gg = $grps[$gg];
                    }

                    $ld = 0;
                    $gg = [$g];

                    while (array_intersect($gg, $grps)) {
                        $ld++;
                        $gg = array_intersect($grps, $gg);
                        if (is_array($gg)) {
                            $gg = array_keys($gg);
                        } else {
                            $gg = [];
                        }
                    }

                    if ($ld == 0) {
                        if ($is_l3) {
                            $code_flag = " | get_mask('L3')";
                        }
                    } elseif ($ld == 1) {
                        if (!$is_l3) {
                            $code_flag = " | get_mask('PALETA')";
                        }
                    } else {
                        $code_flag = " | get_mask('PALETA')";
                    }

                    $gengrpcnt++;

                    $cex = Yii::$app->db->createCommand(
                        "SELECT * FROM codes WHERE code=:code",
                        [":code" => $g]
                    )->queryOne();

                    if (empty($cex)) {
                        Yii::$app->db->createCommand(
                            "
                                INSERT INTO codes (product_uid,/*activate_date,*/code,flag,object_uid,generation_uid,childrens,parent_code)
                                VALUES (:product_uid,/*current_date,*/
                                        :code,
                                        0 | get_mask('NOT EMPTY') | get_mask('RELEASE') " . $code_flag . ",
                                        :object,
                                        :generation,
                                        :childrens,
                                        :parent)",
                            [
                                ":product_uid" => $product_uid,
                                ":code"        => $g,
                                ":object"      => ($fnsid == "416" ? $object->id : $ourObject->id),
                                ":generation"  => $generationGrp->id,
                                ":childrens"   => pghelper::arr2pgarr($ch),
                                ":parent"      => ((empty($p)) ? null : $p),
                            ]
                        )->execute();
                    } else {
                        Yii::$app->db->createCommand(
                            "
                                UPDATE codes SET /*activate_date = current_date, */
                                     product_uid = :product_uid,
                                     flag = 0 | get_mask('NOT EMPTY') | get_mask('RELEASE') " . $code_flag . ",
                                     object_uid = :object,
                                     generation_uid = :generation,
                                     childrens = :childrens,
                                     parent_code = :parent
                                 WHERE code=:code ",
                            [
                                ":product_uid" => $product_uid,
                                ":object"      => ($fnsid == "416" ? $object->id : $ourObject->id),
                                ":generation"  => $generationGrp->id,
                                ":childrens"   => pghelper::arr2pgarr($ch),
                                ":parent"      => ((empty($p)) ? null : $p),
                                ":code"        => $g,
                            ]
                        )->execute();
                    }
                }
            } else {
                //индивидуальный
                if (in_array($answer["gtin"], $ourGtins)) {
                    $code->code = preg_replace('#^' . $answer["gtin"] . '#si', '', $code->code);
                    $table = Code::getRealTable($code->code, CodeType::CODE_TYPE_INDIVIDUAL, true);
                } else {
                    $table = Code::getRealTable($code->code, CodeType::CODE_TYPE_INDIVIDUAL, false);
                }

                $key = $answer["gtin"] . $answer["series_number"]/*.$answer["tnved_code"]*/ . $answer["expiration_date"];

                if (empty($product_cache[$key])) {
                    $product_cache[$key] = $this->getProductForImport($answer, $object, $contract_type);
                }

                $product = $product_cache[$key];
                $code_product[$code->code] = $product->id;

                if (empty($generation[$product->id])) {
                    $generation[$product->id] = $this->getGenerationForImport(
                        $object,
                        CodeType::CODE_TYPE_INDIVIDUAL,
                        $product->id,
                        $doc_num . ' от ' . $doc_date
                    );
                }

                $gencnt[$generation[$product->id]->id]++;

                $cex = Yii::$app->db->createCommand(
                    "SELECT * FROM codes WHERE code=:code",
                    [":code" => $code->code]
                )->queryOne();

                if (empty($cex)) {
                    Yii::$app->db->createCommand(
                        "
                                INSERT INTO codes (/*activate_date,*/code,flag,product_uid,object_uid,generation_uid)
                                VALUES (/*current_date,*/:code, get_mask('NOT EMPTY') | get_mask('RELEASE') ,:product, :object, :generation)",
                        [
                            ":code"       => $code->code,
                            ":product"    => $product->id,
                            ":object"     => ($fnsid == "416" ? $object->id : $ourObject->id),
                            ":generation" => $generation[$product->id]->id,
                        ]
                    )->execute();
                } else {
                    Yii::$app->db->createCommand(
                        "UPDATE " . $table . " SET /*activate_date = current_date, */flag = get_mask('NOT EMPTY') | get_mask('RELEASE'),product_uid = :product,object_uid = :object,generation_uid = :generation WHERE code = :code "
                        ,
                        [
                            ":product"    => $product->id,
                            ":object"     => ($fnsid == "416" ? $object->id : $ourObject->id),
                            ":generation" => $generation[$product->id]->id,
                            ":code"       => $code->code,
                        ]
                    )->execute();
                }
            }

            $code->delete();
        }

        $rootUser = User::findOne(User::SYSTEM_USER);
        //проставляем кол-ва в генерациях
        $fc = 0;
        foreach ($generation as $gen) {
            $fc += $gencnt[$gen->id];
            $gen->cnt += $gencnt[$gen->id];
            $gen->cnt_src += $gencnt[$gen->id];
            $gen->save(false);
        }

        if ($gengrpcnt > 0) {
            $generationGrp->cnt += $gengrpcnt;
            $generationGrp->cnt_src += $gengrpcnt;
            $generationGrp->save(false);
        } else {
            if (!$generationGrp->cnt) {
                $generationGrp->delete();
            }
        }

        if (empty($this->invoice_uid)) {
            $invoice = new Invoice;
            $invoice->scenario = 'external';
            $invoice->load(
                [
                    'invoice_number' => $doc_num,
                    'invoice_date'   => $doc_date,
                    'codes'          => pghelper::arr2pgarr(array_keys($fullcodes)),
                    'created_by'     => $rootUser->id,
                    'object_uid'     => $object->id,
                    'newobject_uid'  => $ourObject->id,
                    'realcodes'      => pghelper::arr2pgarr($realcodes),
                    'updated'        => true,
                    'turnover_type'  => $turnover_type,
                    'contract_type'  => $contract_type,
                    'content'        => json_encode($cont),
                    'dest_consignee' => $ourObject->name ? $ourObject->name : '',
                    'dest_address'   => $ourObject->address ? $ourObject->address : '',
                ],
                ''
            );
            $invoice->save(false);
            $invoice->refresh();
            $this->invoice_uid = $invoice->id;

            Yii::$app->db->createCommand(
                "UPDATE operations SET invoice_uid=:invoice 
                                                            WHERE operation_uid=:op and invoice_uid is null and prev_uid=:prev and created_at>=:created",
                [
                    ':invoice' => $invoice->id,
                    ':op'      => self::OPERATION_211,
                    ':prev'    => $this->id,
                    ':created' => $this->created_at,
                ]
            )->execute();
        }

        if (in_array($fnsid, ['601', '613', '603', '609', '615'])) {
            $this->fnsid = $fnsid;
            $this->scenario = 'update601';
            $this->state = static::STATE_COMPLETED;
            $this->object_uid = $object->id;
            $this->full_codes = pghelper::arr2pgarr(array_keys($fullcodes));

            $p = [];
            foreach ($product_cache as $pr) {
                $p[] = $pr->id;
            }

            if (count($p) > 1) {
                $this->products = pghelper::arr2pgarr($p);
            } elseif (count($p) == 1) {
                $this->product_uid = $p[0];
            }

            $this->save(false);
            $this->makeNotify();
        }

        return $fc;
    }

    /**
     * Возвращает товарную карту для группвого кода, если все внутри однородные - то товарная карта, если разнородное то null
     *
     * @param array $products - массив код => товарная карта
     * @param array $codes - масив числдов
     *
     * @return integer идетификатор товарной карты для группового кода по чилдам из $codes
     */
    public function getGroupProductForImport(array $products = [], array $codes = [])
    {
        $ret = null;
        foreach ($codes as $i => $code) {
            if ($i == 0) {
                $ret = $products[$code];
            } else {
                if ($ret != $products[$code]) {
                    return null;
                }
            }
        }

        return $ret;
    }

    public function getGenerationForImport(Facility $object, $codeType, $product_uid = null, $comment = '')
    {
        $generation = Generation::findOne(
            [
                'codetype_uid' => $codeType,
                'object_uid'   => $object->id,
                'product_uid'  => $product_uid,
                'comment'      => $comment
            ]
        );
        $rootUser = User::findOne(User::SYSTEM_USER);
        if (empty($generation)) {
            $generation = new Generation();
            $generation->scenario = "external";
            $generation->load(
                [
                    'codetype_uid' => $codeType,
                    'status_uid'   => GenerationStatus::STATUS_READY,
                    'created_by'   => $rootUser->id,
                    //                'comment' => 'генерация для внешних кодов',
                    'object_uid'   => $object->id,
                    'cnt'          => 0,
                    'capacity'     => '0',
                    'prefix'       => '',
                    'product_uid'  => $product_uid,
                    'comment'      => $comment,
                ],
                ''
            );
            $generation->save(false);
            $generation->refresh();
        }

        return $generation;
    }

    public function getProductForImport($answer, Facility $object, $contract_type)
    {
        // попытка получить наименование номенклатуры
        $name = null;
        $manufacturerName = null;
        $code1c = null;
        $i = Yii::$app->params['invoice'];
        $axapta = Connector::getActive(['Axapta'], 1, $object->id);

        if (!empty($axapta)) {
            $i = [
                'check'  => true,
                'login'  => $axapta->data['user'],
                'passwd' => $axapta->data['password'],
                'url'    => $axapta->data['url']
            ];
        }

        if (isset($i['check']) && $i['check'] == true) {
            try {
                Yii::getLogger()->log('Запрос номенклатуры по GTIN: ' . $answer['gtin'], Logger::LEVEL_INFO, 'axapta');

                define('USERPWD', $i['login'] . ':' . $i['passwd']);
                stream_wrapper_unregister('http');
                stream_wrapper_register('http', 'app\modules\itrack\components\NTLMStream');
                $params = [
                    'stream_context'     => stream_context_create(
                        [
                            'ssl' => [
                                'ciphers'             => 'RC4-SHA',
                                'verify_peer'         => false,
                                'verify_peer_name'    => false,
                                'allow_static_signed' => true,
                            ],
                        ]
                    ),
                    'cache_wsdl'         => WSDL_CACHE_NONE,
                    'soap_version'       => SOAP_1_1,
                    'trace'              => 1,
                    'connection_timeout' => 180,
                    'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
                ];
                $soap = new NTLMSoapClient($i['url'], $params);
                $data = $soap->getItemInfo(['GTIN' => $answer['gtin']]);
                Yii::getLogger()->log('Ответ аксапты: ' . print_r($data, true), Logger::LEVEL_INFO, 'axapta');
            } catch (Exception $ex) {
                echo 'Ошибка получения данных от аксапты';
                error_clear_last();
                exit;
            }
            if (!empty($data)) {
                $name = $data->response->Name;
                $manufacturerName = $data->response->ManufacturingSiteId;
                $code1c = $data->response->ItemId;
            }
        }
        Yii::getLogger()->log("Итог: $name | $manufacturerName | $code1c", Logger::LEVEL_INFO, 'axapta');

        if (empty($manufacturerName)) {
            $manufacturerName = 'Сторонний производитель';
        }
        //"gtin";s:14:"11170012610151";s:13:"series_number";s:7:"3021017";s:15:"expiration_date";s:10:"01.07.2017";s:10:"tnved_code";s:4:"3004"
        //nomenclature gtin tnved
        //product series expdate + nomenclature

        $rootUser = User::findOne(User::SYSTEM_USER);
        $manufacturer = Manufacturer::findOne(['name' => $manufacturerName, 'external' => true]);

        if (empty($manufacturer)) {
            $manufacturer = new Manufacturer();

            $manufacturer->load(
                [
                    'name'     => $manufacturerName,
                    'external' => true,
                ],
                ''
            );
            $manufacturer->save(false);
            $manufacturer->refresh();
        }

        $nomenclature = Nomenclature::findOne(['gtin' => $answer['gtin']]);

        if (empty($nomenclature)) {
            $nomenclature = new Nomenclature();

            $nomenclature->load(
                [
                    'name'             => ((!empty($name)) ? $name : 'ЛП стороннего производителя, gtin: ' . $answer['gtin']),
                    'created_by'       => $rootUser->id,
                    'cnt'              => 0,
                    'object_uid'       => $object->id,
                    'gtin'             => $answer['gtin'],
                    'manufacturer_uid' => $manufacturer->id,
                    'tnved'            => $answer['tnved_code'] ?? '',
                    'fns_order_type'   => $contract_type,
                    'code1c'           => (empty($code1c) ? '' : $code1c),
                    'ean13'            => substr($answer['gtin'], -13),
                ],
                ''
            );

            $nomenclature->save(false);
            $nomenclature->refresh();
        }

        $expdate = str_replace('.', ' ', $answer['expiration_date']);

        $product = Product::findOne(
            [
                'nomenclature_uid' => $nomenclature->id,
                'series'           => $answer['series_number'],
                //            'expdate'          => $expdate,
            ]
        );

        if (empty($product)) {
            $product = new Product();
            $product->load(
                [
                    'nomenclature_uid' => $nomenclature->id,
                    'series'           => $answer['series_number'],
                    'created_by'       => $rootUser->id,
                    'expdate'          => $expdate,
                    'expdate_full'     => $expdate,
                    'object_uid'       => $object->id,
                    'cdate'            => '-',
                ],
                ''
            );
            $product->save(false);
            $product->refresh();
        }

        return $product;
    }

    /**
     * Запуск отправки документов по серии за текущим
     */
    public function startSeries()
    {
        Yii::$app->db->createCommand(
            "
                UPDATE
                operations SET state = :newState
                WHERE state = :state AND
                      created_at >= :created AND
                      dbschema = get_constant('schema') AND
                      prev_uid = :prev",
            [
                ':newState' => static::STATE_READY,
                ':state'    => static::STATE_ERRORSTOPED,
                ':created'  => $this->created_at,
                ':prev'     => $this->id,
            ]
        )->execute();
    }


    //для переноса в OCS

    /**
     * Рассылка оповещений при ошибке отправки документа
     */
    public function makeNotify()
    {
        /** @var FnsNotifyEvent $notifyEvent */
        $notifyEvent = Yii::createObject(FnsNotifyEvent::className());
        $notifyEvent->setFns($this);

        Event::trigger(FnsNotifyEvent::class, FnsNotifyEvent::EVENT_SEND_NOTIFY, $notifyEvent);
    }

    public function getNotifyAttach(): array
    {
        $attach = [];

        try {
            if ($this->fnsid == 441) {
                $this->fnsid = 552;
                $this->operation_uid = static::OPERATION_WDEXT_ID;

                $attach[$this->getFileName(false)] = $this->xml(
                    [
                        'withdrawal_type' => 14,
                        'doc_num'         => isset($this->invoice->invoice_number) ? $this->invoice->invoice_number : '',
                        'doc_date'        => isset($this->invoice->invoice_date) ? $this->invoice->invoice_date : '',
                    ]
                );

                $this->fnsid = 415;
                $this->operation_uid = static::OPERATION_OUTCOMERETAIL_ID;
                $attach[$this->getFileName(false)] = $this->xml();

                $this->fnsid = 441;
                $this->operation_uid = static::OPERATION_OUTCOMERETAILUNREG_ID;
                $ismdata = json_decode($this->invoice->ismdata, true);
                $attach['answer_ism.txt'] = print_r($ismdata, true);

                $params = unserialize($this->fns_params);
                $params['regNum'] = $ismdata['filtered_records'][0]['system_subj_id'] ?? 'unknown';
                $attach[str_replace(
                    '-441.',
                    '-441regNum.',
                    $this->getFileName(false)
                )] = $this->xml($params);
            } elseif ($this->fnsid == 415) {
                $this->fnsid = 552;
                $this->operation_uid = static::OPERATION_WDEXT_ID;
                $attach[$this->getFileName(false)] = $this->xml(
                    [
                        'withdrawal_type' => 14,
                        'doc_num'         => isset($this->invoice->invoice_number) ? $this->invoice->invoice_number : '',
                        'doc_date'        => isset($this->invoice->invoice_date) ? $this->invoice->invoice_date : '',
                    ]
                );

                $this->fnsid = 441;
                $this->operation_uid = static::OPERATION_OUTCOMERETAILUNREG_ID;
                $attach[$this->getFileName(false)] = $this->xml(['regNum' => 'Unknown']);

                $this->fnsid = 251;
                $this->operation_uid = static::OPERATION_251;
                $attach[$this->getFileName(false)] = $this->xml(
                    [
                        'subject_id'     => $this->object->fns_subject_id,
                        'operation_date' => $this->cdt,
                        'receiver_id'    => $this->invoice->dest_fns,
                        'reason'         => '',
                    ]
                );

                $this->fnsid = 415;
                $this->operation_uid = static::OPERATION_OUTCOMERETAIL_ID;
            } elseif ($this->fnsid == 601 && $this->state == Fns::STATE_COMPLETED) {
                $this->fnsid = 252;
                $this->internal = true;
                $this->operation_uid = static::OPERATION_252;
                $attach[$this->getFileName(false)] = $this->xml(
                    [
                        'subject_id'     => $this->newobject->fns_subject_id,
                        'operation_date' => $this->cdt,
                        'shipper_id'     => $this->object->fns_subject_id,
                        'reason'         => '',
                    ]
                );

                $this->fnsid = 601;
                $this->internal = false;
                $this->operation_uid = static::OPERATION_601;
            } elseif ($this->fnsid == 606) {
                $this->fnsid = 552;
                $this->internal = true;
                $this->operation_uid = static::OPERATION_WDEXT_ID;
                $uns = unserialize($this->fns_params);
                $attach[$this->getFileName(false)] = $this->xml(
                    [
                        'withdrawal_type' => 14,
                        'doc_num'         => 'Unknown',
                        'doc_date'        => date('Y-m-d'),
                        'subject_id'      => $uns['shipper_id'],
                    ]
                );

                $this->fnsid = 606;
                $this->internal = false;
                $this->operation_uid = static::OPERATION_606;
            }
        } catch (Exception $ex) {
        }

        return $attach;
    }


    /**
     * Проверка возможно ли отправлять текущую операцию - т.е. все ли были успешные перед текущей операцией
     *
     * @return boolean   статус проверки - если - true - можно отправлять
     * @throws DbException
     */
    public function checkPrevious()
    {
        $p = pghelper::pgarr2arr($this->products);
        $p[] = $this->product_uid;
        //надо проверить по товарной карте предыдущую операцию - если она не отправлена, то не отправляем текущую...(игнорим операции утилизации и выпуска - так как они отправляются вручную)
        $ops = Yii::$app->db->createCommand(
            "SELECT * FROM operations
                        WHERE
                            dbschema = get_constant('schema') AND
                              fns_start_send <= coalesce(:creat, now()) AND
                              operation_uid NOT IN (:op1, :op2, :op3) AND
                              operation_uid < 200 AND
                              state IN (0,1,2,3,4,5,6,9,14,22,44)  --задали явно, раньше было все кроме 8,7
                              AND
                              (products || product_uid) && '" . pghelper::arr2pgarr($p) . "'::bigint[] AND
                               (CASE WHEN cardinality(full_codes)>0 then full_codes else (select array_agg(code) from get_full_codes(case when code is null then coalesce(codes,'{}') else coalesce(codes,'{}') || code end)) end) && :full_codes AND
                                id!=:id
                        ORDER by fns_start_send,id
                        LIMIT 1"
            ,
            [
                ':creat'      => $this->fns_start_send,
                ':op1'        => static::OPERATION_DESTRUCTIONACT_ID,
                ':op2'        => static::OPERATION_DESTRUCTION_ID,
                ':op3'        => static::OPERATION_EMISSION_ID,
                ':full_codes' => $this->full_codes,
                ':id'         => $this->id,
            ]
        )->queryOne();

        if (!empty($ops)) {
            if (in_array(
                $ops['state'],
                [Fns::STATE_RESPONCE_ERROR, Fns::STATE_ERRORSTOPED, Fns::STATE_SEND_ERROR, Fns::STATE_RESPONCE_PART]
            )) {
                $this->state = Fns::STATE_ERRORSTOPED;
            }
            $this->prev_uid = $ops['id'];

            return false;
        }


        //операция перемещения - надо проверить, а отправлена ли 313 (у нас не все серии прошли полный цикл операций - типа фича)
        if (in_array($this->fnsid, [/*381, 431, */ 701, 415, 441])) {
            $ops = Yii::$app->db->createCommand(
                '
                        SELECT * FROM operations
                        WHERE
                                dbschema = get_constant(\'schema\') AND
                              fns_start_send <= :creat AND
                              operation_uid = :op AND
                              state in (0,1,2,3,4,5,6,9,14,22)  --задали явно, раньше было все кроме 7,8
                              AND
                              ' . ((empty($this->product_uid)) ? " product_uid=ANY('" . ((empty($this->products)) ? "{}" : $this->products) . "')" : ' product_uid=' . $this->product_uid) . "
                              AND (case when cardinality(full_codes)>0 then full_codes else (select array_agg(code) from get_full_codes(case when code is null then coalesce(codes,'{}') else coalesce(codes,'{}') || code end)) end) && :full_codes
                              AND id!=:id
                        LIMIT 1"
                ,
                [
                    //                            ':curcodes' => \app\modules\itrack\components\pghelper::arr2pgarr($codes),
                    ':creat'      => $this->fns_start_send,
                    ':op'         => static::OPERATION_EMISSION_ID,
                    ':full_codes' => $this->full_codes,
                    ':id'         => $this->id,
                ]
            )->queryOne();

            if (!empty($ops)) {
                if (in_array(
                    $ops['state'],
                    [
                        Fns::STATE_RESPONCE_ERROR,
                        Fns::STATE_ERRORSTOPED,
                        Fns::STATE_SEND_ERROR,
                        Fns::STATE_RESPONCE_PART,
                    ]
                )) {
                    $this->state = Fns::STATE_ERRORSTOPED;
                }
                $this->prev_uid = $ops['id'];

                return false;
            }
        }

        return true;
    }

    /**
     * Парсинг ответа от OCS
     *
     * @param type $params
     *
     * @return type
     */
    public function parseTQSanswer($params)
    {
        if (empty($this->cdata)) {
            $ret = [];
            $a = pghelper::pgarr2arr($this->data);
            try {
                if (is_array($a)) {
                    $ret = unserialize($a[0]);
                }
            } catch (Exception $ex) {
            }
            $this->cdata = $ret;
        }
        switch ($this->cdata['type']) {
            case 'push-serial-numbers-request':
                $ocsid = $this->cdata['ocs'];
                $ocs = Ocs::findOne(['id' => $ocsid]);
                if (!empty($ocs)) {
                    $generations = pghelper::pgarr2arr($ocs->generations);
                    $gen_ind = Generation::find()->andWhere(['id' => $generations[0]])->one();
                    $gen_gofra = Generation::find()->andWhere(['id' => $generations[1]])->one();
                    $gen_pallet = Generation::find()->andWhere(['id' => $generations[2]])->one();

                    if ($this->state == Fns::STATE_TQS_CONFIRMED) {
                        //принято
                        if (!empty($gen_ind)) {
                            $gen_ind->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                            $gen_ind->save(false, ['status_uid']);
                        }
                        if (!empty($gen_gofra)) {
                            $gen_gofra->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                            $gen_gofra->save(false, ['status_uid']);
                        }
                        if (!empty($gen_pallet)) {
                            $gen_pallet->status_uid = GenerationStatus::STATUS_CONFIRMEDWOADDON;
                            $gen_pallet->save(false, ['status_uid']);
                        }
                    } elseif ($this->state == Fns::STATE_TQS_DECLAINED) {
                        //отклонено
                        $err = (isset($params['fns_log']) && !empty($params['fns_log'])) ? $params['fns_log'] : 'Нераспознанная ошибка';
                        if (!empty($gen_ind)) {
                            $gen_ind->status_uid = GenerationStatus::STATUS_DECLINED;
                            $gen_ind->comment = "Ошибка отправки на оборудование ($err)";
                            $gen_ind->save(false, ['status_uid', 'comment']);
                        }
                        if (!empty($gen_gofra)) {
                            $gen_gofra->status_uid = GenerationStatus::STATUS_DECLINED;
                            $gen_gofra->comment = "Ошибка отправки на оборудование ($err)";
                            $gen_gofra->save(false, ['status_uid', 'comment']);
                        }
                        if (!empty($gen_pallet)) {
                            $gen_pallet->status_uid = GenerationStatus::STATUS_DECLINED;
                            $gen_pallet->comment = "Ошибка отправки на оборудование ($err)";
                            $gen_pallet->save(false, ['status_uid', 'comment']);
                        }
                    }
                }
                break;
            case 'create-order':
                $gen = $this->cdata['generation'];
                $generation = Generation::find()->andWhere(['id' => $gen])->one();
                if (!empty($generation)) {
                    if ($this->state == Fns::STATE_TQS_CONFIRMED) {
                        $generation->status_uid = GenerationStatus::STATUS_CONFIRMED;
                        //TQS если просто экспорт - не создаем - это сотекс, автоматической упаковки нет
                        $res = Yii::$app->db->createCommand("SELECT get_constant('TQS') as a")->queryOne();
                        if (!($res['a'] == 'true')) {
                            $eq = Equip::findOne($this->cdata['equip_uid']);
                            if ($eq->data == '2') {
                                $tqs = new TqsSession();
                                $tqs->load(
                                    [
                                        'generation_uid' => $generation->id,
                                        'state'          => 0,
                                        'equip_uid'      => $this->cdata['equip_uid'],
                                        'created_by'     => $this->created_by
                                    ],
                                    ''
                                );
                                $tqs->save(false);
                            }
                        }
                    } elseif ($this->state == Fns::STATE_TQS_DECLAINED) {
                        $err = (isset($params["fns_log"]) && !empty($params["fns_log"])) ? $params["fns_log"] : "Нераспознанная ошибка";
                        $generation->comment = "Ошибка отправки на оборудование ($err)";
                        $generation->status_uid = GenerationStatus::STATUS_DECLINED;
                    }
                    $generation->save(false);
                }
                break;
            case 'get-order-status-request':
                try {
                    $xml = new SimpleXMLElement($params["fns_log"]);
                    $tqs_session = $this->cdata["tqs_session"];
                    $tqs = TqsSession::findOne(['id' => $tqs_session]);
                    if (!empty($tqs)) {
                        if ($this->state == Fns::STATE_TQS_DECLAINED) {
                            if (empty($tqs->nerrors)) {
                                $tqs->nerrors = 1;
                            } else {
                                $tqs->nerrors++;
                            }
                            if ($tqs->nerrors > 5) {
                                $tqs->state = -1;
                            }
                        } else {
                            if (1 == (integer)$xml->response->status) {
                                $tqs->state = (string)$xml->response->status;
                            } else {
                                Yii::$app->db->createCommand(
                                    "DELETE FROM operations where id=:id and created_at=:created_at",
                                    [
                                        ":id"         => $this->id,
                                        ":created_at" => $this->created_at,
                                    ]
                                )->execute();
                                //$this->delete();
                            }
                        }
                        $tqs->save(false);
                    }
                } catch (Exception $ex) {
                }
                break;
            case 'query-serial-numbers-request':
                //ответ со статусами серийников
                if ($this->state == static::STATE_TQS_CONFIRMED) {
                    $tqs_session = $this->cdata["tqs_session"];
                    $tqs = TqsSession::findOne(['id' => $tqs_session]);
                    if (empty($tqs)) {
                        //не найдена сессия!!!
                        return;
                    }
                    Yii::$app->db->createCommand(
                        "INSERT INTO ocs_data (generation_uid,equip_uid,data) VALUES (:generation_uid,:equip_uid,:data)",
                        [
                            ":generation_uid" => $tqs->generation_uid,
                            ":equip_uid"      => $tqs->created_by,
                            ":data"           => $params["fns_log"],
                        ]
                    )->execute();
                }
                break;
            case "get-article-fields-request":
                if ($this->state == static::STATE_TQS_CONFIRMED) {
                    $ocs = Ocs::findOne(['id' => $this->cdata["ocs"]]);
                    if (!empty($ocs)) {
                        $ret = [];
                        try {
                            $xml = new SimpleXMLElement($params["fns_log"]);
                            if (trim($xml->response->result->{'return-value'}) == 'ok') {
                                $data = $xml->response->{'article-data-descriptions'};
                                foreach ($data->{'aggregation-level'} as $lvl) {
                                    $df = $lvl->{'data-field-descriptions'};

                                    foreach ($df->element as $el) {
                                        $ret[trim($lvl->id)][] = trim($el->name);
                                    }
                                }
                            }
                        } catch (Exception $ex) {
                        }
                        $ocs->article_data = serialize($ret);
                        $ocs->save(false);
                    }

                    break;
                }
        }
    }

    /**
     * Отмена принятого документа
     *
     * @throws BadRequestHttpException
     */
    public function decline($check_state = true)
    {
        if ($check_state && !in_array($this->state, [self::STATE_RESPONCE_SUCCESS])) {
            throw new BadRequestHttpException('Нельзя отменить данный документ');
        }

        try {
            $dec = self::createDoc(
                [
                    'created_by'    => Yii::$app->user->identity->id,
                    'operation_uid' => self::OPERATION_250,
                    'state'         => self::STATE_CREATED,
                    'object_uid'    => $this->object_uid,
                    'fnsid'         => '250',
                    'note'          => 'Отмена',
                    'prev_uid'      => $this->id,
                ]
            );

            $this->state = self::STATE_RESPONCE_SUCCESS_DECLINE;
            $this->prev_uid = $dec->id;
            $this->save(false, parent::attributes());
        } catch (Exception $ex) {
            throw new BadRequestHttpException('Ошибка отмены операции: ' . $ex->getMessage());
        }
    }

    protected function dt($t)
    {
        return date('Y-m-d\TH:i:sP', Yii::$app->formatter->asTimestamp($t));
    }
}
