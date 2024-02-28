<?php
/**
 * @link http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use Yii;
use app\modules\itrack\components\boxy\Helper;
use yii\base\InvalidConfigException;
use yii\data\SqlDataProvider;
use yii\db\Command;
use yii\db\Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotAcceptableHttpException;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\AuditLog;
use app\modules\itrack\models\AuditOperation;

use app\modules\sklad\models\cache\History as HistoryCache;
use app\modules\sklad\models\replica\History as HistoryReplica;
use app\modules\itrack\components\boxy\ActiveRecord;

/**
 * Class Code
 *      Основная сущность - уникальный код
 *      в базе хранятся как перснальные коды, так и групповые
 *      Коды могут стрится в дерево - для привязки персональных к групповым
 *
 *
 * @property integer $id - Идентифкатор
 * @property string $code  - Код
 * @property integer $generation_uid  - ССылка на генерацию - заявку
 * @property integer $parent_uid   - Ссылка на родителя (групповой код) может быть null
 * @property integer $flag      - Статус кода - сгенерирова, активирован, брак, утилизация, в продаже и тд - побитная маска
 * @property integer $ucnt   - Кол-во проверок данного кода - потребителями
 * @property integer $product_code - Ссылка на товарную карточку (если null - то не активирован)
 * @property integer $code_sn — SN Code
 *
 * @property string $release_date — дата поступления в реализацию
 * @property string $activate_date — дата поступления в реализацию
 * @property integer $object_uid — объект
 * @property string $lmtime
 *
 * @property Generation $generation
 * @property array $childGroup
 * @property string $statusMessage
 * @property Product $product
 *
 * Методы:
 *  - просмотр
 *  - проверка кода (Чек сумм)
 *  - получение содержимого групповых кодов
 *  ...
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Code",
 *      type="object",
 *      properties={
 *          @OA\Property(property="code", type="string", example="123123123"),
 *          @OA\Property(property="status", type="array", @OA\Items()),
 *          @OA\Property(property="childs", type="array", @OA\Items()),
 *          @OA\Property(property="statusMessage", type="string", example="На объекте"),
 *          @OA\Property(property="codeType", ref="#/components/schemas/app_modules_itrack_models_Code_Type"),
 *          @OA\Property(property="product", ref="#/components/schemas/app_modules_itrack_models_Product"),
 *          @OA\Property(property="object_uid", type="integer", example=2),
 *          @OA\Property(property="release_date", type="string", example=null),
 *          @OA\Property(property="activate_date", type="string", example="2020-03-20"),
 *          @OA\Property(property="parent_code", type="string", example="123123"),
 *          @OA\Property(property="generation_uid", type="string", example="1234-1234-123"),
 *      }
 * )
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Code_Individual",
 *      type="object",
 *      properties={
 *          @OA\Property(property="id", type="integer", example=123),
 *          @OA\Property(property="code", type="string", example="123123123"),
 *          @OA\Property(property="generation_uid", type="string", example="1234-1234-123"),
 *          @OA\Property(property="nomenclature_uid", type="integer", example=2),
 *          @OA\Property(property="product_uid", type="integer", example=2),
 *          @OA\Property(property="flag", type="integer", example=1),
 *          @OA\Property(property="codetype", type="string", example="Индивидуальный"),
 *          @OA\Property(property="parent_code", type="string", example="123123"),
 *          @OA\Property(property="object_name", type="string", example="Тестовый"),
 *          @OA\Property(property="nomenclature", type="string", example="Авастин ®, концентрат"),
 *          @OA\Property(property="childrens", type="string", example=null),
 *          @OA\Property(property="flags", type="string", example="{}"),
 *          @OA\Property(property="series", type="string", example="234234"),
 *      }
 * )
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Code_Group",
 *      type="object",
 *      properties={
 *          @OA\Property(property="code", type="string", example="123123123"),
 *          @OA\Property(property="parent_code", type="string", example="123123"),
 *          @OA\Property(property="nomenclature", type="string", example="Авастин ®, концентрат"),
 *          @OA\Property(property="flags", type="string", example="{}"),
 *          @OA\Property(property="codetype", type="string", example="Групповой"),
 *          @OA\Property(property="series", type="string", example="234234"),
 *          @OA\Property(property="childrens", type="array",
 *              @OA\Items(
 *                  @OA\Property(property="code", type="string", example="123123123"),
 *                  @OA\Property(property="parent_code", type="string", example="123123"),
 *                  @OA\Property(property="nomenclature", type="string", example="Авастин ®, концентрат"),
 *                  @OA\Property(property="flags", type="string", example="{}"),
 *                  @OA\Property(property="codetype", type="string", example="Индивидуальный"),
 *                  @OA\Property(property="series", type="string", example="234234"),
 *                  @OA\Property(property="childrens", type="array", @OA\Items()),
 *              )
 *          ),
 *      }
 * )
 */
class Code extends ActiveRecord
{
    protected $_attributes = [];

    public $empty;
    public $claim;
    public $removed;
    public $released;
    public $defected;
    public $retail;
    public $gover;
    public $blocked;
    public $paleta;
    public $l3;
    public $serialized;
    public $brak;
    
    public static $ShortLevels = [
        '0' => 'Упаковка',
        '1' => 'Гофрокороб',
        '2' => 'Паллета',
    ];
    public static $ExtLevels = [
        '0' => 'Упаковка',
        '1' => 'Бандероль',
        '2' => 'Гофрокороб',
        '3' => 'Паллета',
    ];

    const CODE_FLAG_BOX = 1;
    const CODE_FLAG_PALETA = 512;
    const CODE_FLAG_L3 = 1024;
    const CODE_FLAG_SERIALIZED = 2048;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        if(SERVER_RULE != SERVER_RULE_SKLAD)
            return 'codes';
        else
            return 'codes_cache';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['code', 'generation_uid'], 'required'],
            [['flag', 'ucnt', 'product_uid', 'object_uid'], 'integer'],
            [['parent_code', 'generation_uid'], 'string'],
            [['code'], 'string', 'max' => 20],
            [['release_date','activate_date', 'lmtime'], 'safe'],
            [['generation_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Generation::className(), 'targetAttribute' => ['generation_uid' => 'id']],
            [['product_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['product_uid' => 'id']],
            [['empty', 'claim', 'removed', 'released', 'defected', 'retail', 'gover', 'blocked', 'paleta', 'id','childrens'], 'safe'],
        ];
    }

    
    public function attributes()
    {
        $attr = [
            'id', 'code', 'generation_uid', 'parent_code', 'flag', 'ucnt', 'product_uid', 'activate_date', 'release_date', 'object_uid', 'lmtime', 'childrens', 'childs','statusMessage'
        ];
        if (SERVER_RULE == SERVER_RULE_SKLAD)
        {
            $attr = array_merge($attr,['cachehistory']);
        }
        return $attr;
    }

    public function fields()
    {
        $fields = [
            'uid' => 'id',
            'code',
            'status' => function () {
                $result = [];

                if ($this->empty) $result[] = 'empty';
                if ($this->claim) $result[] = 'claim';
                if ($this->removed) $result[] = 'removed';
                if ($this->released) $result[] = 'released';
                if ($this->defected) $result[] = 'defected';
                if ($this->retail) $result[] = 'retail';
                if ($this->gover) $result[] = 'gover';
                if ($this->blocked) $result[] = 'blocked';
                if ($this->paleta) $result[] = 'paleta';
                if ($this->l3) $result[] = 'l3';
                if ($this->serialized) $result[] = 'serialized';
                if ($this->brak) $result[] = 'brak';

                return $result;
            },
            'childs' => function(){
                if ($this->childrens == '{NULL}') return null; //фича из за крипты.. пока гвоздь
                return pghelper::pgarr2arr($this->childrens);
            },
            'statusMessage' => function(){
                return $this->getStatusMessage(false);
            },
            'codeType',
            'generation',
            'product',
            'ucnt',
            'object_uid',
            'release_date' => function () {
                return (!empty($this->release_date)) ? Yii::$app->formatter->asDate($this->release_date) : null;
            },
            'activate_date' => function () {
                return (!empty($this->activate_date)) ? Yii::$app->formatter->asDate($this->activate_date) : null;
            },
            'childGroup' => function () {
                $childGroup = $this->getChildGroup(false);
                return ($childGroup) ? $childGroup : null;
            },
            'maxCount',
            'parent_code'
        ];
        if (SERVER_RULE == SERVER_RULE_SKLAD)
            $fields = array_merge($fields,[
                'cachehistory'
            ]);
        return $fields;    
    }
    
    /**
     * Очистка кода(выделение из разных Serial, Gs1, Sgtin и тп)
     * @param type $code
     * @param type $forcegtin
     * @return type
     */
    static function stripCode($code, $forcegtin = false)
    {
        //serial
        if(isset(\Yii::$app->params['serialMask']) && !empty(\Yii::$app->params['serialMask']) && preg_match('#^'. \Yii::$app->params['serialMask'] .'$#',$code))
        {
            $res = \Yii::$app->db->createCommand('SELECT * FROM _get_code_by_serial(:serial)',[":serial" => $code])->queryOne();
            if(!empty($res) && !empty($res["code"]))return pg_escape_string($res["code"]);
        }
        
        if(preg_match('#^\(00\)#si',$code))
                $code = substr($code,4);
        //sgtin
        if(strlen($code)>18 && strlen($code)<28)
        {
            if(in_array(substr($code, 0, 14), \Yii::$app->modules['itrack']->ourGtins))
            {
                if($forcegtin)
                    return $code;
                else
                    return substr($code, 14);
            }
        }
        
        //gs1
        $gtin = "";
        $code = str_replace('~1',chr(29),$code);
        if(preg_match('#^\]d[12]#si',$code))
        {
            $code = substr($code,3);
        }
        $len = strlen($code);
        
        if(strstr($code, chr(29)) || $len>27)
        {
            $p = strpos($code, chr(29));
            if($p === 0) {$code=substr($code,1);$len--;}
            do
            {
                if($len>2)
                {
                    $prefix = substr($code,0,2);
                    $code = substr($code, 2);
                    $len-=2;
                    switch($prefix)
                    {
                        case '01': //GTIN 14 знаков
                            if($len>=14)
                            {
                                $gtin = substr($code, 0, 14);
                                $code = substr($code, 14);
                                $len-=14;
                            }
                            else 
                            {
                                $len = 0;$code = "";
                            }
                            break;
                        case '17': //
                            if($len>=6)
                            {
                                $code = substr($code,6);
                                $len-=6;
                            }
                            else 
                            {
                                $len = 0;$code = "";
                            }
                            break;
                        case '10': //BATCH переменной длины
                            $p = strpos($code, chr(29));
                            if($p === false)
                            {
                                $len = 0;$code = "";
                            }
                            else
                            {
                                $code = substr($code, $p+1);
                                $len-=($p+1);
                            }
                            break;
                        case '21':
                            $p = strpos($code, chr(29));
                            if($p !== false)
                            {
                                $code = substr($code,0, $p);
                                $len = 0;
                            }
                            //@file_put_contents(\Yii::getAlias('@codePath').'/', $prefix)
                            //return $code;
                            break 2;
                            break;
                    }
                }
                else {
                    $len = 0;
                }
            }    
            while($len>0);
        }
        //file_put_contents(\Yii::getAlias("@reportPath") . "/codes.txt", ' => '.$code.'/'.$gtin . PHP_EOL, FILE_APPEND);
        if(empty($gtin))
            return pg_escape_string($code);
        if(in_array($gtin,\Yii::$app->modules["itrack"]->ourGtins))
        {
            if($forcegtin)
            {
                return pg_escape_string($gtin.$code);
            }
            else
            {
                return pg_escape_string($code);
            }
        }
        else
            return pg_escape_string($gtin.$code);
    }
    
    /**
     * Для обхода проверок и ускорения обработки
     * @param string $code  код
     * @param int $type      тип кода
     * @param boolean $our  флаг наш или внешний
     * @return string
     */
    static function getRealTable($code, $type = CodeType::CODE_TYPE_INDIVIDUAL, $our = true)
    {
        if(!$our)
            return 'codes_external';
        if($type == CodeType::CODE_TYPE_GROUP)
            return 'codes_grp';
        return 'codes.codes_'.substr($code, 0, 4);
    }

    /**
     * Максимальное количество кодов в коробке
     * для тех кто может упаковывать однородные коды
     *
     * @return int|null
     */
    public function getMaxCount()
    {
        if (Yii::$app->user->can('codeFunction-group')) {
            if (isset($this->product) && isset($this->product->nomenclature)) {
                return $this->product->nomenclature->cnt;
            }
            return 0;
        }
        return null;
    }

    public function extraFields()
    {
        return [
            'flag',

            'generation_uid',
            'product_uid',
            'parent',
            'codetype_uid' => function () {
                return $this->generation->codetype_uid;
            },

            'dataMatrixUrl',
            'checkUrl',
            'viewUrl',

            'parent',
            'child',

            'history',

            'retailInvoice',

            'object',
            'gtin',
            'code_sn',
            'oneDeepChild',
            'contentByProduct',
            'paletraCode',
            'l3Code',

//            'childGroupNotDeep' => function () {
//                return $this->getChildGroup(false);
//            },
//            'childGroup'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public static function find()
    {
        $query = parent::find();
        $query->select([
            '*',
            'is_empty(codes.flag) as empty',
            'is_claim(codes.flag) as claim',
            'is_removed(codes.flag) as removed',
            'is_released(codes.flag) as released',
            'is_defected(codes.flag) as defected',
            'is_retail(flag) as retail',
            'is_blocked(flag) as blocked',
            'is_paleta(flag) as paleta',
            'is_gover(flag) as gover',
            'is_l3(flag) as l3',
            'is_serialized(flag) as serialized',
            'is_brak(flag) as brak',
        ]);
        return $query;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'код, доступный алфавит (0,1,2,3,4,5,6,7,8,9,A,B,C,E,T,P,H,K,X,M)',
            'generation_uid' => 'ID генерации',
            'parent' => 'родитель (иерархия кодов)',
            'product_uid' => 'товарная карточка',
            'flag' => 'статус кода (флаги в таблице code_types)',
            'ucnt' => 'общее кол-во проверок кода (checks.ucnt)',
        ];
    }

    /**
     * Получение типа кода
     *
     * @return CodeType
     */
    public function getCodeType()
    {
        return $this->generation->codeType;
    }

    /**
     * Получение id типа кода
     *
     * @return int
     */
    public function getCodeTypeId()
    {
        return $this->generation->codetype_uid;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChecks()
    {
        return $this->hasMany(Check::className(), ['code_uid' => 'id']);
    }

//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getCodeData() {
//        return $this->hasOne(CodeData::className(), ['codeid' => 'id']);
//    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGeneration()
    {
        return $this->hasOne(Generation::className(), ['id' => 'generation_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHistories()
    {
        return $this->hasMany(History::className(), ['code_uid' => 'id']);
    }

    /**
     * 
     * 
     * @param type $code
     * @return string $code
     */
    public function getTopParentBySql($code)
    {
        do
        {
            $res = \Yii::$app->db->createCommand("SELECT * FROM _get_codes('code=''".pg_escape_string($code)."'')")->queryOne();
        }while(!empty($res['parent_code']));
        return $res['code'];
    }
    
    /**
     * Получение родителя от текущего кода
     * @return type
     */
    public function getParent()
    {
        if (empty($this->parent_code)) {
            return null;
        }

        $row = new Code();
        $row = $row->findOneByCode($this->parent_code);
        return $row;
    }

    /**
     * получение SQL все прямых потомков от текущего кода
     * @return type
     */
    public function getChild()
    {
        $sql = Yii::$app->db->createCommand("
                            select *,
                              is_empty(flag) as empty,
                              is_claim(flag) as claim,
                              is_removed(flag) as removed,
                              is_released(flag) as released,
                              is_defected(flag) as defected,
                              is_retail(flag) as retail,
                              is_blocked(flag) as blocked,
                              is_paleta(flag) as paleta,
                              is_gover(flag) as gover,
                              is_l3(flag) as l3,
                              is_serialized(flag) as serialized,
                              is_brak(flag) as brak
                            from _get_codes_array2('".(!empty($this->childrens)?$this->childrens:"{}")."','parent_code=''" . $this->code . "''') 
        ");

        return $sql->getRawSql();
    }

    public function getProduct()
    {
        return $this->hasOne(Product::className(), ['id' => 'product_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::className(), ['id' => 'object_uid']);
    }

    public static function incomeReverse(array $codes, $invoice, $invoiceDate)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(empty($codes))
            throw new \yii\web\BadRequestHttpException('Не передан массив кодов');
        
        if (!\Yii::$app->user->can('codeFunction-incomeReverse'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        
        $inv = new Invoice();
        $inv->invoice_number = $invoice;
        $inv->invoice_date = $invoiceDate;
        $inv->updateVendor(false);
        if(!empty($inv->vatvalue))
            throw new NotAcceptableHttpException($inv->vatvalue);

        $result = Invoice::createQuarantine($codes, $invoice, $invoiceDate);
        AuditLog::Audit(AuditOperation::OP_CODES, 'Приемка кодов (обратное акцептование)', [
            ['field'=>'Коды','value'=>$codes], 
            ['field' => 'Номер накладной', 'value' => $invoice], 
            ['field' => 'Дата накладной', 'value' => $invoiceDate]
        ]);
        return $result;
    }
    
    public static function relabel($codes, $newcodes) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        $newcodes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $newcodes);

        if (!\Yii::$app->user->can('codeFunction-relabel'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand('select make_relabel(:userID, :codes, :newcodes)', [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':newcodes' => new Expression(pghelper::arr2pgarr($newcodes)),
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, 'Переупаковка', [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => 'Новые коды', 'value' => $newcodes],
        ]);
        return $result;
    }

    
    public static function assign($code,$serial)
    {
        $mask = "";
        if(isset(\Yii::$app->params['serialMask']))
            $mask = \Yii::$app->params['serialMask'];
        if(empty($mask))
            return ['2','Mask not set'];
        if(!preg_match('#^'.$mask.'$#',$serial))
                return ['3',"Серийный код не соответствует маске: $serial"];
        $cd = Code::findOneByCode($code);
        if(empty($cd))
            return ['1',"Код не найден: $code"];
        try
        {
            $query = \Yii::$app->db->createCommand('INSERT INTO serials (serial,code) VALUES (:serial,:code)', [
                ':serial' => $serial,
                ':code' => $code,
            ]);
            $query->execute();
        } catch (Exception $ex) {
            return ["2", "Некорректный серийный код: $serial"];
        }

        AuditLog::Audit(AuditOperation::OP_CODES, 'Присвоение кода', [
            ['field' => 'Код', 'value' => $code],
            ['field' => 'Серийный код', 'value' => $serial],
        ]);

        return ['0','Ok'];
    }
    
    
    /**
     * Сериализация кодов
     *
     * @param array $codes
     * @param $note
     *
     * @return array
     */
    public static function serialize($code, $s_shift, $s_date, $s_zakaz, $s_brak, $s_brak_flag, $s_fio, $equipid) {

        $query = \Yii::$app->db->createCommand('select make_serialized(:userID, :code, :shift, :date, :zakaz, :brak, :brakflag, :equipid)', [
            ':userID' => $s_fio,
            ':code' => $code,
            ':shift' => $s_shift,
            ':date' => $s_date,
            ':zakaz' => $s_zakaz,
            ':brak' => $s_brak,
            ':brakflag' => $s_brak_flag,
            ':equipid' => $equipid,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, 'Сериализация', [
            ['field' => 'Код', 'value' => $code],
            ['field' => 'Смена', 'value' => $s_shift],
            ['field' => 'Дата', 'value' => $s_date],
            ['field' => 'Заказ', 'value' => $s_zakaz],
            ['field' => 'Брак', 'value' => $s_brak],
            ['field' => 'Брак флаг', 'value' => $s_brak_flag],
            ['field' => 'ФИО', 'value' => $s_fio],
            ['field' => 'Идентификатор оборудования', 'value' => $equipid],
        ]);
        return $result;
    }

    /**
     * Сериализация кодов - множественно
     *
     * @param array $codes
     * @param $note
     *
     * @return array
     */
    public static function serializeMultiple($serializationData) {

        $isBrak = $isNotBrak = [];
        $cantReSerialization = Constant::get('cantReSerialization');
        $authorizedUser = Yii::$app->user->getIdentity();
        $extData = [];

        $transaction = Yii::$app->db->beginTransaction();
        $sourceCodes = array_keys($serializationData);
        
        try
        {
            $codes = Yii::$app->db->createCommand('SELECT 
                                                        codes.*,
                                                        codetype_uid, 
                                                        generations.object_uid::text || \' / \' || generations.num::text as zakaz,
                                                        series, name
                                                    FROM _get_codes_array(:codes) as codes
                                                    LEFT JOIN generations ON generations.id = codes.generation_uid
                                                    LEFT JOIN product ON codes.product_uid = product.id
                                                    LEFT JOIN nomenclature ON nomenclature.id = product.nomenclature_uid
                                                 ',
                            [':codes' => pghelper::arr2pgarr(array_keys($serializationData))]
                    )->queryAll();

            foreach ($codes as $code) {
                if ($code['codetype_uid'] != CodeType::CODE_TYPE_INDIVIDUAL)
                    throw new \Exception('Код не является индивидуальным: ' . $code['code']);
                if ($cantReSerialization === 'true' && Code::isSerialized($code['flag']))
                    throw new \Exception('Код был сериализован ранее: ' . $code['code']);
                if(Code::isSerialized($code['flag']))
                {
                    unset($serializationData[$code['code']]);
                    continue;
                }
                
                if(empty($serializationData[$code['code']]['s_zakaz']))
                {
                    $serializationData[$code['code']]['s_zakaz'] = $code['zakaz'];
                }
                
                $key = $code['series'] . '_' . $serializationData[$code['code']]['equipid'] . '_' . $serializationData[$code['code']]['s_date'] . '_' . $serializationData[$code['code']]['s_shift'] . '_' . $serializationData[$code['code']]['s_zakaz'] . '_' . $authorizedUser->fio;

                if(!isset($extData[$key]))
                {
                    $extData[$key] = [
                        'notBrak' => 0,
                        'brak' => 0,
                        'brakInfo' => [
                            'A' => 0,
                            'B' => 0,
                            'C' => 0,
                            'D' => 0,
                            'E' => 0,
                            'F' => 0,
                            'G' => 0,
                        ],
                        'params' => [
                            'params1' => $code['series'],
                            'typeof'  => 'reportRafarma7',
                            'params2' => $serializationData[$code['code']]['equipid'],
                            'params3' => $serializationData[$code['code']]['s_date'],
                            'data1'   => $serializationData[$code['code']]['s_shift'],
                            'data2'   => $serializationData[$code['code']]['s_zakaz'],
                            'data3'   => $code['name'],
                            'data4'   => $authorizedUser->fio,
                        ]
                    ];
                }
                
                if ($serializationData[$code['code']]['s_brak_flag']) 
                {
                    $isBrak[$code['code']] = $serializationData[$code['code']];
                    $extData[$key]['brakInfo'][$serializationData[$code['code']]['s_brak']]++;
                    $extData[$key]['brak']++;
                } 
                else 
                {
                    $isNotBrak[$code['code']] = $serializationData[$code['code']];
                    $extData[$key]['notBrak']++;
                }
                
                unset($serializationData[$code['code']]);
            }
            
            if(count($serializationData))
            {
                throw new \Exception('Не найдены коды: ' . implode(',', array_keys($serializationData)));
            }
                
            /**
             * сохранение брака
             */
            if(count($isBrak))
            {
                Yii::$app->db->createCommand("SELECT _update_codes_array(
                                                            :codes,
                                                                'id,
                                                                code,
                                                                parent_code,
                                                                flag | get_mask(''SERIALIZED'') | get_mask(''BRAK'') as flag,
                                                                ucnt,
                                                                product_uid,
                                                                activate_date,
                                                                release_date,
                                                                object_uid,
                                                                now() as lmtime,
                                                                childrens,
                                                                generation_uid',
                                                            :message,
                                                                " . Code::getHistoryType([
                                                                    'operation_uid' => 61, //HistoryOperation:: сделать константы
                                                                    'created_by' => $authorizedUser->id,
                                                                    'object_uid' => $authorizedUser->object_uid,
                                                                ]) . ",
                                                            ''
                                                        )",
                        [
                            ':codes' => pghelper::arr2pgarr(array_keys($isBrak)),
                            ':message' => sprintf('Пользователь %s на объекте %s: брак сериализации <code>', $authorizedUser->fio, $authorizedUser->object->name),
//                            ':history_type' => Code::getHistoryType([
//                                    'operation_uid' => 61, //HistoryOperation:: сделать константы
//                                    'created_by' => $authorizedUser->id,
//                                    'object_uid' => $authorizedUser->object_uid,
//                                ]),
                        ]
                    )->execute();
            }
            
            /**
             * сохранение НЕ брака
             */
            if (count($isNotBrak)) {
                Yii::$app->db->createCommand("SELECT _update_codes_array(
                                                            :codes,
                                                                'id,
                                                                code,
                                                                parent_code,
                                                                flag | get_mask(''SERIALIZED'') as flag,
                                                                ucnt,
                                                                product_uid,
                                                                activate_date,
                                                                release_date,
                                                                object_uid,
                                                                now() as lmtime,
                                                                childrens,
                                                                generation_uid',
                                                            :message,
                                                                " . Code::getHistoryType([
                                                                    'operation_uid' => 61, //HistoryOperation:: сделать константы
                                                                    'created_by' => $authorizedUser->id,
                                                                    'object_uid' => $authorizedUser->object_uid,
                                                                ]) . ",
                                                            ''
                                                        )",
                        [
                            ':codes' => pghelper::arr2pgarr(array_keys($isNotBrak)),
                            ':message' => sprintf('Пользователь %s на объекте %s сериализировал <code>', $authorizedUser->fio, $authorizedUser->object->name),
//                            ':history_type' => Code::getHistoryType([
//                                'operation_uid' => 61, //HistoryOperation:: сделать константы
//                                'created_by' => $authorizedUser->id,
//                                'object_uid' => $authorizedUser->object_uid,
//                            ]),
                        ]
                )->execute();
            }
            
            /**
             * Сохраняем extData по сериализации
             */
            foreach($extData as $ext)
            {
                $res = Yii::$app->db->createCommand('SELECT *   
                                                                FROM extdata 
                                                                WHERE 
                                                                    params1 = :params1
                                                                    and typeof = :typeof
                                                                    and params2 = :params2
                                                                    and params3 = :params3
                                                                    and data1 = :data1
                                                                    and data2 = :data2
                                                                    and data3 = :data3
                                                                    and data4 = :data4
                                                   ', $ext['params'])->queryOne();
                
                if(!empty($res))
                {
                    $brakInfo = json_decode($res['data7'], true);
                    foreach($brakInfo as $s_brak=>$s_cnt)
                    {
                        $ext['brakInfo'][$s_brak] += $s_cnt;
                    }
                    Yii::$app->db->createCommand('UPDATE extdata    
                                                        SET
                                                            data5 = (data5::bigint + :allcodes)::varchar,
                                                            data6 = (data6::bigint + :brak)::varchar,
                                                            data7 = :data7
                                                        WHERE id = :id
                                                ',[
                                                    ':allcodes' => $ext['brak'] + $ext['notBrak'],
                                                    ':brak' => $ext['brak'],
                                                    ':data7' => json_encode($ext['brakInfo']),
                                                    ':id' => $res['id']
                                                ]
                            )->execute();
                }
                else
                {
                    Yii::$app->db->createCommand('INSERT INTO extdata 
                                                    (created_by,object_uid,params1,params2,params3,data1,data2,data3,data4,data5,data6,data7,typeof) 
                                                    VALUES
                                                    (:created_by,:object_uid,:params1,:params2,:params3,:data1,:data2,:data3,:data4,:data5,:data6,:data7,:typeof) 
                                                 ',
                                                 array_merge($ext['params'], [
                                                     ':created_by' => $authorizedUser->id,
                                                     ':object_uid' => $authorizedUser->object_uid,
                                                     ':data5' => $ext['brak'] + $ext['notBrak'],
                                                     ':data6' => $ext['brak'],
                                                     ':data7' => json_encode($ext['brakInfo']),
                                                 ])
                            )->execute();
                }
            }

            AuditLog::Audit(AuditOperation::OP_CODES, 'Сериализация', [
                ['field' => 'Коды', 'value' => implode(',', array_keys($sourceCodes))],
                ['field' => 'Данные', 'value' => print_r($serializationData, true)],
            ]);
            $transaction->commit();
            $result = ['0', 'Ok'];
    
        } catch (\Exception $ex) {
            $result =  ['1', $ex->getMessage()];
        }
        
        return $result;
    }
    
    /**
     * Возвращает строку для PG типа hh_type 
     * 
     * SELECT attname,typname
     *           FROM pg_class c JOIN pg_attribute a ON c.oid = a.attrelid JOIN pg_type t ON a.atttypid = t.oid
     *           WHERE c.relname = 'hh_type'
     *           order by attnum
     * 
     * требуемые параметры:
     * 
     * "created_at";"timestamptz"
     * "operation_uid";"int8"
     * "code_uid";"int8"
     * "created_by";"int8"
     * "data";"varchar"
     * "object_uid";"int8"
     * "product_uid";"int8"
     * "address";"varchar"
     * "comment";"varchar"
     * "shopname";"varchar"
     * "content";"varchar"
     * "invoice_uid";"uuid"
     * 
     * @param array $params
     * @return string формата (now(),52,null,2420,null,null,null,null,null,null,null,null)::hh_type
     */
    public static function getHistoryType(array $params) :string
    {
        $strArray = [];
        
        //TODO нужна проверка если там строка надо заключать в кавычки  или через BIND ?       
        $strArray[] = (!empty($params['created_at'])) ? $params['created_at'] : 'timestamptz(timeofday())';  

        $strArray[] = (!empty($params['operation_uid'])) ? $params['operation_uid'] : '1';  //TODO надо дефолтный тип хистори!
        
        $strArray[] = (!empty($params['code_uid'])) ? $params['code_uid'] : 'null';
        
        $strArray[] = (!empty($params['created_by'])) ? $params['created_by'] : 'null';
        $strArray[] = (!empty($params['data'])) ? $params['data'] : 'null';
        $strArray[] = (!empty($params['object_uid'])) ? $params['object_uid'] : 'null';
        $strArray[] = (!empty($params['product_uid'])) ? $params['product_uid'] : 'null';
        $strArray[] = (!empty($params['address'])) ? $params['address'] : 'null';
        $strArray[] = (!empty($params['comment'])) ? $params['comment'] : 'null';
        $strArray[] = (!empty($params['shopname'])) ? $params['shopname'] : 'null';
        $strArray[] = (!empty($params['content'])) ? $params['content'] : 'null';
        $strArray[] = (!empty($params['invoice_uid'])) ? $params['invoice_uid'] : 'null';

        return '(' . implode(', ', $strArray) . ')::hh_type';
    }
    
    /**
     * Проверка флага кода на флаг сериализации
     * 
     * @param int $flag
     * @return int   0 не сериализован 
     */
    public static function isSerialized(int $flag): int
    {
        return $flag & Code::CODE_FLAG_SERIALIZED;
    }

    /**
     * Утилизация кодов
     *
     * @param array $codes
     * @param $note
     *
     * @return array
     */
    public static function removeWeb(array $codes, $note)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        
        if($note == 'Вывод из оборота КИЗ, накопленных в рамках эксперимента')
            $type = 'ext9';
        elseif($note == 'Списание без передачи на уничтожение')
            $type = 'ext8';
        elseif($note == 'Брак')
            $type = 'brak';
        elseif($note == 'Истечение срока годности')
            $type = 'srok';
        elseif($note == 'Бой')
            $type = 'boi';
        else
            $type = 'other';
        if(!\Yii::$app->user->can('codeFunction-remove-web-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        
        $query = \Yii::$app->db->createCommand('select make_removed_web(:userID, :codes, :type, :note)', [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => $type,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, 'Утилизация (WEB)', [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => 'Примечание', 'value' => $note]
                ]);
        return $result;
    }
    
    /**
     * Внутренняя блокировка кодов
     * 
     * @param array $codes массив с кодами для блокировки
     * @return type
     */
    public static function brak(array $codes)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        $query = \Yii::$app->db->createCommand('select make_removed_internal(:userID, :codes)', [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, 'Брак (внутренняя)', [
            ['field' => 'Коды', 'value' => $codes]
                ]);
        return $result;
    }

    /**
     * Устарело - было для 1с рафармы-сотекса
     * 
     * @param array $codes
     * @param type $qrcode
     * @param type $permname
     * @param type $oparams
     * @return boolean
     */
    public static function checkQR(array $codes, $qrcode, $permname, $oparams = [])
    {
        
        $reports = [];
        $auth = \Yii::$app->authManager;
        $permission = $auth->getPermission($permname);
        if (isset($permission->data->needQR) && $permission->data->needQR === true) 
        {
            $qr = \app\modules\itrack\components\OdinSqr::parse($qrcode, $oparams);
            if($qr->error)
                $reports[] = 'Некорректный формат QR кода';
            if(!in_array($permname, $qr->permission))
                $reports[] = "Некорректный операция документа: $permname";
            if(in_array($permname,['codeFunction-income-log', 'codeFunction-income-prod', 'codeFunction-incomeExt', 'codeFunction-outcome-prod' , 'codeFunction-outcome-log', "codeFunction-retail-log", "codeFunction-retail-prod"]))
            {
                //операция приемки/перемещения/отгрузки - надо сравнить номера накладных в QR
                if($oparams['invoice_number'] != $qr->docId)
                    $reports[] = "Несоответствие номера накладной $qr->docId <> " . $oparams['invoice_number'];
                if($oparams['invoice_date'] != $qr->docDate)
                    $reports[] = "Несоответствие даты накладной $qr->docDate <> " . $oparams['invoice_date'];
            }
            
            if(in_array($permname,['codeFunction-retail-log', 'codeFunction-retail-prod']))
            {
                //операция отгрузка - надо список кодов найти в ранее загруженном 0800
                $found = false;
                $res = \Yii::$app->db->createCommand("SELECT * FROM operations WHERE operation_uid=:op and state=:state and created_at>current_date - 7",[
                    ":op" => Fns::OPERATION_1C_IN,
                    ":state" => Fns::STATE_1CCOMPLETED
                ])->queryAll();
                foreach($res as $doc)
                {
                    $params = unserialize($doc["fns_params"]);
                    if($params["action_id"] == '0800' && $oparams["invoice_number"] == $doc["doc_num"] && $oparams["invoice_date"] == $doc["doc_date"])
                    {
                        $found = true;
                        break;
                    }
                }
                if(!$found)
                    $reports[] = "Нет разрешающего документа 1C (0800)";
                else
                {
                    $qr->setGtins($params["gtins2"]);
                }
            }
            
            if (in_array($permname, ['codeFunction-income-log', 'codeFunction-income-prod', 'codeFunction-incomeExt']))
            {
                //операции приемки - нам не передают массив gtins - сравнение должно осуществляться по отсканированным кодам
                $res = \Yii::$app->db->createCommand("SELECT realcodes FROM invoices WHERE invoice_number=:num and invoice_date=:date ORDER by created_at desc limit 1",[
                    ":num" => $oparams["invoice_number"],
                    ":date" => $oparams["invoice_date"],
                ])->queryOne();
                $realcodes = pghelper::pgarr2arr($res["realcodes"]);
                $arr1 = array_diff($realcodes, $codes);
                $arr2 = array_diff($codes, $realcodes);
                if(count($arr1))
                    $reprots[] = "Вы отсканировали кодов меньше, чем в накладной на: ". count($arr1)." шт";
                if(count($arr2))
                    $reprots[] = "Вы отсканировали кодов больше, чем в накладной на: ". count($arr2)." шт";
            }
            else
            {
                //проверка по gtins
                if(empty($reports))
                {

                    $res = \Yii::$app->db->createCommand("select series,gtin,count(*) as cnt from (
                                                                    select distinct unnest(coalesce(childrens,array[code])) as code from _get_codes_array(:codes)
                                                            ) as a
                                                            left join codes ON a.code = codes.code
                                                            LEFT JOIN generations ON codes.generation_uid = generations.id
                                                            LEFT JOIN product ON codes.product_uid = product.id
                                                            LEFT JOIN nomenclature ON product.nomenclature_uid = nomenclature.id
                                                            WHERE codetype_uid = :codetype
                                                            GROUP by 1,2
                    ", [
                        ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                        ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                    ])->queryAll();
                    foreach($res as $r)
                    {
                        $found = false;
                        foreach ($qr->gtins as $k=>$v)
                        {
                            if(($v[0] == $r["gtin"]) && ($v[1] == $r["series"]))
                            {
                                $found = true;
                                if($v[2] == $r["cnt"])
                                {
                                    unset($qr->gtins[$k]);
                                }
                                elseif($v[2]<$r["cnt"])
                                {
                                    $reports[] = "Серия ($v[1]]), GTIN ($v[0]]) в документе меньше, чем отсканировано ($v[2]<${r["cnt"]})";
                                }
                                else
                                {
                                    $reports[] = "Серия ($v[1]]), GTIN ($v[0]]) в документе больше, чем отсканировано ($v[2]>${r["cnt"]})";
                                }
                            }
                        }
                        if(!$found)
                            $reports[] = "Серия (${r["series"]}), GTIN (${r["gtin"]}) не содержится в документе (${r["cnt"]})";
                    }
                    foreach($qr->gtins as $v)
                        $reports[] = "Серия ($v[1]]), GTIN ($v[0]]) не была отсканирована ($v[2])";

                }
            }
            //file_put_contents(\Yii::$aliases["@codePath"] . '/qr.log', print_r($reports, true), FILE_APPEND);
        }

        if(!empty($reports)) 
        {
            //создаем репорт об ошибке  //// успех создается в СУБД
            $fns = new Fns;
            $fns->load([
                "operation_uid" => Fns::OPERATION_1C_OUT,
                "state" => Fns::STATE_1CPREPARING,
                "created_by" => \Yii::$app->user->getIdentity()->id,
                "object_uid" => \Yii::$app->user->getIdentity()->object_uid,
                "data" => pghelper::arr2pgarr([
                    /*$qr->xmltype*/ '0201',
                    $qr->docId,
                    $qr->docDate,
                    $qr->odinSnum,
                    1,
                    serialize($reports),
            ]),
                "docid" => $qr->docId,
            ],'');
            $fns->save();

            $trans = \Yii::$app->db->getTransaction();
            $trans->commit();
            
            return false;
        }
        if(!empty($qr->docId)) //ИД для отчета об успехе!!!
        {
            \Yii::$app->db->createCommand("SELECT set_config('itrack.docid',:docid,true)", [":docid" => $qr->docId])->execute();
            \Yii::$app->db->createCommand("SELECT set_config('itrack.docdate',:p,true)", [":p" => $qr->docDate])->execute();
            \Yii::$app->db->createCommand("SELECT set_config('itrack.odinsnum',:p,true)", [":p" => $qr->odinSnum])->execute();
        }
        return true;
    }
    
    
    public static function removeTSD(array $codes, $note, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if($note == "Вывод из оборота КИЗ, накопленных в рамках эксперимента")
            $type = "ext8";
        elseif($note == "Списание без передачи на уничтожение")
            $type = "ext8";
        elseif($note == "Брак")
            $type = "brak";
        elseif($note == "Истечение срока годности")
            $type = "srok";
        elseif($note == "Бой")
            $type = "boi";
        else
            $type = "other";
        if(!\Yii::$app->user->can('codeFunction-removed-tsd-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        if(!self::checkQR($codes, $qrcode, 'codeFunction-removed-tsd-' . $type))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_removed_tsd(:userID, :codes, :type, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => $type,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Утилизация TSD", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
            ['field' => "QR", 'value' => $qrcode]
                ]);
        return $result;
    }
    public static function removeWebByDate($note, $bdate, $edate, $series)
    {
        $bdate = Yii::$app->formatter->asDate($bdate);
        $edate = Yii::$app->formatter->asDate($edate);

        if($note == "Вывод из оборота КИЗ, накопленных в рамках эксперимента")
            $type = "ext9";
        elseif($note == "Списание без передачи на уничтожение")
            $type = "ext8";
        elseif($note == "Брак")
            $type = "brak";
        elseif($note == "Истечение срока годности")
            $type = "srok";
        elseif($note == "Бой")
            $type = "boi";
        elseif ($note == "Отправка продукции на уничтожение")
            $type = "util";
        else
            $type = "other";
        if(!\Yii::$app->user->can('codeFunction-remove-web-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_removed_web_by_date(:userID, :note, :type, :bdate, :edate, :series)", [
            ':userID' => Yii::$app->user->getId(),
            ':note' => $note,
            ':bdate' => $bdate,
            ':edate' => $edate,
            ':series' => $series,
            ':type' => $type,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Утилизация WEB по дате упаковки", [
            ['field' => "Примечание", 'value' => $note],
            ['field' => "Период с", 'value' => $bdate],
            ['field' => "Период по", 'value' => $edate],
            ['field' => "Серия", 'value' => $series]
                ]);
        return $result;
    }


    /**
     * Блокировка обращения
     *
     * @return array
     */
    public static function block(array $codes, $note)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if(!\Yii::$app->user->can('codeFunction-block-web'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_block(:userID, :codes, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Блокировка обращения", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note]
                ]);
        return $result;
    }
    public static function blockByDate($note, $bdate, $edate, $series)
    {
        $bdate = Yii::$app->formatter->asDate($bdate);
        $edate = Yii::$app->formatter->asDate($edate);

        if(!\Yii::$app->user->can('codeFunction-block-web'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_block_by_date(:userID, :note, :bdate, :edate, :series)", [
            ':userID' => Yii::$app->user->getId(),
            ':note' => $note,
            ':bdate' => $bdate,
            ':edate' => $edate,
            ':series' => $series
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Блокировка обращения по дате", [
            ['field' => "Примечание", 'value' => $note],
            ['field' => "Период с", 'value' => $bdate],
            ['field' => "Период по", 'value' => $edate],
            ['field' => "Серия", 'value' => $series]
                ]);
        return $result;
    }

    /**
     * Разблокировка обращения
     *
     * @return array
     */
    public static function unblock(array $codes, $note)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if(!\Yii::$app->user->can('codeFunction-unblock-web'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_unblock(:userID, :codes, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Разблокировка обращения",  [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note]
        ]);
        return $result;
    }
    public static function unblockByDate($note, $bdate, $edate, $series)
    {
        $bdate = Yii::$app->formatter->asDate($bdate);
        $edate = Yii::$app->formatter->asDate($edate);
        if(!\Yii::$app->user->can('codeFunction-unblock-web'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_unblock_by_date(:userID, :note, :bdate, :edate, :series)", [
            ':userID' => Yii::$app->user->getId(),
            ':note' => $note,
            ':bdate' => $bdate,
            ':edate' => $edate,
            ':series' => $series
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Разблокировка обращения по дате", [
            ['field' => "Примечание", 'value' => $note],
            ['field' => "Период с", 'value' => $bdate],
            ['field' => "Периодпо", 'value' => $edate],
            ['field' => "Серия", 'value' => $series]
        ]);
        return $result;
    }

    /**
     * Упаковка
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function pack($groupCode, array $codes, $canRepack = false)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                //групповой - надо в pallet
                return static::paleta($groupCode, $codes, true);
            } else {
                if ($c->product->nomenclature->hasl3) {
                    //нуно ругаться - некорректный тип кода
                    throw new NotAcceptableHttpException("Некорректный тип кода");
                }
            }
        }
        
        //постаринке
        if (!\Yii::$app->user->can('codeFunction-pack'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_pack(:userID, :grpCode, :codes, :repack, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':repack' => $canRepack,
            ':l3' => false,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Упаковка", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Флаг переупаковки", 'value' => $canRepack]
        ]);
        return $result;
    }
    public static function packFull($groupCode, array $codes, $canRepack = false)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if(empty($c))
                throw new NotAcceptableHttpException($codes[0].": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                //групповой - надо в pallet
                return static::paletaUni($groupCode, $codes, true);
            } else {
                if ($c->product->nomenclature->hasl3) {
                    //нуно ругаться - некорректный тип кода
                    throw new NotAcceptableHttpException("Некорректный тип кода");
                }
            }
        }
        //постаринке
        if (!\Yii::$app->user->can('codeFunction-pack-full'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_pack_full(:userID, :grpCode, :codes, :repack, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':repack' => $canRepack,
            ':l3' => false,
        ]);
        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Упаковка полный гофрокороб", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Флаг переупаковки", 'value' => $canRepack]
        ]);
        
        return $result;
    }
    
    /**
     * Упаковка
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function l3($groupCode, array $codes, $canRepack = false) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                throw new NotAcceptableHttpException("Некорректный тип кода");
            } else {
                if ($c->product->nomenclature->hasl3) {
                    //нуно ругаться - некорректный тип кода
                    if (!\Yii::$app->user->can('codeFunction-l3'))
                        throw new NotAcceptableHttpException('Запрет на выполнение операции');
                    $query = \Yii::$app->db->createCommand("select make_pack(:userID, :grpCode, :codes, :repack, :l3)", [
                        ':userID' => Yii::$app->user->getId(),
                        ':grpCode' => $groupCode,
                        ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                        ':repack' => $canRepack,
                        ':l3' => true,
                    ]);

                    $result = pghelper::pgarr2arr($query->queryScalar());
                    AuditLog::Audit(AuditOperation::OP_CODES, "Упаковка бандероли", [
                        ['field' => "Групповой код", 'value' => $groupCode],
                        ['field' => 'Коды', 'value' => $codes],
                        ['field' => "Флаг переупаковки", 'value' => $canRepack]
                    ]);
                }
                else
                    throw new NotAcceptableHttpException("Данная номенклатура не может упаковываться в бандероли");
            }
        } else {
            //постаринке
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        return $result;
    }

    
    public static function l3Uni($groupCode, array $codes, $canRepack = false) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                throw new NotAcceptableHttpException("Некорректный тип кода");
            } else {
                if ($c->product->nomenclature->hasl3) {
                    if (!\Yii::$app->user->can('codeFunction-l3-uniform'))
                        throw new NotAcceptableHttpException('Запрет на выполнение операции');
                    $query = \Yii::$app->db->createCommand("select make_pack_full(:userID, :grpCode, :codes, :repack, :l3)", [
                        ':userID' => Yii::$app->user->getId(),
                        ':grpCode' => $groupCode,
                        ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                        ':repack' => $canRepack,
                        ':l3' => true,
                    ]);
                    $result = pghelper::pgarr2arr($query->queryScalar());
                    AuditLog::Audit(AuditOperation::OP_CODES, "Упаковка полная бандероль", [
                        ['field' => "Групповой код", 'value' => $groupCode],
                        ['field' => 'Коды', 'value' => $codes],
                        ['field' => "Флаг переупаковки", 'value' => $canRepack]
                    ]);
                }
                else
                    throw new NotAcceptableHttpException("Данная номенклатура не может упаковываться в бандероли");
            }
        } else {
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        return $result;
    }

    /**
     * Упаковка палеты
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function paleta($groupCode, array $codes, bool $l3 = false)
    {
        if(is_null($l3))$l3 = false;
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!$l3 && !\Yii::$app->user->can('codeFunction-paleta'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if($l3 && !\Yii::$app->user->can('codeFunction-pack'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_paleta(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => $l3,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Паллетирование неоднородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
        return $result;
    }
    public static function paletaUni($groupCode, array $codes,bool $l3 = false)
    {
        if (is_null($l3))
            $l3 = false;
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!$l3 && !\Yii::$app->user->can('codeFunction-paleta-uniform'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if($l3 && !\Yii::$app->user->can('codeFunction-pack-full'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_paleta_uni(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => $l3,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Паллетирование однородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
        return $result;
    }

    /**
     * Добавление в палету
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function paletaAdd($groupCode, array $codes, bool $l3 = false)
    {
        if (is_null($l3))
            $l3 = false;
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!$l3 && !\Yii::$app->user->can('codeFunction-paleta-add'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if($l3 && !\Yii::$app->user->can('codeFunction-gofra-add'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_paleta_add(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => $l3,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в паллету неоднородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
        return $result;
    }
    public static function paletaAddUni($groupCode, array $codes, bool $l3 = false)
    {
        if (is_null($l3))
            $l3 = false;
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!$l3 && !\Yii::$app->user->can('codeFunction-paleta-add-uniform'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if($l3 && !\Yii::$app->user->can('codeFunction-gofra-add-uniform'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_paleta_add_uni(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => $l3,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в паллету однородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
        return $result;
    }

    /**
     * Добавление в гофру
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function gofraAdd($groupCode, array $codes)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(Constant::get('hasL3') == 'true')
        {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP)
            {
                //групповой - надо в pallet
                return static::paletaAdd($groupCode, $codes,true);
            }
            else
            {
                if($c->product->nomenclature->hasl3)
                {
                    //нуно ругаться - некорректный тип кода
                    throw new NotAcceptableHttpException("Некорректный тип кода");
                }
            }
        }
        //постаринке
        if(!\Yii::$app->user->can('codeFunction-gofra-add'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_gofra_add(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => false,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в гофрокороб неоднородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
        return $result;
    }
    /**
     * Добавление в гофру
     *
     * @param $groupCode
     * @param array $codes
     * @return array
     */
    public static function l3Add($groupCode, array $codes) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                throw new NotAcceptableHttpException("Некорректный тип кода");
            } else {
                if ($c->product->nomenclature->hasl3) {
                    if (!\Yii::$app->user->can('codeFunction-l3-add'))
                        throw new NotAcceptableHttpException('Запрет на выполнение операции');
                    $query = \Yii::$app->db->createCommand("select make_gofra_add(:userID, :grpCode, :codes, :l3)", [
                        ':userID' => Yii::$app->user->getId(),
                        ':grpCode' => $groupCode,
                        ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                        ':l3' => true,
                    ]);

                    $result = pghelper::pgarr2arr($query->queryScalar());
                    AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в бандероль неоднородное", [
                        ['field' => "Групповой код", 'value' => $groupCode],
                        ['field' => 'Коды', 'value' => $codes]
                    ]);
                }
                else
                    throw new NotAcceptableHttpException("Данная номенклатура не может упаковываться в бандероли");
            }
        } else {
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        return $result;
    }

    public static function gofraAddUni($groupCode, array $codes)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                //групповой - надо в pallet
                return static::paletaAddUni($groupCode, $codes, true);
            } else {
                if ($c->product->nomenclature->hasl3) {
                    //нуно ругаться - некорректный тип кода
                    throw new NotAcceptableHttpException("Некорректный тип кода");
                }
            }
        }
        //постаринке
        if (!\Yii::$app->user->can('codeFunction-gofra-add-uniform'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_gofra_add_uni(:userID, :grpCode, :codes, :l3)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $groupCode,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':l3' => false,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в гофрокороб однородное", [
            ['field' => "Групповой код", 'value' => $groupCode],
            ['field' => 'Коды', 'value' => $codes]
        ]);
                
        return $result;
    }
    public static function l3AddUni($groupCode, array $codes) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (Constant::get('hasL3') == 'true') {
            $c = self::findOneByCode($codes[0]);
            if (empty($c))
                throw new NotAcceptableHttpException($codes[0] . ": код не найден");
            if ($c->generation->codeType->id == CodeType::CODE_TYPE_GROUP) {
                throw new NotAcceptableHttpException("Некорректный тип кода");
            } else {
                if ($c->product->nomenclature->hasl3) {
                    if (!\Yii::$app->user->can('codeFunction-l3-add-uniform'))
                        throw new NotAcceptableHttpException('Запрет на выполнение операции');
                    $query = \Yii::$app->db->createCommand("select make_gofra_add_uni(:userID, :grpCode, :codes, :l3)", [
                        ':userID' => Yii::$app->user->getId(),
                        ':grpCode' => $groupCode,
                        ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                        ':l3' => true,
                    ]);

                    $result = pghelper::pgarr2arr($query->queryScalar());
                    AuditLog::Audit(AuditOperation::OP_CODES, "Добавление в гофрокороб однородное", [
                        ['field' => "Групповой код", 'value' => $groupCode],
                        ['field' => 'Коды', 'value' => $codes]
                    ]);
                }
                else
                    throw new NotAcceptableHttpException("Данная номенклатура не может упаковываться в бандероли");
            }
        } else {
            //постаринке
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        }
        return $result;
    }

    /**
     * Приход кодов на склад
     *
     *
     * @return array
     */
    public static function incom($invoice, $invoiceDate, array $codes, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!\Yii::$app->user->can('codeFunction-income-prod'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if (!self::checkQR($codes, $qrcode, 'codeFunction-income-prod', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_income_prod(:userID, :codes, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Приемка производство", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "QR", 'value' => $qrcode]
        ]);
        return $result;
    }
    public static function incomExt($invoice, $invoiceDate, array $codes, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!\Yii::$app->user->can('codeFunction-incomeExt'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if (!self::checkQR($codes, $qrcode, 'codeFunction-incomeExt', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];
        
        Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','true',true)")->execute(); //отключаем проверку прав в БД

        $query = \Yii::$app->db->createCommand("select make_income_log(:userID, :codes, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Приемка сторонних кодов (прямое акцептование)", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "QR", 'value' => $qrcode]
        ]);
        return $result;
    }
    public static function incomLog($invoice, $invoiceDate, array $codes, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(\Yii::$app->user->identity->check_rights &&  !\Yii::$app->user->can('codeFunction-income-log'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if (!self::checkQR($codes, $qrcode, 'codeFunction-income-log', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_income_log(:userID, :codes, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Приемка логистика", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "QR", 'value' => $qrcode]
        ]);
        return $result;
    }
    
    public static function transfer(array $codes, $objectId) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (!\Yii::$app->user->can('codeFunction-transfer'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_transfer(:userID, :codes, :objectId)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':objectId' => $objectId
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Внутреннее перемещение", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Идентификатор объекта", 'value' => $objectId]
        ]);
        return $result;
    }

    public static function incomeReverseCodes(array $codes, $objectId, $invoiceId) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (!\Yii::$app->user->can('codeFunction-incomeReverse'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        $query = \Yii::$app->db->createCommand("select make_income_reverse(:userID, :codes, :objectId, :invoice)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':objectId' => $objectId,
            ":invoice" => $invoiceId,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Приемка кодов по обратному акцептованию", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Идентификатор объекта", 'value' => $objectId]
        ]);
        return $result;
    }

    /**
     *
     * @sql make_outcome(_userid bigint, _invoice character varying, _invoice_date date, _codes character varying[])
     *
     *
     * @return bool
     */
    public static function outcom($invoice, $invoiceDate, array $codes, $objectId, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!\Yii::$app->user->can('codeFunction-outcome-prod'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        
        if (!self::checkQR($codes, $qrcode, 'codeFunction-outcome-prod', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_outcome_prod(:userID, :codes, :objectId, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':objectId' => $objectId
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Перемещение производство", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Идентификатор объекта получателя", 'value' => $objectId]
        ]);
        return $result;
    }
    public static function outcomLog($invoice, $invoiceDate, array $codes, $objectId, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(\Yii::$app->user->identity->check_rights && !\Yii::$app->user->can('codeFunction-outcome-log'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        
        if (!self::checkQR($codes, $qrcode, 'codeFunction-outcome-log', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_outcome_log(:userID, :codes, :objectId, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':objectId' => $objectId
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Перемещение логистика", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Идентификатор объекта получателя", 'value' => $objectId]
        ]);
        return $result;
    }
    /**
     * Отгрузка в розницу
     *
     * @return array
     */
    public static function outcomRetail($invoice, $invoiceDate, array $codes, $qrcode, $manufacturer_md_uid = null)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        
        
        //если отгружают контактное на наш объект - вызываем перемещение!!!!
        if(!empty($manufacturer_md_uid))
        {
            $obj = Facility::findOne(['fns_subject_id' => $manufacturer_md_uid]);
            if(!empty($obj) && $obj->external == false)
            {
                return self::outcom($invoice, $invoiceDate, $codes, $obj->id, $qrcode);
            }
        }

        if(!\Yii::$app->user->can('codeFunction-retail-prod'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        if (!self::checkQR($codes, $qrcode, 'codeFunction-retail-prod', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_retail_prod(:userID, :codes, :invoice, :invoiceDate, :manufacturer)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':manufacturer' => $manufacturer_md_uid,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Отгрузка производство", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "QR", 'value' => $qrcode]
        ]);
        return $result;
    }
    public static function outcomRetailLog($invoice, $invoiceDate, array $codes, $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if(!\Yii::$app->user->can('codeFunction-retail-log'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        if (!self::checkQR($codes, $qrcode, 'codeFunction-retail-log', ['invoice_number' => $invoice, 'invoice_date' => $invoiceDate]))
            return [1, "Ошибка проверки QR кода 1C"];

        $query = \Yii::$app->db->createCommand("select make_retail_log(:userID, :codes, :invoice, :invoiceDate)", [
            ':userID' => Yii::$app->user->getId(),
            ':invoice' => $invoice,
            ':invoiceDate' => $invoiceDate,
            ':codes' => new Expression(pghelper::arr2pgarr($codes))
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Отгрузка логистика", [
            ['field' => "Номер накладной", 'value' => $invoice],
            ['field' => "Дата накладной", 'value' => $invoiceDate],
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "QR", 'value' => $qrcode]
        ]);
        return $result;
    }
    /**
     * Разгруппировка
     *
     * @return array
     */
    public static function unGroup($grpCode, $note = '')
    {
        if(!\Yii::$app->user->can('codeFunction-ungrp'))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_ungrp(:userID, :grpCode, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':grpCode' => $grpCode,
            ':note' => $note
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Разгруппировка", [
            ['field' => "Групповой код", 'value' => $grpCode],
            ['field' => "Примечание", 'value' => $note]
        ]);
        return $result;
    }
    
    
    /**
     * Возврат в оборот
     *
     * @return array
     */
    public static function back(array $codes, $note) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);
        
        $type= "";

        if ($note == "Списание")
            $type = "spis";
        elseif ($note == "Реэкспорт")
            $type = "reexp";
        elseif ($note == "Отбор образцов")
            $type = "otbor";
        elseif ($note == "Отпуск по льготному рецепту")
            $type = "recept";
        elseif ($note == "Выдача для оказания мед. помощи")
            $type = "pomosh";
        elseif ($note == "Отгрузка незарегистрированному участнику")
            $type = "unreg";
        elseif ($note == "Выборочный контроль")
            $type = "control";

        if (!\Yii::$app->user->can('codeFunction-back-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_back(:userID, :codes, :type, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => $type,
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат в оборот", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note]
        ]);
        return $result;
    }

    /**
     * Отказ получателя - 252
     *
     * @return array
     */
    public static function refuse(array $codes, $note) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if (!\Yii::$app->user->can('codeFunction-refuse' ))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        $query = \Yii::$app->db->createCommand("select make_refuse(:userID, :codes, :type, :note)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => "",
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Отказ получателя", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note]
        ]);
        return $result;
    }

    /**
     * Возврат кодов
     *
     * апдейт:
     *   1. поиск операции п которой присланные коды отгружались
     *   2. проверка операции/статуса и тп
     *   3. 415 по возврату и отгрузке
     *   4. 441 - вывод из оборота
     * @return array
     */
    public static function returned(array $codes, $note = '', $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if ($note == "Переупаковка")
            $type = "repack";
        elseif ($note == "Отзыв серии")
            $type = "back";
        elseif ($note == "ПТВ, бой")
            $type = "boi";
        elseif ($note == "Ошибка клиент-менеджера")
            $type = "errmng";
        elseif ($note == "Ошибка склада")
            $type = "errstr";
        elseif ($note == "Отсутствие потребности у клиента")
            $type = "client";
        else
            $type = "other";

        if(!\Yii::$app->user->can('codeFunction-return-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if (!self::checkQR($codes, $qrcode, 'codeFunction-return-' . $type))
            return [1, "Ошибка проверки QR кода 1C"];
/*
        \Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','true',true)")->execute();

        $fns = Fns::findLastOperation($codes, [Fns::OPERATION_OUTCOMERETAIL_ID, Fns::OPERATION_OUTCOMERETAILUNREG_ID, Fns::OPERATION_OUTCOME_ID, Fns::OPERATION_OUTCOMESELF_ID]);
        if(empty($fns))
            throw new NotAcceptableHttpException("Коды были отгружены разными операциями");
        \Yii::$app->db->createCommand("SELECT set_config('itrack.invoice',:p,true)", [":p" => $fns->invoice_uid])->execute();

        $diff = array_diff($codes, pghelper::pgarr2arr($fns->codes));
        if($fns->state <= Fns::STATE_READY && empty($diff))
        {
            //док еще не отправлялся и  коды все соответствуют.. убиваем его и возвращаем молча коды
            $fns->dbschema = 'deleted';
            $fns->save(false, ['dbschema']);
            
            $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, true)', [
                'userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
            ]);
        }
        elseif($fns->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID)
        {
            //415
            //здесь мы должны все коды верхнего уровня в которых есть отсканенные
            //дальше изъять - что изъяли в 251, что осталось отгрузить по старой накладной
            /////TODOOOO
            
            //получаем парентов
            $cc = array();
            foreach ($codes as $code) {
                $c = Code::findOneByCode($code);
                if (!$c->defected && !$c->removed) {
                    if (!empty($c->parent_code)) {
                        $cp = Code::findOneByCode($c->parent_code);
                        if (!empty($cp->parent_code)) {
                            $cc[$cp->parent_code] = 1;
                        } else
                            $cc[$c->parent_code] = 1;
                    } else
                        $cc[$code] = 1;
                }
            }
            //возврат с 251 для всех кодов родителей
            $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, false)', [
                'userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr(array_keys($cc))),
                ':note' => $note,
                ':type' => $type,
            ]);
            $result = pghelper::pgarr2arr($query->queryScalar());
            if($result[0] != 0)
                return $result;
            if(!count(array_diff($codes, array_keys($cc))))
                    return $result;
            
            //изъятие
            $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => "Системное изъятие",
                ':type' => "other",
            ]);
            $result = pghelper::pgarr2arr($query->queryScalar());
            if ($result[0] != 0)
                return $result;

            //исключение утилизированных
            //получаем парентов
            $cc_new = array();
            foreach (array_keys($cc) as $code) {
                $c = Code::findOneByCode($code);
                if (!$c->defected && !$c->removed) {
                    if (!empty($c->parent_code)) {
                        $cp = Code::findOneByCode($c->parent_code);
                        if (!empty($cp->parent_code)) {
                            $cc_new[$cp->parent_code] = 1;
                        } else
                            $cc_new[$c->parent_code] = 1;
                    } else
                        $cc_new[$code] = 1;
                }
            }
            if(empty($cc_new))
                return $result;
            //отгрузка
            $query = \Yii::$app->db->createCommand("select make_retail_log(:userID, :codes, :invoice, :invoiceDate)", [
                ':userID' => Yii::$app->user->getId(),
                ':invoice' => $fns->invoice->invoice_number,
                ':invoiceDate' => $fns->invoice->invoice_date,
                ':codes' => new Expression(pghelper::arr2pgarr(array_keys($cc_new)))
            ]);
        }
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAILUNREG_ID) {
            //441
            //изымаем все наши коды + 391 в которм разгрупп все групповых и 391 тока sgtins
            //пробежка(пока все - должны в методе пропустится) и выявление у кого есть парент - изъятие парентов + make_back для 391
            
            //391
            $query = \Yii::$app->db->createCommand("select make_back(:userID, :codes, :type, :note)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => "Отгрузка незарегистрированному участнику",
                ':type' => "unreg",
            ]);
        }
        else
        {
*/
            //молча возвращаем!!!
            $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, true)', [
                'userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
            ]);
//        }


        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат отгруженных кодов", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
            ['field' => "QR", 'value' => $qrcode]
        ]);

        // добавляем возвращаемые коды в накладную
        $invoiceSrc = Invoice::findByCodes($codes);
        if (isset($result[0]) && ($result[0] == 0) && $invoiceSrc) {
            $childes = [];

            foreach ($codes as $code) {
                $c = Code::findOneByCode($code);
                if ($c && $c->childrens)
                    array_merge($childes, pghelper::pgarr2arr($c->childrens));
            }

            $invoiceSrc->returned_codes = pghelper::arr2pgarr(array_merge($codes, $childes));
            $invoiceSrc->save();
        }

        return $result;
    }
    
    
    /**
     * Получение кодов верхнео уровня
     * 
     * @param array $codes
     */
    static function findParentsByCodes(array $codes)
    {
        $cc = array();
        foreach ($codes as $code) {
            $c = Code::findOneByCode($code);
            if (!$c->defected && !$c->removed) {
                if (!empty($c->parent_code)) {
                    $cp = Code::findOneByCode($c->parent_code);
                    if (!empty($cp->parent_code)) {
                        $cc[$cp->parent_code] = 1;
                    } else
                        $cc[$c->parent_code] = 1;
                } else
                    $cc[$code] = 1;
            }
        }
        return array_keys($cc);
    }
    
    /**
     * Продолжение расширенного возврата - ПЕРЕОТПРАВКА (полуатель кода, но со своим номером накладной)
     * 
     * @param type $codes
     * @param type $note
     * @param type $invoice
     * @param type $fns
     */
    private static function returnedExtResend($codes, $note, $invoice, $fns, $type)
    {
        $diff = array_diff(pghelper::pgarr2arr($fns->codes), $codes);
        if(!empty($diff))
            throw new \yii\web\BadRequestHttpException('Отсканируйте все коды из отгрузки');
        if(!in_array($fns->state,[Fns::STATE_RESPONCE_PART, Fns::STATE_RESPONCE_SUCCESS]))
                throw new \yii\web\BadRequestHttpException('Возврат не возможен, докуемент отггрузки в стадии регистрации в МДЛП');
        
        //обновленная накладная
        $inv = $fns->invoice;
        $ninv = new Invoice();
        $ninv->setAttributes($inv->getAttributes(), false);
        $ninv->invoice_number = $invoice;
        unset($ninv->id);
        $ninv->save(false);
        $ninv->refresh();
        
        $docs = [];


        if ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID || $fns->operation_uid == Fns::OPERATION_OUTCOMESELF_ID)
        {
            //251(отказ отправителя) + 415/381 + 701
            $fns251 = Fns::createDoc([
                'created_by' => \Yii::$app->user->getIdentity()->id,
                'state' => Fns::STATE_CREATED,
                'codes' => pghelper::arr2pgarr($codes),
                'object_uid' => $fns->invoice->newObject->fns_subject_id,
                'newobject_uid' => $fns->invoice->object->fns_subject_id,
                'operation_uid' => Fns::OPERATION_251,
                'invoice_uid' => $fns->invoice_uid,
                'fns_params' => serialize(["receiver_id" => ((!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? '') )]),
                'fnsid' => '251',
                'updated_at' => new Expression('timeofday()::timestamptz'),
                'note' => 'Ошибка в накладной',
            ]);
            $docs[] = 'Отказ отправителя - '.$fns251->id;
            
            $fns415 = new Fns();
            $fns415->setAttributes($fns->getAttributes(), false);
            unset($fns415->id);
            $fns415->state = Fns::STATE_CREATED;
            $fns415->invoice_uid = $ninv->id;
            $fns415->save(false, Fns::par_attributes());
            
            $docs[] = 'Отгрузка - '. $fns415->id;
            
            $fns701 = Fns::createDoc([
                'created_by' => \Yii::$app->user->getIdentity()->id,
                'state' => Fns::STATE_CREATED,
                'codes' => pghelper::arr2pgarr($codes),
                'object_uid' => $fns->invoice->newObject->fns_subject_id,
                'newobject_uid' => $fns->invoice->object->fns_subject_id,
                'operation_uid' => Fns::OPERATION_INCOME_ID,
                'invoice_uid' => $ninv->id,
                'fnsid' => '701',
                'updated_at' => new Expression('timeofday()::timestamptz'),
            ]);
            
            $docs[] = 'Приемка - '. $fns701->id;
        }
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOME_ID)
        {
            //250(отмена) + 431
            $fns->decline(false);
            $docs[] = 'Отмена - '.$fns->prev_uid;
            
            $fns431 = new Fns();
            $fns431->setAttributes($fns->getAttributes(), false);
            unset($fns431->id);
            $fns431->state = Fns::STATE_CREATED;
            $fns431->invoice_uid = $ninv->id;
            $fns431->save(false, Fns::par_attributes());
            $docs[] = 'Перемещение - ' . $fns431->id;
        }
        else
        {
            throw new \yii\web\BadRequestHttpException('Операция возврата невозможна');
        }
        $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note, false, false)", [
            ':userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => "Системное изъятие",
            ':type' => "other",
        ]);
        $result = pghelper::pgarr2arr($query->queryScalar());
        if ($result[0] != 0)
            return $result;
        
        //молча принимаем
        $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
            'userID' => Yii::$app->user->getId(),
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => $type,
            ':nofns' => true,
            ':comment' => 'Возврат на объект получателя. (' . implode(",", $docs).')',
        ]);
        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат отгруженных кодов", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
        ]);
        return $result;
    }

    /**
     * Продолжение расширенного возврата - возвращаем на сторонний объект
     * !!коды только верхнего уровня
     * 
     * @param type $codes
     * @param type $note
     * @param type $invoice
     * @param type $fns
     */
    private static function returnedExtBack2($codes, $note, $invoice, $fns, $type)
    {
        $diff = array_diff(pghelper::pgarr2arr($fns->codes), $codes);
        $docs = [];
        $invoiceSrc = $fns->invoice;

        if (!empty($diff))
            throw new \yii\web\BadRequestHttpException('Просканируйте все коды из отгрузки');

        if (in_array($fns->state, [Fns::STATE_READY, Fns::STATE_CREATED, Fns::STATE_CREATING, Fns::STATE_CHECKING, Fns::STATE_ERRORSTOPED, Fns::STATE_RESPONCE_ERROR, Fns::STATE_SEND_ERROR, Fns::STATE_STOPED])) 
        {
            //док еще не отправлялся и  коды все соответствуют.. убиваем его и возвращаем молча коды на объект отправителя
            $fns->dbschema = 'deleted';
            $fns->save(false, Fns::par_attributes());
        } 
        else
        {


            //субъективно делаем возврат к отправителю..
            if ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID || $fns->operation_uid == Fns::OPERATION_OUTCOME_ID) {
                //251
                $fns251 = Fns::createDoc([
                            'created_by' => $fns->created_by,
                            'state' => Fns::STATE_CREATED,
                            'codes' => pghelper::arr2pgarr($codes),
                            'object_uid' => $fns->invoice->object->fns_subject_id,
                            'newobject_uid' => $fns->invoice->newObject->fns_subject_id,
                            'operation_uid' => Fns::OPERATION_251,
                            'invoice_uid' => $fns->invoice_uid,
                            'fns_params' => serialize(["receiver_id" => ((!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? '') )]),
                            'fnsid' => '251',
                            'data' => pghelper::arr2pgarr([1]),
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'note' => 'Ошибка в накладной',
                ]);
                $docs[] = 'Отказ отправителя (251) - ' . $fns251->id;
            }
            elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMESELF_ID) {
                //431
                //создаем отмену операции 250 док
                $fns->decline(false);
                $docs[] = 'Отмена 431: (250) - '.$fns->prev_uid;
                
            } elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAILUNREG_ID) {
                //441
                $ungrp = [];
            //3. фиктивно раззгрпировываем все групповые - рекрсивно!!!
                foreach ($codes as $code) {
                    $c = Code::findOneByCode($code);
                    if (!empty($c) && $c->generation->codetype_uid == CodeType::CODE_TYPE_GROUP) {
                        //групповой код
                        $fns912 = Fns::createDoc([
                                    'created_by' => $invoiceSrc->created_by,
                                    'state' => Fns::STATE_CREATED,
                                    'code' => $code,
                                    'codes' => $c->childrens,
                                    'product_uid' => $c->product_uid,
                                    'code_flag' => ($c->paleta ? 513 : 1),
                                    'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                    'operation_uid' => Fns::OPERATION_UNGROUP_ID,
                                    'fnsid' => '912',
                                    'note' => 'системная разгруппировка',
                                    'data' => pghelper::arr2pgarr(['recursive']),
                                    'updated_at' => new Expression('timeofday()::timestamptz'),
                                    'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($c->childrens), "grp" => $code])),
                        ]);
                        $ungrp[] = $fns912->id;
                        sleep(1);
                    }
                }

                //4. создаем 391 на всех потомков
                $ch = Code::findChilds($codes);
                $fns391 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'codes' => pghelper::arr2pgarr($ch),
                            'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                            'operation_uid' => Fns::OPERATION_BACK_ID,
                            'fnsid' => '391',
                            'indcnt' => count($ch),
                            'data' => pghelper::arr2pgarr([6]),
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => $ch, "grp" => null])),
                ]);

                sleep(1);

                $agr = [];
                //5. АГРЕГАЦИЯ !!!!!!!!!!!!!!!!! фиктивная для МДЛП!!!
            foreach ($codes as $code) {
                    $c = Code::findOneByCode($code);
                    if (empty($c))
                        throw new \yii\web\BadRequestHttpException('Код не найден: ' . $code);

                    if ($c->generation->codetype_uid == CodeType::CODE_TYPE_GROUP) {
                        if ($c->paleta) {
                            $korobs = [];
                            foreach (\Yii::$app->db->createCommand($c->getChild())->queryAll() as $ch) {
                                $korobs[] = $ch["code"];
                                $fns915 = Fns::createDoc([
                                            'created_by' => $invoiceSrc->created_by,
                                            'state' => Fns::STATE_CREATED,
                                            'code' => $ch["code"],
                                            'codes' => $ch["childrens"],
                                            'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                            'operation_uid' => Fns::OPERATION_GROUP_ID,
                                            'fnsid' => '915',
                                            'code_flag' => 0,
                                            'data' => pghelper::arr2pgarr([1]),
                                            'updated_at' => new Expression('timeofday()::timestamptz'),
                                            'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($ch["childrens"]), "grp" => $ch["code"]])),
                                ]);
                                $agr[] = $fns915->id;
                                sleep(1);
                            }
                            $fns915 = Fns::createDoc([
                                        'created_by' => $invoiceSrc->created_by,
                                        'state' => Fns::STATE_CREATED,
                                        'code' => $code,
                                        'codes' => pghelper::arr2pgarr($korobs),
                                        'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                        'operation_uid' => Fns::OPERATION_GROUP_ID,
                                        'fnsid' => '915',
                                        'code_flag' => 513,
                                        'data' => pghelper::arr2pgarr([2]),
                                        'updated_at' => new Expression('timeofday()::timestamptz'),
                                        'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => $korobs, "grp" => $code])),
                            ]);
                            sleep(1);
                            $agr[] = $fns915->id;
                        } else {
                            //у нас короб
                            $fns915 = Fns::createDoc([
                                        'created_by' => $invoiceSrc->created_by,
                                        'state' => Fns::STATE_CREATED,
                                        'code' => $code,
                                        'codes' => $c->childrens,
                                        'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                        'operation_uid' => Fns::OPERATION_GROUP_ID,
                                        'fnsid' => '915',
                                        'code_flag' => 0,
                                        'data' => pghelper::arr2pgarr([1]),
                                        'updated_at' => new Expression('timeofday()::timestamptz'),
                                        'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($c->childrens), "grp" => $code])),
                            ]);
                            sleep(1);
                            $agr[] = $fns915->id;
                        }
                    }
                }
                $docs[] = "Разгруппировки: " . implode(",", $ungrp);
                $docs[] = "391 - " . $fns391->id;
                $docs[] = "Агрегирование: ". implode(",", $agr);
            } else {
                //unknown operation
                throw new \yii\web\BadRequestHttpException('Операция возврата невозможна');
            }
        }    

        $inv = $fns->invoice;
        $ninv = new Invoice();
        $ninv->setAttributes($inv->getAttributes(), false);
        unset($ninv->id);
        $ninv->invoice_number = $invoice;
        $ninv->save(false);
        $ninv->refresh();

        //отменили старые операции, теперь создаем новые
        $par_rec = Facility::findParent(\Yii::$app->user->identity->object_uid);
        $par_sen = Facility::findParent($invoiceSrc->object_uid);
        if ($par_sen == $par_rec) {
            //пользователь в одной ветке с отправителем
            //431
            $fns = Fns::createDoc([
                        'created_by' => $invoiceSrc->created_by,
                        'state' => Fns::STATE_CREATED,
                        'codes' => pghelper::arr2pgarr($codes),
                        'object_uid' => $invoiceSrc->object_uid,
                        'newobject_uid' => \Yii::$app->user->getIdentity()->object_uid,
                        'operation_uid' => Fns::OPERATION_OUTCOMESELF_ID,
                        'invoice_uid' => $ninv->id,
                        'fnsid' => '431',
                        'updated_at' => new Expression('timeofday()::timestamptz'),
            ]);
            
            $docs[] = "431 - ".$fns->id;
        } else {
            //ТОДО проверку на кконтрактый передан или нет
            //415 от отправителя + 701 от возвращающего
                    $indcnt = $grpcnt = 0;
            $res = \Yii::$app->db->createCommand("SELECT codetype_uid,count(*) as cnt FROM _get_codes_array(:codes) as codes 
                                                                        LEFT JOIN generations ON codes.generation_uid = generations.id
                                                                        GROUP by 1
                                                        ", [':codes' => pghelper::arr2pgarr($codes)])->queryAll();
            foreach ($res as $r)
                if ($r["codetype_uid"] == CodeType::CODE_TYPE_GROUP)
                    $grpcnt += $r["cnt"];
                else
                    $indcnt += $r["cnt"];
            //415 от отправителя + 701 от возвращающего
            $fns415 = Fns::createDoc([
                        'created_by' => $invoiceSrc->created_by,
                        'state' => Fns::STATE_CREATED,
                        'codes' => pghelper::arr2pgarr($codes),
                        'object_uid' => $invoiceSrc->object_uid,
                        'newobject_uid' => \Yii::$app->user->getIdentity()->object_uid,
                        'operation_uid' => Fns::OPERATION_OUTCOMERETAIL_ID,
                        'invoice_uid' => $invoiceSrc->id,
                        'fnsid' => '415',
                        'grpcnt' => $grpcnt,
                        'indcnt' => $indcnt,
                        'updated_at' => new Expression('timeofday()::timestamptz'),
            ]);
            //возможно сбросить флаг накладной updated_external

            $fns701 = Fns::createDoc([
                        'created_by' => \Yii::$app->user->getIdentity()->id,
                        'state' => Fns::STATE_CREATED,
                        'codes' => pghelper::arr2pgarr($codes),
                        'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                        'newobject_uid' => $invoiceSrc->object_uid,
                        'operation_uid' => Fns::OPERATION_INCOME_ID,
                        'invoice_uid' => $invoiceSrc->id,
                        'data' => pghelper::arr2pgarr([1]),
                        'fnsid' => '701',
                        'grpcnt' => $grpcnt,
                        'indcnt' => $indcnt,
                        'updated_at' => new Expression('timeofday()::timestamptz'),
            ]);

            $docs[] = "415 - " . ($fns415->id ?? 'ошибка');
            $docs[] = "701 - " . ($fns701->id ?? 'ошибка');
        }

        //возвращаем молча на объект выполнившего операцию
        $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
            'userID' => \Yii::$app->user->identity->id,
            ':codes' => new Expression(pghelper::arr2pgarr($codes)),
            ':note' => $note,
            ':type' => $type,
            ':nofns' => true,
            ':comment' => 'Возврат на сторонний объект. (' . implode(",", $docs) . ')',
        ]);

        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат отгруженных кодов", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
        ]);
        return $result;
    }
    
    
    /**
     * Поиск и возврат всех индивидуальных кодов внутри массива и их потомков
     * @param array $codes
     * @return array
     */
    static function findChilds($codes)
    {
        $childs = [];
        $res = \Yii::$app->db->createCommand("select code,codetype_uid,childrens from _get_codes_array(:codes) as codes
                                                        LEFT JOIN generations ON codes.generation_uid=generations.id
                                                        ", [":codes" => pghelper::arr2pgarr($codes)])->queryAll();
        foreach($res as $r)
        {
            if ($r["codetype_uid"] == CodeType::CODE_TYPE_INDIVIDUAL)
                $childs[] = $r["code"];
            else
            {
                $res2 = \Yii::$app->db->createCommand("select code,codetype_uid,childrens from _get_codes_array(:codes) as codes
                                                        LEFT JOIN generations ON codes.generation_uid=generations.id
                                                        ", [":codes" => $r["childrens"]])->queryAll();
                foreach($res2 as $r)
                    if ($r["codetype_uid"] == CodeType::CODE_TYPE_INDIVIDUAL)
                        $childs[] = $r["code"];
            }
        }

        return $childs;
    }

    /**
     * Поиск иерархии сверху и все потомки снизу
     * возврат [потомок => родитель]
     * 
     * @param type $codes
     * @return array
     */
    static function getUpHierarchyByCodes($codes)
    {
        $ret = [];
        $down = [];
        $fd = true;
        $par = $codes;
        do
        {
            $res = \Yii::$app->db->createCommand("select parent_code,code,codetype_uid,childrens from _get_codes_array(:codes) as codes
                                                            LEFT JOIN generations ON codes.generation_uid=generations.id
                                                            ", [":codes" => pghelper::arr2pgarr($par)])->queryAll();
            $par = [];
            foreach($res as $r)
            {
                $ret[$r["code"]] = $r["parent_code"];
                if(!empty($r["parent_code"]))
                    $par[] = $r["parent_code"];
                
                if($fd)
                {
                    if($r["codetype_uid"] == CodeType::CODE_TYPE_INDIVIDUAL)
                        $down[] = $r["code"];
                    else
                        $down = array_merge($down, pghelper::pgarr2arr($r["childrens"]));
                }
            }
            $fd = false;
        }while(count($par));
        
        return ['up' => $ret, 'down' => $down];
    }
    
    /**
     * Продолжение расширенного возврата - возвращаем на объект отправителя
     * 
     * @param type $codes
     * @param type $note
     * @param type $invoice
     * @param type $fns
     */
    private static function returnedExtBack($codes, $note, $invoice, $fns, $type)
    {
        $diff = array_diff(pghelper::pgarr2arr($fns->codes), $codes);
        $invoiceSrc = $fns->invoice;
        
        if (in_array($fns->state, [Fns::STATE_READY, Fns::STATE_CREATED, Fns::STATE_CREATING, Fns::STATE_CHECKING, Fns::STATE_ERRORSTOPED, Fns::STATE_RESPONCE_ERROR, Fns::STATE_SEND_ERROR, Fns::STATE_STOPED]) 
                        && empty($diff)) {
            //док еще не отправлялся и  коды все соответствуют.. убиваем его и возвращаем молча коды
            $fns->dbschema = 'deleted';
            $fns->save(false, Fns::par_attributes());

            $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                'userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
                ':nofns' => true,
                ':comment' => 'Возврат на объект отправителя (документ об отгрузке не был отправлен в МДЛП и удален)',
            ]);
        } 
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID) 
        {
            //415
            //получаем парентов
            $hierarchy = Code::getUpHierarchyByCodes($codes);
            $cc = [];
            foreach($hierarchy["up"] as $code=>$parent)
                if(empty($parent))
                    $cc[] = $code;
            
            if (!count(array_diff($codes, $cc)))
            {
                //возвращаюь всех родителей - значит достаточно только возврат
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Возврат на объект отправителя - 415 (возврат родителей)',
                ]);
                
                try
                {
                    $fns_params = unserialize($fns->fns_params);
                    $receiver_id = $fns_params["receiver_id"] ?? null;
                } catch (\Exception $ex) {
                }
                if (empty($receiver_id))
                    $receiver_id = ( (!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? ''));
                
                //создаем док для МДЛП
                $fns251 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'codes' => pghelper::arr2pgarr($codes),
                            'object_uid' => $invoiceSrc->object_uid,
                            'operation_uid' => Fns::OPERATION_251,
                            'fns_params' => serialize(["receiver_id" => $receiver_id]),
                            'fnsid' => '251',
                            'note' => $note,
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'fns_start_send' => new Expression('timeofday()::timestamptz'),
                            'full_codes' => pghelper::arr2pgarr($codes),
                ]);
                sleep(1);
            }
            else
            {
                //возвращаюь потомков.. надо сделать возврат родителей. изъять из них и что не утилизировано - отправить аналогичным 415
                try {
                    $fns_params = unserialize($fns->fns_params);
                    $receiver_id = $fns_params["receiver_id"] ?? null;
                } catch (\Exception $ex) {
                    
                }
                if (empty($receiver_id))
                    $receiver_id = ( (!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? ''));

                //создаем возврат родителей
                $fns251 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'codes' => pghelper::arr2pgarr($cc),
                            'object_uid' => $invoiceSrc->object_uid,
                            'operation_uid' => Fns::OPERATION_251,
                            'fns_params' => serialize(["receiver_id" => $receiver_id]),
                            'fnsid' => '251',
                            'note' => $note,
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'fns_start_send' => new Expression('timeofday()::timestamptz'),
                            'full_codes' => pghelper::arr2pgarr($codes),
                ]);
                
                sleep(1);
                
                //изъятие для не родителей
                $ungrp = [];
                $u = [];
                foreach($hierarchy["up"] as $code=>$parent)
                    if(in_array($code,$codes) && !empty($parent))
                            if(is_array($u[$parent]))
                                $u[$parent][] = $code;
                            else
                                $u[$parent] = [$code];

                foreach($u as $uparent=>$ucodes)
                {
                    if(!empty($hierarchy["up"][$uparent]))
                    {
                        //у нашего родителя есть свой родитель надо его предварительно изъять тоже -- не учтены бандероли!!!!
                        $fns913_ = Fns::createDoc([
                                    'created_by' => $invoiceSrc->created_by,
                                    'state' => Fns::STATE_CREATED,
                                    'code' => $hierarchy["up"][$uparent],
                                    'codes' => pghelper::arr2pgarr([$uparent]),
                                    'object_uid' => $invoiceSrc->object_uid,
                                    'operation_uid' => Fns::OPERATION_GROUPSUB_ID,
                                    'fnsid' => '913',
                                    'code_flag' => 513,
                                    'updated_at' => new Expression('timeofday()::timestamptz'),
                                    'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => [$uparent], "grp" => $hierarchy["up"][$uparent]])),
                                    'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                    'full_codes' => pghelper::arr2pgarr($codes),
                        ]);
                        sleep(1);
                    }
                    $fns913 = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'code' => $uparent,
                                'codes' => pghelper::arr2pgarr($ucodes),
                                'object_uid' => $invoiceSrc->object_uid,
                                'operation_uid' => Fns::OPERATION_GROUPSUB_ID,
                                'fnsid' => '913',
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => $ucodes, "grp" => $uparent])),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    $ungrp[] = $fns913->id;
                    sleep(1);
                    
                    if (!empty($hierarchy["up"][$uparent])) {
                        //у нашего родителя есть свой родитель надо его предварительно изъять тоже -- не учтены бандероли!!!!
                        $fns914_ = Fns::createDoc([
                                    'created_by' => $invoiceSrc->created_by,
                                    'state' => Fns::STATE_CREATED,
                                    'code' => $hierarchy["up"][$uparent],
                                    'codes' => pghelper::arr2pgarr([$uparent]),
                                    'object_uid' => $invoiceSrc->object_uid,
                                    'operation_uid' => Fns::OPERATION_GROUPADD_ID,
                                    'fnsid' => '914',
                                    'code_flag' => 513,
                                    'updated_at' => new Expression('timeofday()::timestamptz'),
                                    'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => [$uparent], "grp" => $hierarchy["up"][$uparent]])),
                                    'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                    'full_codes' => pghelper::arr2pgarr($codes),
                        ]);
                        sleep(1);
                    }
                }
                
                //отгрузка родителей - но c исключtybtv родителей пустышек!!!
                $grpcnt = $indcnt = 0;
                foreach($cc as $key=>$value)
                {
                    $res = \Yii::$app->db->createCommand("SELECT count(*) from _get_codes_array((select childrens from _get_codes_array(:codes))::varchar[]) as codes           
                                                LEFT JOIN generations On codes.generation_uid = generations.id
                                                WHERE codetype_uid = :codetype
                                                and NOT (code=ANY(:cc))
                        ", [
                                ":codes" => pghelper::arr2pgarr([$value]),
                                ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL,
                                ":cc" => pghelper::arr2pgarr($hierarchy["down"]),
                            ])->queryScalar();
                    if(empty($res))
                        unset($cc[$key]);
                    $res = \Yii::$app->db->createCommand("SELECT codetype_uid FROM _get_codes_array(:codes) as codes
                                                            LEFT join generations on generations.id = codes.generation_uid
                                                            ", [":codes" => pghelper::arr2pgarr([$value])])->queryOne();
                    if($res["codetype_uid"] == CodeType::CODE_TYPE_GROUP)
                        $grpcnt++;
                    else
                        $indcnt++;
                }
                
                if(count($cc))
                {
                    $fns415 = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'codes' => pghelper::arr2pgarr($cc),
                                'object_uid' => $invoiceSrc->object_uid,
                                'invoice_uid' => $invoiceSrc->id,   ///осталяем пока старую накладную... со старыми кодами!!!!
                                'operation_uid' => Fns::OPERATION_OUTCOMERETAIL_ID,
                                'fnsid' => '415',
                                'grpcnt' => $grpcnt,
                                'indcnt' => $indcnt,
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    sleep(1);
                }


                //возврат с 251 для всех возвращаемых кодов
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Возврат на объект отправителя - 415 (возврат родителей - '.$fns251->id.', изъятие из родителей - '.implode(",", $ungrp).', отгрузка родителей - '.$fns415->id.')',
                ]);
            }

        }
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOME_ID) 
        {
            //381
            $diff = array_diff($codes, pghelper::pgarr2arr($fns->codes));
            if(empty($diff))
            {
                //коды верхнего уровня
                //возврат с 251 
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Возврат на объект отправителя - 381 (возврат родителей)',
                ]);
                try {
                    $fns_params = unserialize($fns->fns_params);
                    $receiver_id = $fns_params["receiver_id"] ?? null;
                } catch (\Exception $ex) {
                    
                }
                if (empty($receiver_id))
                    $receiver_id = ( (!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? ''));
                $fns251 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'codes' => pghelper::arr2pgarr($codes),
                            'object_uid' => $invoiceSrc->object_uid,
                            'operation_uid' => Fns::OPERATION_251,
                            'fns_params' => serialize(["receiver_id" => $receiver_id]),
                            'fnsid' => '251',
                            'note' => $note,
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'fns_start_send' => new Expression('timeofday()::timestamptz'),
                            'full_codes' => pghelper::arr2pgarr($codes),
                ]);
                sleep(1);
            }
            else
                throw new \yii\web\BadRequestHttpException('Операция возврата невозможна (коды не верхнего уровня)');
        }
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMESELF_ID)
        {
            //431
            //создаем отмену операции 250 док
            $diff = array_diff($codes, pghelper::pgarr2arr($fns->codes));
            if (empty($diff)) {
                //коды верхнего уровня
                
                //отмена 431 (через 250)
                $fns->decline(false);
                sleep(1);
                //возврат с 251 
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Возврат на объект отправителя - 431 (возврат родителей - операция отмены - '.$fns->prev_uid.')',
                ]);
//                $fns251 = Fns::createDoc([
//                            'created_by' => $invoiceSrc->created_by,
//                            'state' => Fns::STATE_CREATED,
//                            'codes' => pghelper::arr2pgarr($codes),
//                            'object_uid' => $invoiceSrc->object_uid,
//                            'operation_uid' => Fns::OPERATION_251,
//                            'fns_params' => serialize(["receiver_id" => ((!empty($fns) && !empty($fns->invoice) && !empty($fns->invoice->dest_fns)) ? $fns->invoice->dest_fns : ($fns->newobject->fns_subject_id ?? '') )]),
//                            'fnsid' => '251',
//                            'note' => $note,
//                            'updated_at' => new Expression('timeofday()::timestamptz'),
//                ]);
//                sleep(1);
            } else
                throw new \yii\web\BadRequestHttpException('Операция возврата невозможна (коды не верхнего уровня)');
        }
        elseif ($fns->operation_uid == Fns::OPERATION_OUTCOMERETAILUNREG_ID)
        {
            //441
            //1.изымаем если есть из чего + с отправкий в фнс                                                       VV _nofns   
            $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note, true, false)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => "Системное изъятие",
                ':type' => "other",
            ]);
            $result = pghelper::pgarr2arr($query->queryScalar());
            if ($result[0] != 0)
                return $result;
            
            $ungrp = [];
            sleep(1);

            //3. фиктивно раззгрпировываем все групповые - рекрсивно!!!
            foreach($codes as $code)
            {
                $c = Code::findOneByCode($code);
                if(!empty($c) && $c->generation->codetype_uid == CodeType::CODE_TYPE_GROUP)
                {
                    //групповой код
                    $fns912 = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'code' => $code,
                                'codes' => $c->childrens,
                                'product_uid' => $c->product_uid,
                                'code_flag' => ($c->paleta?513:1),
                                'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                'operation_uid' => Fns::OPERATION_UNGROUP_ID,
                                'fnsid' => '912',
                                'note' => 'системная разгруппировка',
                                'data' => pghelper::arr2pgarr(['recursive']),
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($c->childrens), "grp" => $code])),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    $ungrp[] = $fns912->id;
                    sleep(1);
                }
            }
            
            //4. создаем 391 на всех потомков
            $ch = Code::findChilds($codes);
            $fns391 = Fns::createDoc([
                'created_by' => $invoiceSrc->created_by,
                'state' => Fns::STATE_CREATED,
                'codes' => pghelper::arr2pgarr($ch),
                'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                'operation_uid' => Fns::OPERATION_BACK_ID,
                'fnsid' => '391',
                'indcnt' => count($ch),
                'data' => pghelper::arr2pgarr([6]),
                'updated_at' => new Expression('timeofday()::timestamptz'),
                'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => $ch, "grp" => null])),
                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                'full_codes' => pghelper::arr2pgarr($codes),
            ]);
            
            sleep(1);
                    
            $agr = [];
            //5. АГРЕГАЦИЯ !!!!!!!!!!!!!!!!! фиктивная для МДЛП!!!
            foreach($codes as $code)
            {
                $c = Code::findOneByCode($code);
                if(empty($c))
                    throw new \yii\web\BadRequestHttpException('Код не найден: ' . $code);
                
                if ($c->generation->codetype_uid == CodeType::CODE_TYPE_GROUP)
                {
                    if($c->paleta)
                    {
                        $korobs = [];
                        foreach(\Yii::$app->db->createCommand($c->getChild())->queryAll() as $ch)
                        {
                            $korobs[] = $ch["code"];
                            $fns915 = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'code' => $ch["code"],
                                'codes' => $ch["childrens"],
                                'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                'operation_uid' => Fns::OPERATION_GROUP_ID,
                                'fnsid' => '915',
                                'code_flag' => 0,
                                'data' => pghelper::arr2pgarr([1]),
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($ch["childrens"]), "grp" => $ch["code"]])),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                            ]);
                            $agr[] = $fns915->id;
                            sleep(1);
                        }
                        $fns915 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'code' => $code,
                            'codes' => pghelper::arr2pgarr($korobs),
                            'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                            'operation_uid' => Fns::OPERATION_GROUP_ID,
                            'fnsid' => '915',
                            'code_flag' => 513,
                            'data' => pghelper::arr2pgarr([2]),
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => $korobs, "grp" => $code])),
                            'fns_start_send' => new Expression('timeofday()::timestamptz'),
                            'full_codes' => pghelper::arr2pgarr($codes),
                        ]);
                        sleep(1);
                        $agr[] = $fns915->id;
                    }
                    else
                    {
                        //у нас короб
                        $fns915 = Fns::createDoc([
                            'created_by' => $invoiceSrc->created_by,
                            'state' => Fns::STATE_CREATED,
                            'code' => $code,
                            'codes' => $c->childrens,
                            'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                            'operation_uid' => Fns::OPERATION_GROUP_ID,
                            'fnsid' => '915',
                            'code_flag' => 0,
                            'data' => pghelper::arr2pgarr([1]),
                            'updated_at' => new Expression('timeofday()::timestamptz'),
                            'codes_data' => pghelper::arr2pgarr(json_encode(["codes" => pghelper::pgarr2arr($c->childrens), "grp" => $code])),
                            'fns_start_send' => new Expression('timeofday()::timestamptz'),
                            'full_codes' => pghelper::arr2pgarr($codes),
                        ]);
                        sleep(1);
                        $agr[] = $fns915->id;
                    }
                }
            }
            
            // молча принимаем
            $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                'userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':nofns' => true,
                ':type' => $type,
                ':comment' => 'Возврат на объект отправителя - 441 (разгруппировка - ' . implode(",",$ungrp) . ', 391 - '.$fns391->id.', агрегирование - ' . implode(',', $agr) . ')',
            ]);
        }
        else
        {
            //unknown operation
            throw new \yii\web\BadRequestHttpException('Операция возврата невозможна');
        }
            
        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат отгруженных кодов", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
        ]);
        return $result;
    }
    
    /**
     * Возврат кодов Расширенная
     *
     * @return array
     */
    public static function returnedExt(array $codes, $note = '', $invoice, $invoiceDate, $qrcode) {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if ($note == "Переупаковка")
            $type = "repack";
        elseif ($note == "Отзыв серии")
            $type = "back";
        elseif ($note == "ПТВ, бой")
            $type = "boi";
        elseif ($note == "Ошибка клиент-менеджера")
            $type = "errmng";
        elseif ($note == "Ошибка склада")
            $type = "errstr";
        elseif ($note == "Отсутствие потребности у клиента")
            $type = "client";
        else
            $type = "other";

        if (!\Yii::$app->user->can('codeFunction-return-' . $type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');
        if (!self::checkQR($codes, $qrcode, 'codeFunction-return-' . $type))
            return [1, "Ошибка проверки QR кода 1C"];
        
        
        \Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','true',true)")->execute();
        
        try
        {
            $invoiceSrc = Invoice::findByCodes($codes);
        } catch (\Exception $ex) {
            throw new NotAcceptableHttpException($ex->getMessage());
        }
        if (empty($invoiceSrc))
            throw new NotAcceptableHttpException('Накладная по отгрузке не найдена');

        
        $res = Fns::findByTypeInvoice([Fns::OPERATION_601, Fns::OPERATION_607], $invoice , $codes);
        if(!empty($res))
        {
            if($res["operation_uid"] == Fns::OPERATION_601)
                throw new NotAcceptableHttpException("Воспользуйтесь операцией приемка");
            if ($res["operation_uid"] == Fns::OPERATION_607)
                throw new NotAcceptableHttpException("Свяжитесь с клиентом, отменить операцию невозможно");
        }
        
        //поиск документа МДЛП
        $fns = Fns::findLastOperation($codes, [Fns::OPERATION_OUTCOMERETAIL_ID, Fns::OPERATION_OUTCOMERETAILUNREG_ID, Fns::OPERATION_OUTCOME_ID, Fns::OPERATION_OUTCOMESELF_ID, Fns::OPERATION_WDEXT_ID]);

        if (empty($fns))
        {
//            throw new NotAcceptableHttpException("Коды были отгружены разными операциями");
            //документ не найден - значит было внутреннее перемещение...
            if(\Yii::$app->user->identity->object->fns_subject_id == $invoiceSrc->newObject->fns_subject_id)
            {
                //возврат делает получатель
                //молча возвращаем c изъятием из родителей!!!
                                                                                                                //не проверять родителей    //не отправлять ФНС
                $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note, true, false)", [
                    ':userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => "Системное изъятие",
                    ':type' => "other",
                ]);
                $result = pghelper::pgarr2arr($query->queryScalar());
                if ($result[0] != 0)
                    return $result;
                
                                                                                                            //не отпавлять фнс  
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Пользователь - получатель кода - ДА. изъятие + возврат'
                ]);
            } 
            else
            {
                //возврат делает не получатель!!!
                
                //изъятие если есть
                $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note, true, false)", [
                    ':userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => "Системное изъятие",
                    ':type' => "other",
                ]);
                $result = pghelper::pgarr2arr($query->queryScalar());
                if ($result[0] != 0)
                    return $result;

                $docs = "";
                
                //возврат делает не получатель
                $par_rec = Facility::findParent(\Yii::$app->user->identity->object_uid);
                $par_sen = Facility::findParent($invoiceSrc->object_uid);
                if($par_sen == $par_rec)
                {
                    //пользователь в одной ветке с отправителем
                    //431
                    $fns = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'codes' => pghelper::arr2pgarr($codes),
                                'object_uid' => $invoiceSrc->object_uid,
                                'newobject_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                'operation_uid' => Fns::OPERATION_OUTCOMESELF_ID,
                                'invoice_uid' => $invoiceSrc->id,
                                'fnsid' => '431',
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    
                    $docs .= " 431 - ".($fns->id ?? 'ошибка');
                }
                else
                {
                    //ТОДО проверку на кконтрактый передан или нет
                    $indcnt = $grpcnt = 0;
                    $res = \Yii::$app->db->createCommand("SELECT codetype_uid,count(*) as cnt FROM _get_codes_array(:codes) as codes 
                                                                        LEFT JOIN generations ON codes.generation_uid = generations.id
                                                                        GROUP by 1
                                                        ")->queryAll();
                    foreach($res as $r)
                        if($r["codetype_uid"] == CodeType::CODE_TYPE_GROUP)
                            $grpcnt+=$r["cnt"];
                        else
                            $indcnt += $r["cnt"];
                    //415 от отправителя + 701 от возвращающего
                    $fns415 = Fns::createDoc([
                                'created_by' => $invoiceSrc->created_by,
                                'state' => Fns::STATE_CREATED,
                                'codes' => pghelper::arr2pgarr($codes),
                                'object_uid' => $invoiceSrc->object_uid,
                                'newobject_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                'operation_uid' => Fns::OPERATION_OUTCOMERETAIL_ID,
                                'invoice_uid' => $invoiceSrc->id,
                                'fnsid' => '415',
                                'grpcnt' => $grpcnt,
                                'indcnt' => $indcnt,
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    //возможно сбросить флаг накладной updated_external
                    
                    $fns701 = Fns::createDoc([
                                'created_by' => \Yii::$app->user->getIdentity()->id,
                                'state' => Fns::STATE_CREATED,
                                'codes' => pghelper::arr2pgarr($codes),
                                'object_uid' => \Yii::$app->user->getIdentity()->object_uid,
                                'newobject_uid' => $invoiceSrc->object_uid,
                                'operation_uid' => Fns::OPERATION_INCOME_ID,
                                'invoice_uid' => $invoiceSrc->id,
                                'data' => pghelper::arr2pgarr([1]),
                                'fnsid' => '701',
                                'grpcnt' => $grpcnt,
                                'indcnt' => $indcnt,
                                'updated_at' => new Expression('timeofday()::timestamptz'),
                                'fns_start_send' => new Expression('timeofday()::timestamptz'),
                                'full_codes' => pghelper::arr2pgarr($codes),
                    ]);
                    
                    $docs .=  "415 - ".($fns415->id ?? 'ошибка') . " & 701 - ". ($fns701->id ?? 'ошибка');
                }
                
                //возврат кодов без ФНС
                                                                                                            //не отпавлять фнс  
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Пользовтаель - получатель кода - НЕТ. изъятие + возврат (документы: '.$docs.')'
                ]);
            }
                
        }
        else
        {
            //поиск 606S
            $fns606 = Fns::findLastOperation($codes, [Fns::OPERATION_606]);
            //606 на объект пользователя!!! TODO
            if(!empty($fns606))
            {
                //изымаем без проверки родителей и не отчитываясь в фнс
                $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note, true, true)", [
                    ':userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => "Системное изъятие",
                    ':type' => "other",
                ]);
                $result = pghelper::pgarr2arr($query->queryScalar());
                if ($result[0] != 0)
                    return $result;

                //возврат кодов без ФНС
                //не отпавлять фнс  
                $query = \Yii::$app->db->createCommand('select make_returned(:userID, :codes, :type, :note, :nofns, :comment)', [
                    'userID' => Yii::$app->user->getId(),
                    ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                    ':note' => $note,
                    ':type' => $type,
                    ':nofns' => true,
                    ':comment' => 'Получен 606 - Да. изъятие + возврат ',
                ]);
            }
            else
            {
                //606 не было!!
                if(SERVER_RULE == SERVER_RULE_SKLAD)
                {
                    $fns->isNewRecord = true;
                    $fns->fns_log = "for_update";
                }
                
                if (\Yii::$app->user->identity->object->fns_subject_id == $invoiceSrc->object->fns_subject_id)
                {
                    //Пользовтаель отправитель кода
                    //[ВОЗВРАТ]
                    return Code::returnedExtBack($codes, $note, $invoice, $fns, $type);
                }
                else
                {
                    if (\Yii::$app->user->identity->object->fns_subject_id == $invoiceSrc->newObject->fns_subject_id)
                    {
                        //пользователь получатель кода - накладная введенная
                        //[ПЕРЕОТПРАВКА]
                        return Code::returnedExtResend($codes, $note, $invoice, $fns, $type);
                    }
                    else
                    {
                        //[ВОЗВРАТ] на стороннний объект
                        return Code::returnedExtBack2($codes, $note, $invoice, $fns, $type);
                    }
                }
            }
        }



        $result = pghelper::pgarr2arr($query->queryScalar());
        AuditLog::Audit(AuditOperation::OP_CODES, "Возврат отгруженных кодов", [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => "Примечание", 'value' => $note],
            ['field' => "QR", 'value' => $qrcode]
        ]);

        // добавляем возвращаемые коды в накладную
        if (isset($result[0]) && ($result[0] == 0) && $invoiceSrc) {
            $childes = [];

            foreach ($codes as $code) {
                $c = Code::findOneByCode($code);
                if ($c && $c->childrens)
                    array_merge($childes, pghelper::pgarr2arr($c->childrens));
            }

            $invoiceSrc->returned_codes = pghelper::arr2pgarr(array_merge($codes, $childes));
            $invoiceSrc->save();
        }

        return $result;
    }

    /**
     * Изъятие кодов
     * 
     * @param array $codes массив кодов
     * @param type $note причиная изъятие - строка - жестко заданная
     * @param type $doc - необязательынй параметр - номер документа для изъятия - испольхуется при доп изъятиях
     * @param type $docdate  - необязательный параметр - дада документа на изъятие
     * @param type $qrcode   -  qr code - строка - для проверки рафармы - необязательный
     * @return array
     * @throws NotAcceptableHttpException
     */
    public static function withdrawal(array $codes, $note, $doc = "", $docdate = "", $qrcode)
    {
        $codes = array_map(function ($code) {
            return ($code instanceof Code) ? $code->code : $code;
        }, $codes);

        if($note == "На контроль")
            $type = "control";
        elseif($note == "В архив ОКК")
            $type = "archive";
        elseif($note == "Декларирование/Сертификация")
            $type = "declar";
        elseif($note == "Доукомплектация")
            $type = "douk";
        elseif($note == "Ошибка при группировке")
            $type = "err";
        elseif ($note == "Выборочный контроль")
            $type = "ext1";
        elseif ($note == "Таможенный контроль")
            $type = "ext2";
        elseif ($note == "Федеральный надзор")
            $type = "ext3";
        elseif ($note == "В целях клинических исследований")
            $type = "ext4";
        elseif ($note == "В целях фармацевтической экспертизы")
            $type = "ext5";
        elseif ($note == "Недостача")
            $type = "ext6";
        elseif ($note == "Отбор демонстрационных образцов")
            $type = "ext7";
        elseif ($note == "Списание без передачи на уничтожение")
            $type = "ext8";
        elseif ($note == "Вывод из оборота КИЗ, накопленных в рамках эксперимента")
            $type = "ext9";
        elseif ($note == "Производственный брак")
            $type = "ext15";
        elseif ($note == "Списание разукомплектованной потребительской упаковки")
            $type = "ext16";
        elseif ($note == "Производство медицинских изделий")
            $type = "ext17";
        elseif ($note == "Производство медицинских препаратов")
            $type = "ext18";
        else
            $type = "other";

        if(!\Yii::$app->user->can('codeFunction-withdrawal-tsd-'.$type))
            throw new NotAcceptableHttpException('Запрет на выполнение операции');

        if(in_array($type,["control","archive", "declar"]))
        {
            $query = \Yii::$app->db->createCommand("select make_defected(:userID, :codes, :type, :note)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
            ]);
            AuditLog::Audit(AuditOperation::OP_CODES, "Отбор образцов", [
                ['field' => 'Коды', 'value' => $codes],
                ['field' => "Примечание", 'value' => $note],
                ['field' => "Номер документа", 'value' => $doc],
                ['field' => "Дата документа", 'value' => $docdate],
                ['field' => "QR", 'value' => $qrcode]
            ]);
        }
        elseif(in_array($type, ["ext1", "ext2", "ext3", "ext4", "ext5", "ext6", "ext7", "ext8", "ext9","ext15","ext16", "ext17", "ext18"]))
        {

            $query = \Yii::$app->db->createCommand("select make_defected_ext(:userID, :codes, :type, :note, :doc, :docdate)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
                ':doc' => $doc,
                ':docdate' => $docdate,
            ]);
            AuditLog::Audit(AuditOperation::OP_CODES, "Отбор образцов", [
                ['field' => 'Коды', 'value' => $codes],
                ['field' => "Примечание", 'value' => $note],
                ['field' => "Номер документа", 'value' => $doc],
                ['field' => "Дата документа", 'value' => $docdate],
                ['field' => "QR", 'value' => $qrcode]
            ]);
        }
        else
        {  //douk, err, other
            $query = \Yii::$app->db->createCommand("select make_grp_exclude2(:userID, :codes, :type, :note)", [
                ':userID' => Yii::$app->user->getId(),
                ':codes' => new Expression(pghelper::arr2pgarr($codes)),
                ':note' => $note,
                ':type' => $type,
            ]);
            AuditLog::Audit(AuditOperation::OP_CODES, "Изъятие из группы", [
                ['field' => 'Коды', 'value' => $codes],
                ['field' => "Примечание", 'value' => $note],
                ['field' => "Номер документа", 'value' => $doc],
                ['field' => "Дата документа", 'value' => $docdate],
                ['field' => "QR", 'value' => $qrcode]
            ]);
        }

        $result = pghelper::pgarr2arr($query->queryScalar());
        return $result;

    }

    public function getDataMatrixUrl($check = true) {
        
        Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/download', 'id' => $this->id, 'access-token' => $token]);
                
        $url = \Yii::$app->urlManager->baseUrl. '/' . Yii::$app->params['dataMatrixFile'];

        if ($this->generation->codetype_uid == CodeType::CODE_TYPE_GROUP) {
            if (!empty($this->generation->object->gs1) || $this->generation->object->external)
            {    
//                $url .= '?code=' . base64_encode($this->code) . '&type=code128&aa=' . $this->code;
                $url = \Yii::$app->urlManager->baseUrl . '/barcode' . '?code=' . base64_encode($this->code);
            }    
            else
                $url .= '?code=' . base64_encode($this->code) . '&type=EAN13';
        } else {
//            $url .= '?code=' . urlencode(($check) ? $this->getCheckUrl() : $this->getViewUrl());
            return $this->getViewUrl();
        }

        return $url;
    }

    /**
     * URL для проверки потребителем
     *
     * @return string
     */
    public function getCheckUrl()
    {
        $codeGenerationParams = \Yii::$app->params['codeGeneration'];
        $codeGenerationParamsUrl = $codeGenerationParams['codeCheckUrl'];

        return str_replace('{code}', $this->code, $codeGenerationParamsUrl);
    }

    /**
     * URL для проверки внутренними системами
     *
     * @return string
     */
    public function getViewUrl()
    {
        $url = \Yii::$app->urlManager->baseUrl . '/' . \Yii::$app->params['dataMatrixFile'];

        if ($this->generation->codetype_uid == CodeType::CODE_TYPE_GROUP) {
            $url .= '?code=' . base64_encode($this->code) . '&type=EAN13';
        } else {
//            getcodes.*, product.cdate, product.expdate, product.series, nomenclature.gtin, nomenclature.sngroup_uid
            $fields = $this->toArray(['code', 'release_date'], ['checkUrl', 'gtin', 'code_sn', 'price']);
            $product = $this->product->toArray(['cdate', 'expdate', 'series']);

            $saveFields = [
                'gs1' => null,
                'gtin' => $fields['gtin'],
                'series' => $product['series'],
                'cdate' => $product['cdate'],
                'expdate' => $product['expdate'],
                'code' => $fields['code'],
            ];
 
            $gs1 = str_replace(chr(29), chr(232), \app\commands\GenerationController::genGS1v20170125($saveFields,false));
            //$gs1 = \app\commands\GenerationController::genGS1v20170125($saveFields);
            $url .= '?s=1&code=' . base64_encode($gs1);
        }

        return $url;
    }

    public function getContentByProduct() {
        if ($this->codeType->abbr == 'group') {
            $sql = "select nomenclature.gtin,series,count(*) as cnt from get_code_content(:CODE) as codes
                                    left join nomenclature on codes.nomenclature_uid = nomenclature.id
                                    WHERE codetype='Индивидуальный'
                                    group by 1,2";
            return \Yii::$app->db->createCommand($sql, [
                        ':CODE' => $this->code
                    ])->queryAll();
        } else
            return [
                    [
                        'gtin' => $this->product->nomenclature->gtin,
                        'series' => $this->product->series,
                        'cnt' => 1,
                    ]
                ];
    }

    
    public function getChildGroup($deep = true)
    {
        if($this->codeType->abbr == 'group')
        {
            $sql = "select product_uid,nomenclature as name,count(*) from get_code_content(:CODE) WHERE codetype='Индивидуальный' group by 1,2";
                return \Yii::$app->db->createCommand($sql, [
                            ':CODE' => $this->code
                        ])->queryAll();
        }
        else
            return [];
    }
    public function getContent(){
        $sql = "select code,parent_code,nomenclature,flags,codetype,series from get_code_content(:CODE) order by parent_code";
        return \Yii::$app->db->createCommand($sql, [
                    ':CODE' => $this->code
                ])->queryAll();
    }
    /**
     * SQL
     *
     * @return Command
     */
    public function getChildIndividual()
    {
        $sql = "SELECT * FROM get_code_content(:CODE)";
        $countSql = count($this->childs);
        return new SqlDataProvider([
            'sql' => $sql,
            'params' => [':CODE' => $this->code],
            'totalCount' => $countSql,
            'pagination' => false,
        ]);
    }

    /**
     * Получить 1 индивидуальный вложенный код
     * Для определения retail в палетах
     *
     * @return Code|false
     */
    public function getOneDeepChild()
    {
        $sql = "SELECT code FROM get_code_content(:CODE) WHERE codetype = 'group' LIMIT 1";
        $code = \Yii::$app->db->createCommand($sql, [':CODE' => $this->code])->queryOne();
        if ($code) {
            $code = self::findOneByCode($code['code']);
        } else {
            $code = null;
        }
        return $code;
    }

    /**
     * @param $code
     *
     * @return static
     */
    public static function findAllByGen($generation) {
        $query = self::find();
        $query->from("_get_codes('generation_uid=''" . pg_escape_string($generation->id) . "''') as codes");

        return $query;
    }

    
    /**
     * @param $code
     *
     * @return static
     */
    public static function findOneByCode($code)
    {
        $query = self::find();
        $query->from("_get_codes('code=''".self::stripCode($code)."''') as codes");

        return $query->one();
    }

    public function getHistoryLastOutCome()
    {
//        select * from history as a
//   LEFT JOIN history_data as b ON (a.id = b.history_uid)
//   LEFT JOIN objects as c ON (b.object_uid = c.id)
//   WHERE operation_uid = 4 and code_uid=17798728
//   ORDER by created_at desc LIMIT 1

        if (SERVER_RULE == SERVER_RULE_RF) {
            return $this->hasOne(History::className(), ['code_uid' => 'id'])
                                ->from(['history' => '_get_code_history(\'' . $this->code . '\',\'{4,14,52,53,34,56}\',1)'])
                                ->with(['historyOperation'])
                                ->orderBy('created_at DESC');
        } else {
            $history = $this->hasOne(HistoryReplica::className(), ['code_uid' => 'id'])->andWhere(['operation_uid'=>[4,14,52,53,34,56]])
                    ->limit(1)
                    ->orderBy('id DESC');
            return $history;
        }
    }

    public function getHistoryLastView()
    {
        if (SERVER_RULE == SERVER_RULE_RF) {
            return $this->hasMany(History::className(), ['code_uid' => 'id'])
                ->from(['history'=>'_get_code_history(\''.$this->code.'\',\'{11}\',50)'])
                ->with(['historyOperation'])
                ->orderBy('created_at DESC')
                ->limit(50);
        } else {
            $history = $this->hasMany(HistoryReplica::className(), ['code_uid' => 'id'])->andWhere(['operation_uid' => [11]])
                    ->limit(50)
                    ->orderBy('created_at DESC');
            return $history;
        }    
    }

    public function getHistory($limit = 5) {
        if (SERVER_RULE == SERVER_RULE_RF) {
            return $this->hasMany(History::className(), ['code_uid' => 'id'])
                            ->from(["history" => "_get_code_history('" . $this->code . "','{}'," . $limit . ")"])
                            ->with(['historyOperation'])
                            ->orderBy('created_at DESC');
        } else {
            $history = $this->hasMany(HistoryReplica::className(), ['code_uid' => 'id'])
                    ->limit($limit)
                    ->orderBy('created_at DESC');
            return $history;
        }
    }

    /**
     * @param bool $forUsers Для потребителей?
     *
     * @return mixed|string
     */
    public function getStatusMessage($forUsers = true)
    {

//        product_uid != NULL  -  привязана товарная карта (товарная карта - свойства)
//        is_empty(flag) = true  - код не выпущен / выпущен (дата выпуска release_date)
//        is_released(flag) = true - отгружен со склада/ находится на складе   - object_uid ссылка на объект склада
//        is_defected(flag) = true - код изъят/ не изъят
//        is_claim(flag) = true  - подделка/не подделка
//        is_retail(falg) = true - ВЫпущен в розницу

        if (false == $forUsers) {
            $message = [];

            if ($this->getCodeTypeId() == CodeType::CODE_TYPE_GROUP) {
                if ($this->empty) $message[] = 'Не активен';
                if ($this->retail) $message[] = 'Отгружен контрагенту';
                if ($this->released) $message[] = 'Перемещение'; elseif (!$this->retail && !$this->empty) $message[] = 'На объекте';
                if ($this->removed) $message = ['Утилизирован'];
                if ($this->blocked) $message[] = 'Заблокирован';
                if ($this->defected) $message = ['Выведен из оборота'];
            } else {
                if (!$this->product_uid) $message[] = 'Товарная карта не привязана'; //else $message[] = 'Товарная карта ' . @$this->product->nomenclature->name;
                if ($this->empty) $message[] = 'Не активен';// else $message[] = 'Выпущен/упакован';// . Yii::$app->formatter->asDate($this->release_date);
//                if ($this->released) $message[] = 'Отгужен со склада <ссылка объекта>'; else 'На складе <ссылка на объект>';
                if ($this->defected) $message[] = 'Код изъят';
                if ($this->claim) $message[] = 'Подделка';
                if ($this->retail) $message[] = 'Отгружен контрагенту';
                if ($this->gover) $message[] = 'Госзаказ';
                if ($this->released) $message[] = 'Перемещение'; elseif (!$this->retail && !$this->empty && !$this->claim ) $message[] = 'На объекте';
                if ($this->defected) $message = ['Выведен из оборота'];
                if ($this->removed) $message = ['Утилизирован'];
                if ($this->blocked) $message[] = 'Заблокирован';
                if ($this->serialized) $message[] = 'Сериализован';
                if ($this->brak) $message[] = 'Брак сериализации';

//                if (!$this->product_uid) $message = 'Товарная карточка не привязана';
//                if ($this->empty) $message = 'Код не выпущен';
//                if ($this->released) $message = 'Отгужен со склада';
//                if ($this->defected) $message = 'Код изъят';
//                if ($this->claim) $message = 'Подделка';
//                if ($this->retail) $message = 'Выпущен в розницу';
            }

            $message = implode(", ", $message);
            return $message;
        } else {
            // Detect message
            $state = "USER_CHECK_SUCCESS";
            if (!$this->retail)
                $state = "USER_CHECK_CLAIM";
            elseif ($this->gover)
                $state = "USER_CHECK_GOVERNMENT";
            elseif ($this->empty || $this->claim || $this->removed)
                $state = "USER_CHECK_CLAIM";
            elseif ($this->defected)
                $state = "USER_CHECK_DEFECTED";
            elseif (empty($this->product_uid))
                $state = "USER_CHECK_WARNING";

            $message = Message::find()->andWhere(['name' => $state])->one();

            return ($message) ? $message->message : '';
        }
    }

    public function delete()
    {
        throw new MethodNotAllowedHttpException("You can't delete code");
    }

    public static function deleteAll($condition = '', $params = [])
    {
        throw new MethodNotAllowedHttpException("You can't delete code");
    }

    public function getRetailInvoice()
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            return null;
        }
        $query = $this->getHistoryLastOutCome();
        $query->where(['operation_uid' => 14]);
        $h = $query->one();
        if ($h) return Invoice::findOne($h->history->invoice_uid);
        else return null;
    }

    public function getGtin()
    {
        return $this->product->nomenclature->gtin;
    }

    /**
     * Получение кода л2
     * @return int|null
     */
    public function getPaletraCode()
    {
        $pCode = null;
        if ($this->parent_code) {
            $parentCode = $this->parent;

            if ($parentCode->parent_code) {
                $parentCode = $parentCode->parent;
                if ($parentCode) {
                    $pCode = $parentCode->code;
                }
            }
        }
        return $pCode;
    }
    /**
     * Получение кода л3
     * @return int|null
     */
    public function getL3Code()
    {
        $pCode = null;
        if ($this->parent_code) {
            $parentCode = $this->parent;

            if ($parentCode->parent_code) {
                $parentCode = $parentCode->parent;
                if ($parentCode) {
                    $l3Code = $parentCode->parent;
                    if($l3Code)
                    {
                        $pCode = $l3Code->code;
                    }
                }
            }
        }
        return $pCode;
    }

    /**
     * Импортирование сторонних кодов по групповому
     * @param array $codes
     * @param string $groupCode
     * @return string
     * @throws Exception
     * @throws InvalidConfigException *@throws Exception
     * @throws \ErrorException
     */
    public static function importCodes(array $codes, string $groupCode, string $generationUid, array $originalCodes = []): string
    {
        if ($groupCode === 'auto') {
            if ($generationUid === '') {
                throw new \LogicException('Не был передан id генерации для получения группового кода.');
            }

            $groupCode = self::getFreeGroupCodeFromGeneration($generationUid);
        }

        $groupCode = self::findOneByCode($groupCode);

        if ($groupCode == null) {
            throw new \ErrorException('Групповой код не найден.');
        }

        $product = $groupCode->product;
        $nomenclature = $product->nomenclature;
        $object = $nomenclature->object;
        
        /**
         * проверка GTIN у пришедших кодов
         */
        $errorGtinCodes = [];

        foreach($originalCodes as $code)
        {
            if(!preg_match('#^01'.$nomenclature->gtin.'.*#si', $code)) {
                $errorGtinCodes[] = self::stripCode ($code);
            }
        }

        if (count($errorGtinCodes) > 0) {
            throw new \ErrorException('GTIN кода не совпадает с GTIN заказа: ' . implode(',', $errorGtinCodes));
        }

        $existCodes = self::find()
            ->select(['code'])
            ->where(['code' => $codes])
            ->asArray()
            ->all();

        foreach ($existCodes as $key => $code) {
            $existCodes[$key] = $code['code'];
        }

        $codes = array_diff($codes, $existCodes);

        if (count($codes) === 0) {
            return $groupCode->code;
        }

        $generation = Code::getNotClosedGenerationForImport($object,CodeType::CODE_TYPE_INDIVIDUAL,$product->id);

        $generation->scenario = "external";
        $generation->cnt = $generation->cnt + count($codes);

        if ($generation->save() === false) {
            throw new \ErrorException('Не удалось обновить счетчик.');
        }

        $transaction = \Yii::$app->db->beginTransaction();

        try {
            foreach ($codes as $code) {
                \Yii::$app->db->createCommand("INSERT INTO codes (code,flag,product_uid,object_uid,generation_uid) "
                    . "VALUES (:code,0,:product,:object,:generation)", [
                    ":code" => $code,
                    ":product" => $product->id,
                    ":object" => $object->id,
                    ":generation" => $generation->id
                ])->query();
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \ErrorException('Не удалось импортировать сторонние коды в базу данных.');
        }

        AuditLog::Audit(AuditOperation::OP_CODES, 'Импортирование сторонних кодов', [
            ['field' => 'Коды', 'value' => $codes],
            ['field' => 'Групповой код', 'value' => $groupCode],
        ]);

        return $groupCode->code;
    }

    /**
     * Получает незавершенную генерацию для импорта
     * @param Facility $object
     * @param $codeType
     * @param null $product_uid
     * @param string $comment
     * @return Generation|null
     */
    public static function getNotClosedGenerationForImport(Facility $object,$codeType,$product_uid = null, $comment = '')
    {
        $generation = Generation::findOne(
            ['codetype_uid' => $codeType,
                'object_uid' => $object->id,
                'product_uid' => $product_uid,
                'comment' => $comment,
                'is_closed' => false
            ]
        );

        $rootUser = User::findByLogin('root');
        if(empty($generation))
        {
            $generation = new Generation();
            $generation->scenario = "external";
            $generation->load([
                'codetype_uid' => $codeType,
                'status_uid' => GenerationStatus::STATUS_READY,
                'created_by' => $rootUser->id,
//                'comment' => 'генерация для внешних кодов',
                'object_uid' => $object->id,
                'cnt' => 0,
                'capacity' => '0',
                'prefix' => '',
                'product_uid' => $product_uid,
                'comment' => $comment,
            ], '');
            $generation->save(false);
            $generation->refresh();
        }
        return $generation;
    }

    /**
     * Возвращает код из переданной генерации
     * @param string $generationUid
     * @return string
     * @throws Exception
     * @throws \ErrorException
     */
    public static function getFreeGroupCodeFromGeneration(string $generationUid) :string
    {
        $groupCode = \Yii::$app->db->createCommand("SELECT _get_grp_by_generation(:generation)",[
            'generation' => $generationUid
        ])->queryScalar();

        if ($groupCode === null) {
            throw new \ErrorException('Не удалось получить групповой код.');
        }

        return $groupCode;
    }

    /**
     * @param string $code
     * @param string $parentCode
     * @param int $flag
     */
    public static function updateParentCode(string $code, string $parentCode, int $flag): void
    {
        \Yii::$app->createCommand(
            'UPDATE codes SET parent_code=:parent_code, flag=:flag WHERE code=:code',
            [
                'parent_code' => $parentCode,
                'flag' => $flag,
                'code' => $code,
            ]
        )->execute();
    }

    /**
     * @param string $code
     */
    public static function setPalletFlagToCode(string $code): void
    {
        \Yii::$app->createCommand(
            'UPDATE codes SET flag=:flag WHERE code=:code',
            [
                'flag' => self::CODE_FLAG_PALETA,
                'code' => $code,
            ]
        )->execute();
    }

    /**
     * Возвращает данные о кодах
     * @param array $codes
     * @return array
     * @throws Exception
     */
    public static function getCodesData(array $codes) :array
    {
        $codesData = \Yii::$app->db->createCommand(
            'select * from _get_codes_array(:codes)',
            ['codes' => pghelper::arr2pgarr($codes)]
        )->queryAll();

        return $codesData;
    }
}
