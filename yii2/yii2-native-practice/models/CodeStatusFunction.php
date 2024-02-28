<?php
/**
 * @link http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 08.05.15
 * Time: 16:44
 */

namespace app\modules\itrack\models;

use Exception;
use yii\base\Model;
use app\modules\itrack\components\pghelper;
use Yii;
/**
 * Class CodeStatusFunction
 *
 * Обработка работы со статусом кодов
 *
 * @package app\modules\itrack\models
 */
class CodeStatusFunction extends Model
{

    // activation
    public $product_uid;
    public $groupCode;

    // outcome
    public $invoice;
    public $invoiceDate;

    public $doc;
    public $docDate;

    public $note;

    public $newcodes;
    public $codes;
    public $code;
    public $serial;

    public $bdate;
    public $edate;
    public $series;
    public $force;

    public $object_uid;
    public $manufacturer_uid;
    public $manufacturer_md_uid;

    public $type;
    public $status;
    public $infotxt;
    public $canRepack = false;
    public $generationUid = '';
    public $foreign;

    public $activationCodes;
    public $qrcode;
    
    public $s_shift;
    public $s_date;
    public $s_zakaz;
    public $s_brak;
    public $s_brak_flag;
    public $s_fio;
    public $equipid;
    
    public $multipleSerialization;

    public function rules()
    {
        return [
            [['product_uid', 'newcodes', 'codes', 'invoice', 'invoiceDate', 'groupCode', 'code', 'type', 'object_uid','serial'], 'required', 'except' => ['serialize'], 'message' => 'Не заполнено поле {attribute}'],
            [['invoice'], 'trim'],
            [['invoiceDate', 'docDate'], 'match', 'pattern' => '/\d{4}-\d{2}-\d{2}/i'],
            [['invoiceDate', 'docDate'], 'date', 'format' => 'php:Y-m-d'],
            //[['invoiceDate', 'docDate'], 'date', 'max' => date("Y-m-d")],
            [['s_date'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            [['object_uid'], 'number', 'min' => 1],
            ['force', 'default', 'value' => 0],
            [['qrcode', 'doc', 'note', 'manufacturer_uid','groupCode', 'generationUid'], 'string'],
            [['s_brak_flag', 'canRepack', 'foreign'], 'boolean'],
            [['multipleSerialization'], 'required', 'on' => ['serializeMultiple']],
            ['note', 'required', 'on' => ['block','blockByDate','unblock','unblockByDate','defected', 'defectedByDate', 'removed', 'removedByDate', 'claim', 'claimByDate', 'returned', 'withdrawal']],
//            ['manufacturer_uid', 'required', 'on' => ['outcomRetail']]
//            ['groupCode', 'exist', 'targetClass' => Code::className(), 'targetAttribute' => 'code'],
            [['equipid', 's_fio'], 'integer'],
            ['equipid', 'exist', 'targetClass' => Equip::className(), 'targetAttribute' => 'id'],
            ['s_fio', 'exist', 'targetClass' => User::className(), 'targetAttribute' => 'id'],
            [['s_shift', 's_date', 's_brak', 's_brak_flag', 's_fio', 'equipid'], 'required', 'on' => ['serialize']],
            [['s_shift', 's_zakaz', 's_brak', 'manufacturer_md_uid'], 'string'],
            [['foreign'], 'default', 'value'=> function() {
                return (Constant::get('useForeignCodes') === 'enabled') ? true : false;
            }],
        ];
    }

    public function attributeLabels() {
        return [
            'invoiceDate' => 'Дата накладной',
            'object_uid' => 'Объект',
        ];
    }
    
    
    public function scenarios()
    {
        return [
            'remove' => ['codes', 'note'],
            'removeTSD' => ['qrcode', 'codes', 'note'],
            'removeByDate' => ['note', 'bdate', 'edate', 'series'],
            'block' => ['codes', 'note'],
            'blockByDate' => ['note', 'bdate', 'edate', 'series'],
            'unblock' => ['codes', 'note'],
            'unblockByDate' => ['note', 'bdate', 'edate', 'series'],
            'pack' => ['groupCode', 'codes', 'canRepack', 'foreign', 'generationUid'],
            'packFull' => ['groupCode', 'codes', 'canRepack', 'foreign', 'generationUid'],
            'paleta' => ['groupCode', 'codes'],
            'paletaUni' => ['groupCode', 'codes'],
            'paletaAdd' => ['groupCode', 'codes'],
            'paletaAddUni' => ['groupCode', 'codes'],
            'gofraAdd' => ['groupCode', 'codes'],
            'gofraAddUni' => ['groupCode', 'codes'],
            'incom' => ['qrcode', 'invoice', 'invoiceDate', 'codes'],
            'incomeExt' => ['qrcode', 'invoice', 'invoiceDate', 'codes'],
            'incomLog' => ['qrcode', 'invoice', 'invoiceDate', 'codes'],
            'outcom' => ['qrcode', 'invoice', 'codes', 'object_uid', 'invoiceDate'],
            'outcomLog' => ['qrcode', 'invoice', 'codes', 'object_uid', 'invoiceDate'],
            'outcomRetail' => ['qrcode', 'invoice', 'invoiceDate', 'codes', 'manufacturer_md_uid'],
            'outcomRetailLog' => ['qrcode', 'invoice', 'invoiceDate', 'codes'],
            'unGroup' => ['groupCode', 'note'],
            'returned' => ['codes', 'note'],
            'returnedExt' => ['codes', 'note', 'invoice', 'invoiceDate','qrcode'],
            'withdrawal' => ['qrcode', 'codes', 'note', 'doc', 'docDate'],
            'back' => ['codes', 'note'],
            'refuse' => ['codes', 'note'],
            'transfer' => ['codes', 'object_uid'],
            'incomeReverse' => ['invoice', 'invoiceDate', 'codes'],
            'assign' => ['code', 'serial'],
            'relabel' => ['codes', 'newcodes'],
            'l3' => ['groupCode', 'codes', 'canRepack'],
            'l3Uni' => ['groupCode', 'codes', 'canRepack'],
            'l3Add' => ['groupCode', 'codes'],
            'l3AddUni' => ['groupCode', 'codes'],
            'serialize' => ['code', 's_shift', 's_date', 's_zakaz', 's_brak', 's_brak_flag', 's_fio', 'equipid'],
            'serializeMultiple' => ['multipleSerialization'],
        ];
    }

    /**
     * Формирование модели и ошибки по выполнению функции
     *
     * @param $model
     *
     * @return bool
     */
    private function result($model)
    {
        if (is_array($model)) {
            if (isset($model[0]) && ($model[0] == 0 || $model[0] == 999)) {
                // bug
                if($model[0] == 999) {
                    $this->status = 'AutoGeneration';
                } else {
                    $this->status = $model[1];
                }

                // фича
                if(in_array($this->scenario, ['outcomLog', 'outcomRetailLog'])) {
                    $invoice = Invoice::find()->andWhere(['invoice_number' => $this->invoice, 'invoice_date' => $this->invoiceDate])
                                    ->andFilterWhere(['in', 'created_by', [Yii::$app->user->getId(), User::SYSTEM_USER]])
                                    ->orderBy(['created_at' => SORT_DESC])->one();
                    $codes_cnt = Yii::$app->db->createCommand('SELECT series,count(*) as cnt from  get_full_codes(:codes) as codes
					left join generations on generation_uid = generations.id
                                        LEFT JOIN product ON codes.product_uid = product.id
					where codetype_uid = 1 and is_removed(flag)=false and is_defected(flag)=false
                                        group by 1',
                            [
                                ':codes' => pghelper::arr2pgarr($this->codes)
                            ]
                        )->queryAll();

                    try {
                        $invoice->updateExternal(false, $codes_cnt);
                    } catch (Exception $ex) {
                    }
                    if(!empty($invoice->vatvalue)) {
                        if(!$this->force) {
                            $this->addError('codes', $invoice->vatvalue);
                            return false;
                        }
                    }
                    $this->infotxt = !empty($invoice->infotxt) ? $invoice->infotxt : '';
                    if (!empty($this->infotxt) && !$this->force) {
                        return true;
                    }
                    else{
                        $this->infotxt = "";
                    }
                        
                }

                return true;
            } else {
                if(isset($model[1])) {
                    $this->addError('codes', $model[1]);
                }
                if (isset($model[2])) {
                    $this->addError('data', explode(',', $model[2]));
                }
                return false;
            }
        }
        return $model;
    }


    public function beforeValidate() {
        if(isset($this->code))
        {
            $this->code = Code::stripCode($this->code);
        }
        if(isset($this->codes))
        {
            if (is_array($this->codes)) {
                foreach($this->codes as $k=>$v)
                    $this->codes[$k] = Code::stripCode($v);
            }
            else
            {
                $this->codes = Code::stripCode($this->codes);
            }
            if(is_array($this->codes))
            {
                $err = [];
                $ar = array_count_values($this->codes);
                foreach($ar as $k=>$v)
                    if($v>1)$err[] = $k;
                if(count($err))    
                {
                    $this->addError('codes', 'Повторяющиеся коды: '.implode(',', $err));
                    return false;
                }
            }
        }
        return parent::beforeValidate();
    }

    public function serializeMultiple() {
        if (!$this->validate())
            return false;

        $serializationData = [];
        foreach ($this->multipleSerialization as $key => $serilizeData) {
            $serialization = new CodeStatusFunction(['scenario' => 'serialize']);
            $serialization->load($serilizeData, '');

            if (!$serialization->validate())
            {
                $this->addError('codes', 'Ошибка сериализации, номер элемента ' . $key . ' (' . implode(',', $serialization->getFirstErrors()) . ')');
                return false;
            }
            
            if(!isset($serializationData[$serialization->code]))
            {
                $serializationData[$serialization->code] = $serialization->getAttributes(['s_shift', 's_date', 's_zakaz', 's_brak', 's_brak_flag', 's_fio', 'equipid']);
            }
        }

        $result = Code::serializeMultiple($serializationData);

        return $this->result($result);
    }

    public function serialize()
    {
        if (!$this->validate())
            return false;
        
        $result = Code::serialize($this->code, $this->s_shift, $this->s_date, $this->s_zakaz, $this->s_brak, $this->s_brak_flag, $this->s_fio, $this->equipid);

        
        return $this->result($result);
    }

    public function assign()
    {
        if (!$this->validate())return false;

        $removed = Code::assign($this->code, $this->serial);
        return $this->result($removed);
    }
    
    public function relabel()
    {
        if (!$this->validate())return false;

        $removed = Code::relabel($this->codes, $this->newcodes);
        return $this->result($removed);
    }
    /**
     * Утилизация кодов
     *
     * @return bool
     */
    public function remove()
    {
        if (!$this->validate()) return false;

        $removed = Code::removeWeb($this->codes, $this->note);
        return $this->result($removed);
    }
    public function removeTSD()
    {
        if (!$this->validate()) return false;

        $removed = Code::removeTSD($this->codes, $this->note, $this->qrcode);
        return $this->result($removed);
    }
    public function back()
    {
        if (!$this->validate()) return false;

        $res = Code::back($this->codes, $this->note);
        return $this->result($res);
    }
    public function refuse()
    {
        if (!$this->validate()) return false;

        $res = Code::refuse($this->codes, $this->note);
        return $this->result($res);
    }

    public function removeByDate()
    {
        if (!$this->validate()) return false;

        $defected = Code::removeWebByDate($this->note, $this->bdate, $this->edate, $this->series);
        return $this->result($defected);
    }
    /**
     * Блокировка обращения
     *
     * @return bool
     */
    public function block()
    {
        if (!$this->validate()) return false;

        $block = Code::block($this->codes, $this->note);
        return $this->result($block);
    }

    public function blockByDate()
    {
        if (!$this->validate()) return false;

        $block = Code::blockByDate($this->note, $this->bdate, $this->edate, $this->series);
        return $this->result($block);
    }

    /**
     * Приемка кодов по обратному акцептированию
     * 
     * @return boolean
     */
    public function incomeReverse()
    {
        if (!$this->validate())
            return false;

        $result = Code::incomeReverse($this->codes, $this->invoice, $this->invoiceDate);
        return $this->result($result);
    }
    
    /**
     * Разблокировка обращения
     *
     * @return bool
     */
    public function unblock()
    {
        if (!$this->validate()) return false;

        $unblock = Code::unblock($this->codes, $this->note);
        return $this->result($unblock);
    }
    public function unblockByDate()
    {
        if (!$this->validate()) return false;

        $unblock = Code::unblockByDate($this->note, $this->bdate, $this->edate, $this->series);
        return $this->result($unblock);
    }

    /**
     * Упаковка гофрокороба
     *
     * @return bool
     */
    public function pack()
    {
        $originalCodes = $this->codes;
        if (!$this->validate()) return false;

        $modeAuto = false;

        if ($this->foreign) {
            try {
                $modeAuto = ($this->groupCode === 'auto') ? true : false;
                $this->groupCode = Code::importCodes($this->codes, $this->groupCode, $this->generationUid, $originalCodes);
            } catch (\Exception $e) {
                $this->addError('code', $e->getMessage());
                return $this->result(false);
            }
        }

        $pack = Code::pack($this->groupCode, $this->codes, $this->canRepack);
        if ($pack[0] == 0 && $modeAuto) $pack[1] = $this->groupCode;

        return $this->result($pack);
    }
    public function packFull()
    {
        $originalCodes = $this->codes;
        if (!$this->validate()) return false;
        if(empty($this->canRepack))$this->canRepack = false;

        $modeAuto = false;

        if ($this->foreign) {
            try {
                $modeAuto = ($this->groupCode === 'auto') ? true : false;
                $this->groupCode = Code::importCodes($this->codes, $this->groupCode, $this->generationUid, $originalCodes);
            } catch (\Exception $e) {
                $this->addError('code', $e->getMessage());
                return $this->result(false);
            }
        }

        $pack = Code::packFull($this->groupCode, $this->codes, $this->canRepack);
        if ($pack[0] == 0 && $modeAuto) $pack[1] = $this->groupCode;

        return $this->result($pack);
    }

    /**
     * Упаковка палеты
     *
     * @return bool
     */
    public function paleta()
    {
        if (!$this->validate()) return false;

        $pack = Code::paleta($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function l3()
    {
        if (!$this->validate()) return false;

        $pack = Code::l3($this->groupCode, $this->codes, $this->canRepack);
        return $this->result($pack);
    }
    public function paletaUni()
    {
        if (!$this->validate()) return false;

        $pack = Code::paletaUni($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function l3Uni()
    {
        if (!$this->validate()) return false;

        $pack = Code::l3Uni($this->groupCode, $this->codes, $this->canRepack);
        return $this->result($pack);
    }
    /**
     * Добавление в палету
     *
     * @return bool
     */
    public function paletaAdd()
    {
        if (!$this->validate()) return false;

        $pack = Code::paletaAdd($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function l3Add()
    {
        if (!$this->validate()) return false;

        $pack = Code::l3Add($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function paletaAddUni()
    {
        if (!$this->validate()) return false;

        $pack = Code::paletaAddUni($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function l3AddUni()
    {
        if (!$this->validate()) return false;

        $pack = Code::l3AddUni($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    /**
     * Добавление в гофру
     *
     * @return bool
     */
    public function gofraAdd()
    {
        if (!$this->validate()) return false;

        $pack = Code::gofraAdd($this->groupCode, $this->codes);
        return $this->result($pack);
    }
    public function gofraAddUni()
    {
        if (!$this->validate()) return false;

        $pack = Code::gofraAddUni($this->groupCode, $this->codes);
        return $this->result($pack);
    }

    /**
     * Приемка
     *
     * @return bool
     */
    public function incomeExt()
    {
        if (!$this->validate()) return false;

        $income = Code::incomExt($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
        return $this->result($income);
    }
    
    public function incom()
    {
        if (!$this->validate()) return false;

        $income = Code::incom($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
        return $this->result($income);
    }
    
    public function incomLog()
    {
        if (!$this->validate()) return false;

        $income = Code::incomLog($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
        return $this->result($income);
    }

    public function transfer() {
        if (!$this->validate())
            return false;
        if ($this->object_uid <= 0) {
            $this->addError('object_uid', 'Не указан объект');
            return false;
        }

        $transfer = Code::transfer($this->codes, $this->object_uid);
        return $this->result($transfer);
    }

    
    /**
     * Отгрузка кодов со склада
     *
     * @return bool
     */
    public function outcom()
    {
        if (!$this->validate()) return false;
        if ($this->object_uid <= 0) {
            $this->addError('object_uid', 'Не указан объект');
            return false;
        }

        $outcome = Code::outcom($this->invoice, $this->invoiceDate, $this->codes, $this->object_uid, $this->qrcode);
        return $this->result($outcome);
    }
    public function outcomLog()
    {
        if (!$this->validate()) return false;
        if ($this->object_uid <= 0) {
            $this->addError('object_uid', 'Не указан объект');
            return false;
        }

        $outcome = Code::outcomLog($this->invoice, $this->invoiceDate, $this->codes, $this->object_uid, $this->qrcode);
        return $this->result($outcome);
    }
    /**
     * Отгрузка в розницу
     *
     * @return bool
     */
    public function outcomRetail()
    {
        if (!$this->validate()) return false;

        $outcomeRetail = Code::outcomRetail($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode, $this->manufacturer_md_uid);
        return $this->result($outcomeRetail);
    }
    
    /*
     * отгрузка из логистики
     */
    public function outcomRetailLog()
    {
        if (!$this->validate()) return false;

        try
        {
            $user = \Yii::$app->user->getIdentity();
            if($user->object->isOTH())
                return $this->result(['1', 'Операция не может быть выполнена с места ответственного хранения']);
            
            $cur_invoice = $this->invoice;

            //проверка данных из аксапты. если аксапта выключена или трансферов в этой накладной нет идем по обычной ветке
            $check = false;
            $axapta = connectors\Connector::getActive(['Axapta'], 1);
            if (!empty($axapta)) {
                $check = true;
            }

            $res = Invoice::checkTransfer($this->invoice, $this->invoiceDate);
            if($check && empty($res['transfers']) && $res['sender'] != $user->object->fns_subject_id && !preg_match('#^RPH-#si', $cur_invoice))
                return $this->result(['1', 'В АХ нет данных по трансферу']);
            
            if($check && !empty($res['transfers']) && $res['sender'] != $user->object->fns_subject_id && !preg_match('#^RPH-#si', $cur_invoice))
            {
                if(empty($res['sender']))
                    return $this->result(['1', 'В АХ нет данных по отправителю (sender)']);
                
                $trans = \Yii::$app->db->beginTransaction();
                
                $old_object = $user->object_uid;
                
                //есть трансферы - узнаем что входит в текущую отгрузку!!
                $invoice_content = [];
                foreach($this->codes as $codestr)
                {
                    $code = Code::findOneByCode($codestr);
                    if(empty($code))
                        return $this->result(['1', 'Код не найден: ' . $codestr]);
                    $invoice_content[$code->code] = $code->getContentByProduct();
                    
                    /**
                     * Рфарм организационнно запрещено отгружать смешанные короба/паллеты
                     */
                    if(count($invoice_content[$code->code])>1)
                        return $this->result(['1', 'Отгрузка смешанных упаковок запрещена: ' . $code->code]);
                }
                
                //серия - кол-во - в текущей отгрузке...
                if(empty($res['current']))
                    return $this->result (['1', 'В АХ нет данных по отгружаемой продукции']);
                
                if(count($res['transfers'])>1)  //TODO множественные обрабатываем отдельно от одной - потом объединить!!!
                {
                    //пока непонятно как сравнивать
                    //return $this->result(['1','Несколько трансферов']);
                    
                    foreach($res['transfers'] as $ktransfer=>$transfer)
                    {
                        /**
                         * надо ли проверять тарнсферную накладну. в аксапте?????
                         */
                        $tr = Invoice::checkTransfer($transfer['invoice'], '');
                        if (empty($tr)) {
                            //нет данных по трансферной накладной
                            return $this->result(['1', 'Накладная не найдена (' . $transfer["invoice"] . ' -> ' . $this->invoice . ')']);
                        }
                        //получаем данные по отгрузкам. зарегистрированным в нашей системе с таким же номером TODO - проверка что по этому трансферу не уходило больше
                        
                        $codes_to_transfer = [];
                        foreach($transfer['detail'] as $key=>$cnt)
                        {
                            foreach($invoice_content as $code_to_transfer => $code_info)
                            {
                                if(($code_info[0]['gtin'] . '/' . $code_info[0]['series']) === $key)
                                {
                                    if($cnt >= $code_info[0]['cnt'])
                                    {
                                        $cnt -= $code_info[0]['cnt'];
                                        $transfer['detail'][$key] = $cnt;
                                        $codes_to_transfer[] = $code_to_transfer;
                                        unset($invoice_content[$code_to_transfer]);
                                    }
                                }
                            }
                        }
                        $res['transfers'][$ktransfer]['codes'] = $codes_to_transfer;
                        
                    }
                    if(count($invoice_content))
                    {
                        return $this->result(['1', 'Коды, которые не удается переместить ни по одной трансферной накладной: ' . implode(", ", array_keys($invoice_content))]);
                    }
                    
                    /*
                     * проводим трансферы
                     */
                    Yii::$app->user->identity->check_rights = false;  //отключаем проверку пермишена в пхп
                    Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','true',true)")->execute(); //отключаем проверку прав в БД
                    $codes_src = $this->codes;
                    foreach($res['transfers'] as $transfer)
                    {
                        $this->codes = $transfer['codes'];
                        //не перемещаем если по трасферу нет кодов
                        if(!is_array($transfer['codes']) || empty($transfer['codes']))
                            continue;

                        $newobject = Facility::find()->where(['fns_subject_id' => $res['sender']])->one();
                        if (empty($newobject) || $newobject->id == $old_object)
                            return $this->result(['1', 'Объект для трансфера (' . $res['sender'] . ') не найден']);

                        if ($newobject->isOTH()) {
                            $newobject = Facility::find()->where(['id' => Facility::findParent($newobject->id)])->one();
                        }

                        $newname = $newobject->name;
                        $newobject = Facility::findOne($newobject->id);
                        if (empty($newobject))
                            return $this->result(['1', "Объекта $newname для трансфера нет в видимых объектах пользователя"]);

                        //трансфер по накладной
                        $user->object_uid = $old_object;
                        $user->save(false);
                        $this->invoice = $transfer['invoice'];
                        $this->object_uid = $newobject->id;
                        $s = $this->outcomLog();
                        if (!$s)
                            return $this->result(['1', 'Трансфер: Ошибка перемещения']);

                        //меняем объект у юзверя для приемки
                        $user->object_uid = $newobject->id;
                        $user->save(false);
                        $s = $this->incomLog();
                        if (!$s)
                            return $this->result(['1', 'Трансфер: Ошибка приемки']);
                    }
                    
                    $this->codes = $codes_src;
                    
                    Yii::$app->user->identity->check_rights = true;   //включаем проверку пермишенов в пхп
                    Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','false',true)")->execute(); //включаем проверку прав в БД

                    $this->invoice = $cur_invoice;
                    $outcomeRetail = Code::outcomRetailLog($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
                    if ($outcomeRetail[0])
                        return $this->result(['1', 'Трансфер: Ошибка отгрузки (' . $outcomeRetail[1] . ')']);

                    $user->object_uid = $old_object;
                    $user->save(false);

                    $trans->commit();
                    return $this->result($outcomeRetail);

                }
                else 
                {
                    //коды из одного трансфера
                    //1. запросить с аксапты данные по трансферу. 
                    //2. сравнить что в аксапте в данном трасфере серия-кол-во - больше чем во всех наших накладных с таким номером трансфера...
                    //3. если больше - то делаем трансфер, приемку, отгрузку, если меньше - ошибку...
                    
                    //1.
                    $tr = Invoice::checkTransfer($res['transfers'][0]['invoice'], '');
                    if(empty($tr))
                    {
                        //нет данных по трансферной накладной
                        return $this->result(['1', 'Накладная не найдена ('.$res['transfers'][0]['invoice'].' -> '.$this->invoice.')']);
                    }
                    
                    /*
                     * ОТКЛЮЧЕНР по просьбе РФАРМ - не учитывается возврат!!!
                     * 
                    //получаем данные по отгрузкам. зарегистрированным в нашей системе с таким же номером
                    $tri = \Yii::$app->db->createCommand("select series,count(codes.id) as cnt from _get_codes_array((select array_agg(code) from (select unnest(realcodes) as code from invoices where invoice_number=:invoice) as icodes)) as codes
                                                        left join generations ON codes.generation_uid = generations.id
                                                        left join product ON codes.product_uid = product.id
                                                        WHERE generations.codetype_uid = :codetype
                                                        group by 1", [":invoice" => $res["transfers"][0]["invoice"], ":codetype" => CodeType::CODE_TYPE_INDIVIDUAL])->queryAll();
                    //прибавляем к tri , то что сейчас пытаются отправить codes_cnt
                    foreach($codes_cnt as $k=>$codes)
                        foreach($tri as $icodes)
                            if($icodes["series"] == $codes["series"])
                                $codes_cnt[$k]["cnt"]+=$icodes["cnt"];
                    //проверяем codes_cnt и $tr["current"]        
                    foreach($codes_cnt as $codes)
                    {
                        $found = false;
                        foreach($tr["current"] as $serie=>$cnt)
                        {
                            if($codes["series"] == $serie)
                            {
                                if($codes["cnt"]>$cnt)
                                    return $this->result(['1','В накладной '. $res["transfers"][0]["invoice"] . ' отгружено менее, чем отгужается в iTrack ('.$codes["cnt"].'/'.$cnt.')']);
                                $found = true;
                            }
                        }
                        if(!$found)
                            return $this->result(['1','Серия '.$codes["series"]. ' - не найдена в трансфере '.$res["transfers"][0]["invoice"]]);
                    }
                    //проверки пройдены , делаем перемещение 
                    */
                    
                    \Yii::$app->user->identity->check_rights = false;  //отключаем проверку пермишена в пхп
                    \Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','true',true)")->execute(); //отключаем проверку прав в БД

                    $newobject = Facility::find()->where(['fns_subject_id' => $res["sender"]])->one();
                    if(empty($newobject) || $newobject->id == $old_object)
                        return $this->result(['1', 'Объект для трансфера ('.$res['sender'].') не найден']);
                    
                    if($newobject->isOTH())
                    {
                        $newobject = Facility::find()->where(['id' => Facility::findParent($newobject->id)])->one();
                    }
                    
                    $newname = $newobject->name;
                    $newobject = Facility::findOne($newobject->id);
                    if(empty($newobject))
                        return $this->result(['1', "Объекта $newname для трансфера нет в видимых объектах пользователя"]);

                    //трансфер по накладной
                    $this->invoice = $res['transfers'][0]['invoice'];
                    $this->object_uid = $newobject->id;
                    $s = $this->outcomLog();
                    if(!$s)
                        return $this->result(['1', 'Трансфер: Ошибка перемещения']);
                    
                    
                    
                    //меняем объект у юзверя для приемки
                    $user->object_uid = $newobject->id;
                    $user->save(false);
                    $s = $this->incomLog();
                    if (!$s)
                        return $this->result(['1', 'Трансфер: Ошибка приемки']);
                    
                    
                    \Yii::$app->user->identity->check_rights = true;   //включаем проверку пермишенов в пхп
                    \Yii::$app->db->createCommand("SELECT set_config('itrack.dontcheckrights','false',true)")->execute(); //включаем проверку прав в БД

                    $this->invoice = $cur_invoice;
                    $outcomeRetail = Code::outcomRetailLog($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
                    if($outcomeRetail[0])
                        return $this->result(['1', 'Трансфер: Ошибка отгрузки ('.$outcomeRetail[1].')']);

                    $user->object_uid = $old_object;
                    $user->save(false);

                    $trans->commit();
                    return $this->result($outcomeRetail);
                }
            }
        } catch (\Exception $ex) {
            return $this->result(['1', $ex->getMessage()]);
        }

        
        $outcomeRetail = Code::outcomRetailLog($this->invoice, $this->invoiceDate, $this->codes, $this->qrcode);
        return $this->result($outcomeRetail);
    }
    /**
     * Разгруппировка
     * @return bool
     */
    public function unGroup()
    {
        if (!$this->validate()) return false;

        $unGroup = Code::unGroup($this->groupCode, $this->note);
        return $this->result($unGroup);
    }
    /**
     * Возврат кода
     *
     * @return array|bool
     */
    public function returned()
    {
        if (!$this->validate()) return false;

        $returned = Code::returned($this->codes, $this->note, $this->qrcode);
        return $this->result($returned);
    }
    /**
     * Возврат кода
     *
     * @return array|bool
     */
    public function returnedExt()
    {
        if (!$this->validate()) return false;

        try
        {
            $returned = Code::returnedExt($this->codes, $this->note, $this->invoice, $this->invoiceDate, $this->qrcode);
            return $this->result($returned);
        } catch (\Exception $ex) {
            return $this->result(['1', $ex->getMessage()]);
        }
    }

    /**
     * Изъятие из оборота/группы
     *
     * @return array|bool
     */
    public function withdrawal()
    {
        if (!$this->validate()) return false;

        $withdrawal = Code::withdrawal($this->codes, $this->note, $this->doc, $this->docDate, $this->qrcode);
        return $this->result($withdrawal);
    }

}