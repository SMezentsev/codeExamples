<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\models\ProductionOrder;
use app\modules\itrack\components\boxy\ActiveRecord;
use Yii;
use yii\base\ModelEvent;

/**
 * Class Generation
 *  Сущность генерация кодов (Заявка на генерацию кодов)
 *
 * Методы:
 *  - создание, просморт, список
 *  - удаление кодов не активированных в генерации (т.е. удаление не использованных кодов)
 *  - получение списка кодов для выгрузки
 *  - возможно печать кодов через веб для групповых наклеек
 *
 * @property string   $id            - идентификатор
 * @property string   $created_at    - Дата создания заявки
 * @property integer  $cnt           -Кол-во кодов в генерации
 * @property integer  $codetype_uid  -Тип кодов в генерации
 * @property integer  $capacity      - Разрядность кодов в генерации
 * @property string   $prefix        - префикс кодов в генерации
 * @property integer  $status_uid    - статус Генерации - ссылка
 * @property integer  $created_by    - ССылка на создателя
 * @property string   $deleted_at    - не используем
 * @property integer  $deleted_by    - не используем
 * @property string   $comment       - Комментарий при создании заявки - возможно служебная информаия по внутренним докам производителя
 * @property integer  $product_uid   — ID продукта для кодов
 * @property integer  $object_uid
 * @property integer  $save_cnt
 * @property boolean  $is_rezerv
 * @property string   $originalid
 * @property string   $parent_uid
 * @property string   $packing_status
 * @property string   $equip_uid
 * @property string   $data_uid
 *
 * @property CodeType $codeType
 * @property Code[]   $codes
 * @property Product  $product
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Generation",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="string", example="1234-1234-123"),
 *          @OA\Property(property="codetype_uid", type="integer", example=2),
 *          @OA\Property(property="status_uid", type="integer", example=3),
 *          @OA\Property(property="equip", type="integer", example=null),
 *          @OA\Property(property="object_uid ", type="integer", example=5),
 *          @OA\Property(property="is_rezerv", type="boolean", example=false),
 *          @OA\Property(property="cnt", type="integer", example=2),
 *          @OA\Property(property="cnt_src", type="integer", example=0),
 *          @OA\Property(property="capacity", type="integer", example=13),
 *          @OA\Property(property="prefix", type="string", example="10"),
 *          @OA\Property(property="comment", type="string", example="генерация для внешних кодов"),
 *          @OA\Property(property="save_cnt", type="integer", example=0),
 *          @OA\Property(property="prcnt", type="string", example="100"),
 *          @OA\Property(property="packing_status", type="string", example=null),
 *          @OA\Property(property="sent_to_suz", type="string", example=null),
 *          @OA\Property(property="created_at", type="string", example="2019-07-23 14:29:11+0300"),
 *          @OA\Property(property="number", type="string", example="1/123"),
 *          @OA\Property(property="third_party_order_id", type="string", example="123"),
 *          @OA\Property(property="urlDownloadPallet", type="string", example="http://itrack-rf-api.dev-og.com/generations/123"),
 *      }
 * )
 */
class Generation extends ActiveRecord
{
    static $auditOperation = AuditOperation::OP_GENERATION;
    public $count;
    public $sngroup_uid;
    public $newstatus_uid;

    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'generations';
    }

    /**
     * Метод для проверки возможности экспорта файлов. Если шара недоступна - генерация отменяем
     *
     * @return boolean
     */
    static function canExport()
    {
        $exportOCS = Constant::get('exportOCS');
        $exportUDA = Constant::get('exportUDA');
        if (empty($exportOCS) && empty($exportUDA)) {
            return true;
        }
        if (!empty($exportOCS)) {
            if (!is_dir($exportOCS)) {
                return 'Генерация отклонена. ' . $exportOCS . " не является папкой";
            }
            if (!file_exists($exportOCS . '/flag')) {
                return 'Генерация отклонена. ' . $exportOCS . " не содержит flag";
            }
        }
        if (!empty($exportUDA)) {
            if (!is_dir($exportUDA)) {
                return 'Генерация отклонена. ' . $exportUDA . " не является папкой";
            }
            if (!file_exists($exportUDA . '/flag')) {
                return 'Генерация отклонена. ' . $exportUDA . " не содержит flag";
            }
        }

        return true;
    }

    static function find()
    {
//        $qs = new \yii\db\Query;
//        $qs->select(new \yii\db\Expression('DISTINCT ON (parent_uid) parent_uid,status_uid as pstatus_uid'))
//                ->from('generations')
//                ->andFilterWhere(['IS NOT','parent_uid',null])->orderBy(['parent_uid'=>SORT_ASC, 'status_uid' => SORT_ASC]);

        $query = parent::find();
        $query->select('*, case when status_uid = ' . GenerationStatus::STATUS_CREATED . ' OR status_uid = ' . GenerationStatus::STATUS_PROCESSING . ' THEN  case when ceil(extract(epoch from (now()-created_at))::bigint/ (60 + CEIL(cnt/10000)*60)*100)>100 then 100 else ceil(extract(epoch from (now()-created_at))::bigint/ (60 + CEIL(cnt/10000)*60)*100) end WHEN status_uid = ' . GenerationStatus::STATUS_READY . ' THEN 100 ELSE 0 END as prcnt');
//                ->addSelect(['newstatus_uid' => 'case when pstatus_uid in (1,2) then pstatus_uid else status_uid end'])
//                ->leftJoin(['sub'=>$qs],'id = sub.parent_uid');
        return $query;
    }

    /**
     * Получение количество зарезервированных кодов
     *
     * @param int $objectId
     *
     * @return array
     */
    public static function reserveCount($objectId = null)
    {
        $query = self::find();
        $query->select(['status_uid', 'object_uid', 'codetype_uid', 'SUM(cnt) as count'])
            ->andWhere(['is_rezerv' => true])
            ->andFilterWhere(['object_uid' => $objectId])
            ->with('object')
            ->groupBy('status_uid, object_uid, codetype_uid')
            ->orderBy('object_uid');

        return $query->all();
    }

    /**
     * Получает заказ по его идентификатору
     *
     * @param string $generationId
     *
     * @return Generation
     * @throws \ErrorException
     */
    public static function getGenerationById(string $generationId)
    : Generation
    {
        try {
            $generations = Generation::find()
                ->where(['id' => $generationId])
                ->with('product', 'product.object')
                ->all();
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось получить заказ');
        }

        if (count($generations) === 0) {
            throw new \ErrorException('Заказ удален либо еще не создан.');
        }

        return array_pop($generations);
    }

    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['parent_uid','default','value' => null],
            [['created_at', 'deleted_at','packing_status', 'sent_to_suz', 'is_closed'], 'safe'],
            [['codetype_uid', 'capacity', 'status_uid', 'created_by', 'deleted_by', 'product_uid', 'object_uid', 'cnt_src', 'save_cnt', 'data_uid', 'equip_uid'], 'integer'],
            [['codetype_uid', 'status_uid', 'created_by', 'product_uid', 'cnt'], 'required'],
            [['prefix', 'comment', 'originalid', 'third_party_order_id'], 'string'],
            ['cnt', 'integer', 'min' => 1, 'max' => 1000000, 'message' => 'Количество заказываемых кодов должно быть в интервале от 1 до 1000000'],
            ['cnt',
                'filter',
                'filter' => function ($value) {
                    if ($this->scenario == 'default' || $this->scenario == 'groupCode') {
                        $res = self::reserveCount($this->object_uid);
                        
                        $c = 0;
                        foreach ($res as $g) {
                            if ($g["codetype_uid"] == $this->codetype_uid) {
                                if ($g["status_uid"] == 3) {
                                    $c += $g["count"];
                                }
                            }
                        }
                        if ($c < $this->cnt) {
                            throw new \yii\web\HttpException('400', 'Ошибка, недостаточно резерва для выполнения этого заказа');
                        }
                    }

                    return $value;
                }],
            [['is_rezerv'], 'boolean'],

            ['product_uid', 'exist', 'targetClass' => Product::class, 'targetAttribute' => 'id'],
            ['codetype_uid', 'exist', 'targetClass' => CodeType::class, 'targetAttribute' => 'id'],
            ['equip_uid', 'exist', 'targetClass' => Equip::class, 'targetAttribute' => 'id'],

            ['sngroup_uid', 'safe'],
        ];
    }

    public function attributes()
    {
        return array_merge(parent::attributes(), ['prcnt']);
    }

    public function scenarios()
    {
        return [
            'default'   => [
                'cnt',
                'codetype_uid',
                'capacity',
                'prefix',
                'comment',
                'product_uid',
                'object_uid',
                'created_by',
                'equip_uid',
                'status_uid',
                'parent_uid',
                'num',
            ],
            'external'  => [
                'cnt',
                'codetype_uid',
                'capacity',
                'prefix',
                'comment',
                'product_uid',
                'object_uid',
                'created_by',
                'status_uid',
            ],
            'groupCode' => [
                'cnt',
                'codetype_uid',
                'capacity',
                'prefix',
                'comment',
                'object_uid',
                'created_by',
                'status_uid',
                'parent_uid',
            ],
            'reserve'   => [
                'cnt',
                'codetype_uid',
                'is_rezerv',
                'object_uid',
            ],
        ];
    }

    public function fields()
    {
        $fields = [
            'uid' => 'id',
            'codetype_uid',
            'status_uid'/* =>  function(){
                if(!empty($this->newstatus_uid))
                    return $this->newstatus_uid;
                else
                    return $this->status_uid;
            }*/,
            'equip',
            'object_uid',
            'is_rezerv',
            'cnt',
            'cnt_src',
            'capacity',
            'prefix',
            'comment',
            'save_cnt',
            'prcnt',
            'packing_status',
            'sent_to_suz',
            'created_at' => function () {
                try {
                    return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
                } catch (\Exception $ex) {
                    return null;
                }
            },
            'number',

        ];

        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $addFields = [
                'is_closed',
                'third_party_order_id',
                'urlDownloadPallet' => function() {
                    if ($this->third_party_order_id == null) {
                        return null;
                    } else {
                        $user = \Yii::$app->user->getIdentity();
                        $token = $user->getToken();

                        return Yii::$app->urlManager->createAbsoluteUrl(
                            [
                                'itrack/generation/download',
                                'id' => $this->id,
                                'access-token' => $token,
                                'type' => 'THIRD_PARTY'
                            ]
                        );
                    }
                }
            ];

            $fields = array_merge($fields, $addFields);
        }

        return $fields;
    }

    /**
     * Внесение кодов в бд, только мастер
     *
     * @param type $arr
     */
    public function insertCodes($arr)
    {
        \Yii::$app->db->createCommand("begin")->execute();
        \Yii::$app->db->createCommand("DROP TABLE IF EXISTS ser_tmp")->execute();
        \Yii::$app->db->createCommand("CREATE TEMP TABLE ser_tmp (code varchar)")->execute();
        $pdo = \Yii::$app->db->getMasterPdo();
        $res = $pdo->pgsqlCopyFromArray("ser_tmp", $arr);
        \Yii::$app->db->createCommand("INSERT INTO codes (code, generation_uid, object_uid, product_uid) SELECT code,'" . $this->id . "'," . $this->object_uid . "," . $this->product_uid . " FROM ser_tmp")->execute();
        \Yii::$app->db->createCommand("commit")->execute();
    }

    /**
     * Внесение кодов в бд вместе с криптохвостами
     * @param array $codesData
     * @throws \yii\db\Exception
     */
    public function insertCodesWithCryptoTails(array $codesData)
    {
        \Yii::$app->db->createCommand("begin")->execute();
        \Yii::$app->db->createCommand("DROP TABLE IF EXISTS ser_tmp")->execute();
        \Yii::$app->db->createCommand("CREATE TEMP TABLE ser_tmp (code varchar, childrens varchar[])")->execute();
        $pdo = \Yii::$app->db->getMasterPdo();
        $res = $pdo->pgsqlCopyFromArray("ser_tmp", $codesData);
        \Yii::$app->db->createCommand("INSERT INTO codes (code, childrens, generation_uid, object_uid, product_uid) SELECT code, childrens,'" . $this->id . "'," . $this->object_uid . "," . $this->product_uid . " FROM ser_tmp")->execute();
        \Yii::$app->db->createCommand("commit")->execute();
    }
    
    public function getNumber()
    {
        return $this->object_uid . "/" . $this->num;
    }

    public function extraFields()
    {
        return [
//            'codes',
            'recipe',
            'status',
            'statusExt',
            'codeType',
            'product',
            'product_uid',

            'object',

            'deleted_at' => function () {
                return ($this->deleted_at) ? Yii::$app->formatter->asDatetime($this->deleted_at) : null;
            },
            'deleted_by',
            'created_by',
            'createdBy',
            'deletedBy',
            'save_cnt',

            'count'          => function () {
                return (int)$this->count;
            },
            'fileName'       => function () {
                return $this->getFileName();
            },
            'canZebraPrint'  => function () {
                $user = \Yii::$app->user->getIdentity();
                $token = $user->getToken();
                $ip = Equip::getZebraIp($this->object_uid);
                $eq = [];
                $e = Equip::find()->andWhere(['object_uid' => $this->object_uid])->all();
                foreach ($e as $a) {
                    if ($a->type == Equip::TYPE_ZEBRA) {
                        $eq[] = ['name' => $a->login, 'url' => Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/print-zebra', 'id' => $this->id, 'access-token' => $token, 'zebraId' => $a->id])];
                    }
                }

                if (!empty($token) && $this->cnt <= 100 && $this->status_uid == GenerationStatus::STATUS_READY && $this->codetype_uid == CodeType::CODE_TYPE_GROUP) {
                    if (!empty($eq)) {
                        if (count($eq) == 1) {
                            return $eq[0]["url"];
                        } else {
                            return $eq;
                        }
                    }
                    if (!empty($ip)) {
                        return Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/print-zebra', 'id' => $this->id, 'access-token' => $token]);
                    }

                    return null;
                } else {
                    return null;
                }
            },
            'urlDownload'    => function () {
                if (!in_array($this->status_uid, [GenerationStatus::STATUS_READY, GenerationStatus::STATUS_DECLINED, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT, GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_TIMEOUT])) {
                    return null;
                }
                if ($this->codetype_uid == CodeType::CODE_TYPE_GROUP && $this->save_cnt > 0) {
                    return null;
                }

                $file = $this->getFileName();
                
                if (!file_exists($file)) {
                    return null;
                }
                $user = \Yii::$app->user->getIdentity();
                $token = $user->getToken();

                return Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/download', 'id' => $this->id, 'access-token' => $token]);
            },
            'urlDownloadOCS' => function () {
                if (!in_array($this->status_uid, [GenerationStatus::STATUS_READY, GenerationStatus::STATUS_DECLINED, GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_TIMEOUT, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT])) {
                    return null;
                }
                if ($this->codetype_uid == CodeType::CODE_TYPE_GROUP && $this->save_cnt > 0) {
                    return null;
                }

                $file = $this->getFileNameOCS();
                
                if (!file_exists($file)) {
                    return null;
                }
                $user = \Yii::$app->user->getIdentity();
                $token = $user->getToken();

                return Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/download', 'id' => $this->id, 'access-token' => $token, 'type' => 'OCS']);
            },
            'urlPrint'       => function () {
                return (in_array($this->status_uid, [GenerationStatus::STATUS_READY, GenerationStatus::STATUS_DECLINED, GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_TIMEOUT, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT])) ? Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/print2', 'id' => $this->id]) : null;
            },
            'urlPrint' => function () {
                return (in_array($this->status_uid,[GenerationStatus::STATUS_READY, GenerationStatus::STATUS_DECLINED, GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_TIMEOUT, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT])) ? Yii::$app->urlManager->createAbsoluteUrl(['itrack/generation/print2', 'id' => $this->id]) : null;
            },
        ];
    }

    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */

            $event->sender->created_at = new \yii\db\Expression('now()');
        });

        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */

            $cr = \Yii::$app->user->getId();
            if (empty($event->sender->created_by) && !empty($cr)) {
                $event->sender->created_by = $cr;
            }

            if (empty($event->sender->status_uid)) {
                $event->sender->status_uid = 1;
            }

            $product = null;
            if (!empty($event->sender->product_uid)) {
                $product = Product::find()->andWhere(['id' => $event->sender->product_uid])->one();
            }
            
            $user = Yii::$app->user->getIdentity();
            if (!\Yii::$app->user->can('see-all-objects')) {
                if (!empty($product) && !empty($product->object->id)) {
                    $event->sender->object_uid = $product->object->id;
                } else {
                    $event->sender->object_uid = $user->object_uid;
                }
            } else {
                if ($event->sender->scenario != 'reserve' && empty($event->sender->object_uid)) {
                    $event->sender->object_uid = $user->object_uid;
                }
            }

            $generationParams = \Yii::$app->params['codeGeneration'];

            if (empty($event->sender->comment)) {
                $event->sender->comment = '';
            }
            if (empty($event->sender->prefix)) {
                $event->sender->prefix = $generationParams['prefix'];
            }

            if (empty($event->sender->capacity)) {
                $event->sender->capacity = $generationParams['capacity'];
            }
        });
    }
    
    /**
     * Возвращаем расширенный статус для заказов, которые были отправлены на оборудование
     * с ссылкой для приостановления/возобновления сессии на оборудовании
     *
     * @return array
     */
    public function getStatusExt()
    {
        $status = [];
        
        if (in_array($this->status_uid, [GenerationStatus::STATUS_CONFIRMED, GenerationStatus::STATUS_CONFIRMEDWOADDON, GenerationStatus::STATUS_CONFIRMEDREPORT])) {
            $user = \Yii::$app->user->getIdentity();
            $st = TqsSession::findAll(['generation_uid' => $this->id]);
            foreach ($st as $s) {
                $state = "Активно";
                if ($s->state == 1) {
                    $state = "Завершено";
                }
                if ($s->state == -1) {
                    $state = "Остановлено";
                }
                if ($s->state == -2) {
                    $state = "Отклонено";
                }
                
                $status[] = [
                    "session_id" => $s->id,
                    'state'      => $state,
                    'state_uid'  => $s->state,
                    "pause"      => \Yii::$app->urlManager->createAbsoluteUrl(['generations/tqs', 'id' => $this->id, 'tqs' => $s->id, 'action' => 'pause', 'access-token' => $user->getToken()]),
                    "run"        => \Yii::$app->urlManager->createAbsoluteUrl(['generations/tqs', 'id' => $this->id, 'tqs' => $s->id, 'action' => 'run', 'access-token' => $user->getToken()]),
                ];
            }
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Дата создания',
            'cnt' => 'Кол-во кодов',
            'codetype_uid' => 'Тип кода',
            'capacity' => 'Разрядность кода',
            'prefix' => 'Префикс',
            'status_uid' => 'Статус заказа',
            'created_by' => 'Кто создал',
            'deleted_at' => 'Дата удаления',
            'deleted_by' => 'Кто удалил',
            'comment' => 'Комментарий по заказу',
            'save_cnt' => 'Количество скачиваний',
            'is_rezerv' => 'Флаг резерва',
            'object_uid' => 'Объект',
            'parent_uid' => 'Ссылка на родительский заказ',
            'packint_status' => 'Статус упаковки',
            'third_party_order_id' => 'Идентификатор заказа в сторонних системах',
            'is_closed' => 'Завершено выполнение заказа'
        ];
    }

    public function insertInternal($attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        
        if (!empty($this->equip_uid)) {
            $trans = \Yii::$app->db->beginTransaction();
            $values["equip_uid"] = $this->equip_uid;
            $equip = Equip::findOne(['id' => $this->equip_uid]);
            if (!empty($equip)) {
                $m = $equip->createGeneration($values);
            }

            if (!empty($m)) {
                $this->id = $m->id;
            } else {
                throw new \yii\web\BadRequestHttpException('Ошибка создания заказа на оборудование: ' . $equip->login . $equip->fio . '(' . $equip->type . ')');
            }
            $this->refresh();
            $this->equip_uid = $equip->id;
            $this->save(false);
            $trans->commit();

            return true;
        }
        if (\Yii::$app instanceof \yii\web\Application) {
            $parent = \Yii::$app->request->getBodyParam('parent');
        } else {
            $parent = "";
        }
        if (!empty($parent)) {
            $cnti = intval(\Yii::$app->request->getBodyParam('cnti'));
            $cntg = intval(\Yii::$app->request->getBodyParam('cntg'));
            $cntp = intval(\Yii::$app->request->getBodyParam('cntp'));
            \Yii::$app->request->setBodyParams(['parent' => '']);
            $trans = \Yii::$app->db->beginTransaction();
            $parent_ocs = Ocs::find()->andWhere(new \yii\db\Expression("'" . pg_escape_string($parent) . "'=ANY(generations)"))->one();
            if (empty($parent_ocs)) {
                throw new \yii\web\BadRequestHttpException('Родительская генерация не найдена');
            }
            if (!empty($parent_ocs->parent_uid)) {
                throw new \yii\web\BadRequestHttpException('Нельзя дозаказывать к дозаказу');
            }
            $values["parent_uid"] = $parent_ocs->id;
            $values["object_uid"] = $parent_ocs->object_uid;
            $values["created_at"] = $parent_ocs->created_at;
            $values["created_by"] = $parent_ocs->created_by;
            $values["product_uid"] = $parent_ocs->product_uid;
            $values["equip_uid"] = $parent_ocs->equip_uid;
            $values["cnt"] = intval($cnti);
            $ocs = new Ocs();
            $ocs->load($values, '');
            $ocs->save(false);
            $ocs->refresh();
            
            $m = Ocs::createGenerations($ocs->id, $cnti, $cntg, $cntp);
            $this->id = $m->id;
            $this->refresh();
            $trans->commit();

            return true;
        }

        if (($primaryKeys = \Yii::$app->db->createCommand("SELECT * FROM save_generation(null,null,:CNT,:CODETYPE,:CAPACITY,:PREFIX,:STATUS,:CREATED_BY,null,null,:COMMENT,:PRODUCT,:OBJECT,:REZERV,null,:CNT ,0, :PARENT, :NUM, :PS, :EU, :DU)", [
                ":CNT"        => isset($values["cnt"]) ? $values["cnt"] : null,
                ":CODETYPE"   => isset($values["codetype_uid"]) ? $values["codetype_uid"] : null,
                ":STATUS"     => isset($values["status_uid"]) ? $values["status_uid"] : null,
                ":CAPACITY"   => isset($values["capacity"]) ? $values["capacity"] : null,
                ":PREFIX"     => isset($values["prefix"]) ? $values["prefix"] : null,
                ":CREATED_BY" => isset($values["created_by"]) ? $values["created_by"] : null,
                ":COMMENT"    => isset($values["comment"]) ? $values["comment"] : '',
                ":PRODUCT"    => isset($values["product_uid"]) ? $values["product_uid"] : null,
                ":OBJECT"     => isset($values["object_uid"]) ? $values["object_uid"] : null,
                ":REZERV"     => isset($values["is_rezerv"]) ? $values["is_rezerv"] : false,
                ":PARENT"     => isset($values["parent_uid"]) ? $values["parent_uid"] : null,
                ":NUM"        => isset($values["num"]) ? $values["num"] : null,
                ":PS"         => isset($values["packing_status"]) ? $values["packing_status"] : null,
                ":EU"         => isset($values["equip_uid"]) ? $values["equip_uid"] : null,
                ":DU"         => isset($values["data_uid"]) ? $values["data_uid"] : null,
            ])->queryOne()) === false) {
            return false;
        }

        foreach ($primaryKeys as $name => $value) {
            $id = static::getTableSchema()->columns[$name]->phpTypecast($value);
            $this->setAttribute($name, $id);
            $values[$name] = $id;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodes()
    {
//        return $this->hasMany(Code::class, ['generation_uid' => 'id']);
        return Code::findAllByGen($this);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEquip()
    {
        return $this->hasOne(Equip::class, ['id' => 'equip_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodeType()
    {
        return $this->hasOne(CodeType::class, ['id' => 'codetype_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(GenerationStatus::class, ['id' => 'status_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
    
    public function getRecipe()
    {
        return $this->product->recipe ?? Recipe::findOne(['type_def' => true]) ?? null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeletedBy()
    {
        return $this->hasOne(User::class, ['id' => 'deleted_by']);
    }

    public function getFileName()
    {
        $fileNameMask = "{codePath}/{codetype_uid}-{id}{-description}{(seria)}{-createdAt}.csv";

        $keys = [
            '{codePath}'     => \Yii::getAlias('@codePath', false),
            '{codetype_uid}' => $this->codetype_uid,
            '{id}'           => $this->object_uid . "-" . $this->num,
            '{-description}' => '',
            '{(seria)}'      => '',
            '{-createdAt}'   => '',
        ];

//        if ($this->codetype_uid == CodeType::CODE_TYPE_GROUP) {
//            $keys['{-createdAt}'] = '-' . \Yii::$app->formatter->asDatetime($this->created_at, 'yyyyMMdd-HHmmss');
//        }

        if ($this->codetype_uid == CodeType::CODE_TYPE_INDIVIDUAL) {
            $keys['{-description}'] = '-' . @$this->product->nomenclature->description;
            $keys['{-description}'] = str_replace([' ', '/', '\\'], '_', $keys['{-description}']);

            $keys['{(seria)}'] = '(' . @$this->product->series . ')';
            $keys['{(seria)}'] = str_replace([' ', '/', '\\'], '_', $keys['{(seria)}']);
        } elseif ($this->codetype_uid == CodeType::CODE_TYPE_GROUP) {
        }

        $fileName = str_replace(array_keys($keys), array_values($keys), $fileNameMask);
        $fileName2 = $fileName . '.gz';
        $fileName3 = str_replace('.csv.gz', '(' . $this->cnt . ').csv.gz', $fileName2);

        $fName = $fileName;
        if (file_exists($fileName2)) {
            $fName = $fileName2;
        } elseif (file_exists($fileName3)) {
            $fName = $fileName3;
        }

        return $fName;
    }

    public function getFileNameOCS()
    {
        $fileNameMask = "{codePath}/OCS-{codetype_uid}-{id}{-description}{(seria)}{-createdAt}.csv";

        $keys = [
            '{codePath}'     => \Yii::getAlias('@codePath', false),
            '{codetype_uid}' => $this->codetype_uid,
            '{id}'           => $this->object_uid . "-" . $this->num,
            '{-description}' => '',
            '{(seria)}'      => '',
            '{-createdAt}'   => '',
        ];

//        if ($this->codetype_uid == CodeType::CODE_TYPE_GROUP) {
//            $keys['{-createdAt}'] = '-' . \Yii::$app->formatter->asDatetime($this->created_at, 'yyyyMMdd-HHmmss');
//        }

        if ($this->codetype_uid == CodeType::CODE_TYPE_INDIVIDUAL) {
            $keys['{-description}'] = '-' . @$this->product->nomenclature->description;
            $keys['{-description}'] = str_replace([' ', '/', '\\'], '_', $keys['{-description}']);

            $keys['{(seria)}'] = '(' . @$this->product->series . ')';
            $keys['{(seria)}'] = str_replace([' ', '/', '\\'], '_', $keys['{(seria)}']);
        } elseif ($this->codetype_uid == CodeType::CODE_TYPE_GROUP) {
        }

        $fileName = str_replace(array_keys($keys), array_values($keys), $fileNameMask);
        $fileName2 = $fileName . '.gz';
        $fileName3 = str_replace('.csv.gz', '(' . $this->cnt . ').csv.gz', $fileName2);

        $fName = $fileName;
        if (file_exists($fileName2)) {
            $fName = $fileName2;
        } elseif (file_exists($fileName3)) {
            $fName = $fileName3;
        }

        return $fName;
    }

    /**
     * Возвращает
     * @return string
     */
    public function getPalletCodesFilePath(): string
    {
        $runtime = \Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR;
        $palletCodesDir = \Yii::$app->params['santensFtpConfig']['palletCodesDir'];
        $fileName = 'codes-' . $this->third_party_order_id . '.csv';
        return $runtime . $palletCodesDir . DIRECTORY_SEPARATOR . $fileName;
    }
    
    public function save($runValidation = true, $attributeNames = null)
    {
        if (SERVER_RULE == SERVER_RULE_SKLAD && !$this->isNewRecord) {
            return \Yii::$app->db->createCommand("SELECT save_generation(:ID,:C1,:C,:C2,:C12,:C13,:C3,:C4,:C5,:C6,:C7,:C8,:C9,:C10,:C11,:C14,:C15, :C16, :C17, :C18, :C19, :C20)", [
                ':ID'  => $this->id,
                ':C1'  => $this->created_at,
                ':C'   => $this->cnt,
                ':C2'  => $this->codetype_uid,
                ':C12' => $this->capacity,
                ':C13' => $this->prefix,
                ':C3'  => $this->status_uid,
                ':C4'  => $this->created_by,
                ':C5'  => $this->deleted_at,
                ':C6'  => $this->deleted_by,
                ':C7'  => $this->comment,
                ':C8'  => $this->product_uid,
                ':C9'  => $this->object_uid,
                ':C10' => $this->is_rezerv,
                ':C11' => $this->originalid,
                ':C14' => $this->cnt_src,
                ':C15' => $this->save_cnt,
                ':C17' => $this->num,
                ':C16' => $this->parent_uid,
                ':C18' => $this->packing_status,
                ':C19' => $this->equip_uid,
                ':C20' => $this->data_uid,
            ])->queryScalar();
        } else {
            return parent::save($runValidation, $attributeNames);
        }
    }
}
