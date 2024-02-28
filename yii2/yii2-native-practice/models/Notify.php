<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use app\modules\itrack\components\boxy\Logger;
use app\modules\itrack\components\pghelper;
use yii\db\Exception;

/**
 *
 * @property Generation[] $generations
 */
class Notify extends ActiveRecord
{
    
    static $states = [
        'error',
        'success',
    ];
    static $types  = [
        'DEFAULT'       => 'Все типы документов',
        '311'           => 'Окончательная упаковка',
        '312'           => 'Отбор образцов',
        '313'           => 'Выпуск готовой продукции',
        '381'           => 'Передача собственнику',
        '391'           => 'Возврат в оборот',
        '415'           => 'Отгрузка зарегистрированному контрагенту',
        '416'           => 'Приемка ЛП на склад получателя',
        '431'           => 'Перемещение',
        '441'           => 'Отгрузка незарегистрированному контрагенту',
        '541'           => 'Передача на уничтожение',
        '542'           => 'Регистрация уничтожения',
        '552'           => 'Вывод из оборота',
        '601'           => 'Уведомление об отгрузке ЛП (601)',
        '603'           => 'Уведомление об отгрузке ЛП (603)',
        '605'           => 'Уведомление об отзыве отправителем ЛП (605)',
        '606'           => 'Уведомление об отказе от приемки ЛП (606)',
        '609'           => 'Отгрузка в РФ со сменой собственника (609)',
        '613'           => 'Отгрузка в РФ (613)',
        '615'           => 'Отгрузка в РФ из стран ЕАЭС (615)',
        '617'           => 'Ошибки при приемке (617)',
        '621'           => 'Постановка в арбитраж (621)',
        '623'           => 'Корректировка сведений поставщиком (623)',
        '701'           => 'Приемка',
        '912'           => 'Разгруппировка',
        '913'           => 'Изъятие из группы',
        '914'           => 'Добавление в группу',
        '915'           => 'Агрегирование',
        'TQS'           => 'OCS оборудование',
        'check'         => 'Сервис отправки сообщений в МДЛП',
    ];
    static $macros = [
        "=operation="      => "Название операции",
        "=id="             => "Идентификатор документа",
        "=fnsid="          => "Тип документа",
        "=nomenclature="   => "Наименование номенклатуры",
        "=serie="          => "Серия",
        "=invoice_number=" => "Номер накладной",
        "=invoice_date="   => "Дата накладной",
        "=status="         => "Статус обработки документа",
        "=gtin="           => "GTIN",
        "=subject_id="     => "Идентификатор отправителя",
        "=subject_name="   => "Наименование отправителя",
        "=grp_count="      => "Кол-во групповых кодов",
        "=sgtin_count="    => "Кол-во индивидуальных кодов",
        "=method="         => "Метод обработки документов ФНС",
        "=connector_name=" => "Название соединения",
        "=server_name="    => "Название сервера",
    ];
    static $auditOperation = AuditOperation::OP_FNSNOTIFY;
    public $type;
    public $objects;
    public $subject;
    public $body;
    public $emails;
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'notify';
    }
    
    static function find()
    {
        $query = parent::find();
        $query->select("*,case when (lastdt+(ttl::text || ' sec')::interval)>timeofday()::timestamptz then 0 else 1 end as cansend");
        
        return $query;
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    static function getAll($type, $object, $state)
    {
        $query = self::find();
        if (empty($object)) {
            $query->andWhere(['typeof' => $type, "object_uid" => "{}", 'state' => $state]);
        } else {
            $query->andWhere(['typeof' => $type, 'state' => $state]);
            $query->andWhere(new \yii\db\Expression("(" . intval($object) . "=ANY(object_uid) or cardinality(object_uid)=0)"));
            $query->orderBy(new \yii\db\Expression('cardinality(object_uid) desc'));
        }
        
        return $query;
    }
    
    static function getNotify($type, $object, $state)
    {
        $docNotify = self::getAll('FNS-' . $type, $object, $state)->all();
        if ($type == 'TQS') {
            return $docNotify;
        }
        $defaultNotify = self::getAll('FNS-DEFAULT', $object, $state)->all();
        
        return array_merge($docNotify, $defaultNotify);
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    public function attributes()
    {
        return array_merge(parent::attributes(), [
            'cansend',
            'type',
            'objects',
            'subject',
            'body',
            'emails',
        ]);
    }
    
    public function attributeLabels()
    {
        return [
            'id'         => 'ИД',
            'typeof'     => 'Тип рассылки',
            'email'      => 'Списк получателей',
            'lastdt'     => 'Дата последней отправки оповещения',
            'params'     => 'Параметры отовещения',
            'ttl'        => 'Частота отправки в сек',
            'object_uid' => 'Объект',
        ];
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->typeof = 'FNS-' . $event->sender->type;
            if (is_array($event->sender->emails))
                $event->sender->email = implode(',', $event->sender->emails);
            $param = [$event->sender->subject, $event->sender->body];
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($param);
            $event->sender->object_uid = \app\modules\itrack\components\pghelper::arr2pgarr($event->sender->objects);
            $event->sender->ttl = 0;
        });
        $this->on(self::EVENT_BEFORE_UPDATE, function ($event) {
            $event->sender->typeof = 'FNS-' . $event->sender->type;
            if(is_array($event->sender->emails))
                $event->sender->email = implode(',', $event->sender->emails);
            $param = [$event->sender->subject, $event->sender->body];
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($param);
            $event->sender->object_uid = \app\modules\itrack\components\pghelper::arr2pgarr($event->sender->objects);
        });
    }
    
    public function fields()
    {
        return [
            'uid'     => 'id',
            'cansend',
            'state',
            'type'    => function () {
                $a = explode("-", $this->typeof, 2);
                
                return $a[1];
            },
            'objects' => function () {
                return Facility::find()->andWhere(['in', 'id', \app\modules\itrack\components\pghelper::pgarr2arr($this->object_uid)])->all();
            },
            'subject' => function () {
                $arr = (\app\modules\itrack\components\pghelper::pgarr2arr($this->params));
                
                return $arr[0];
            },
            'body'    => function () {
                $arr = (\app\modules\itrack\components\pghelper::pgarr2arr($this->params));
                
                return $arr[1];
            },
            'emails'  => function () {
                return explode(",", $this->email);
            },
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['subject', 'body', 'state', 'lastdt'], 'string'],
            ['type', 'in', 'range' => array_keys(self::$types)],
            [['objects', 'emails'],
                function ($attribute, $params) {
                    if (!is_array($this->$attribute)) {
                        $this->addError($attribute, $attribute . ' не является массивом');
                    }
                }],
            ['emails', 'each', 'rule' => ['email']],
            ['objects', 'default', 'value' => []],
            ['state', 'default', 'value' => 'error'],
            ['state', 'in', 'range' => self::$states],
            ['objects', 'each', 'rule' => ['exist', 'targetClass' => Facility::class, 'targetAttribute' => 'id']],
            [['type', 'subject', 'body', 'emails'], 'required'],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $query->andWhere(['like', 'typeof', 'FNS-']);
        
        if (isset($params["object_uid"]) && !empty($params["object_uid"])) {
            $query->andWhere(new \yii\db\Expression (intval($params["object_uid"]) . ' = ANY(object_uid)  or cardinality(object_uid) = 0'));
        }
        if (isset($params["type"]) && !empty($params["type"])) {
            $query->andWhere(new \yii\db\Expression("typeof='FNS-DEFAULT' or typeof='FNS-" . pg_escape_string($params["type"]) . "'"));
        }
        if (isset($params["state"]) && !empty($params["state"])) {
            $query->andWhere(['state' => $params['state']]);
        }
        
        $query->orderBy(new \yii\db\Expression('typeof,cardinality(object_uid) desc'));
        
        $dataProvider = new \yii\data\ActiveDataProvider(['query' => $query,]);
        
        return $dataProvider;
    }

    /**
     * @param $to
     * @param $subject
     * @param $message
     * @param null $attach
     */
    public function send($to, $subject, $message, $attach = null)
    {
        if (!$this->cansend) {
            return;
        }

        $mail = $this->composeEmail($to, $subject, $message);

        if (!empty($attach) && is_array($attach)) {
            foreach ($attach as $fileName => $f) {
                $mail->attachContent($f, ['fileName' => $fileName]);
            }
        }

        try {
            if ($mail->send()) {
                \Yii::getLogger()->log('Отправлено: ' . $message, Logger::LEVEL_INFO, 'mail');
                $this->saveToLog($to, $message, true);

                $this->lastdt = new \yii\db\Expression('now()');
                $this->save(false, ['lastdt']);
            } else {
                \Yii::getLogger()->log(
                        'Ошибка отправки, сохраняем в лог для повторной: ' . $message,
                        Logger::LEVEL_WARNING,
                        'mail'
                );
                $this->saveToLog($to, $message, false);
            }
        } catch (\Exception $ex) {
            $this->saveToLog($to, $message, false);
        }
    }

    /**
     * @param string $message
     */
    public function sendFromLog(string $message)
    {
        if (!$this->cansend) {
            return;
        }

        $to = explode(",", $this->email);

        if (!$to)
            return;

        $params = pghelper::pgarr2arr($this->params);
        $subject = $params[0];

        $mail = $this->composeEmail($to, $subject, $message);

        if ($mail->send()) {
            try {
                \Yii::$app->db->createCommand('
                    UPDATE notify_log SET sent = true 
                    WHERE notify_uid = :id AND sent = false', [
                    ':id' => $this->id ?? null
                    ])
                    ->execute();

                $this->lastdt = new \yii\db\Expression('now()');
                $this->save(false, ['lastdt']);
            } catch (Exception $e) {
            }
        } else {
            \Yii::getLogger()->log('Ошибка повторной отправки: ' . $message, Logger::LEVEL_WARNING, 'mail');
        }
    }

    /**
     * @param array $to
     * @param string $message
     * @param bool $isSent
     */
    public function saveToLog(array $to, string $message, bool $isSent)
    {
        try {
            \Yii::$app->db->createCommand('
                INSERT INTO notify_log (notify_uid,email,message,sent) 
                VALUES (:id,:email,:message,:sent)', [
                    ':id' => $this->id ?? null,
                    ':email' => implode(",", $to),
                    ':message' => $message,
                    ':sent' => $isSent
                ])
                ->execute();
        } catch (Exception $e) {
        }
    }

    /**
     * @param $to
     * @param $subject
     * @param $message
     * @return \yii\mail\MessageInterface
     */
    private function composeEmail($to, $subject, $message)
    {
        return \Yii::$app->mailer->compose()
            ->setTextBody($message)
            ->setTo($to)
            ->setFrom(\Yii::$app->params["adminEmail"] ?? "support@i-track.ru")
            ->setSubject($subject);
    }

    /**
     * @param int $id
     * @return Notify|null
     */
    public static function findById(int $id)
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * @param string $type
     * @return Notify|null
     */
    public static function findByType(string $type)
    {
        return self::findOne(['typeof' => $type]);
    }

    /**
     * @param string $interval Интервал устаревания сообщения
     * @return array
     * @throws Exception
     */
    public static function findUnsentLogs(string $interval)
    {
        return \Yii::$app->db->createCommand('
                SELECT * FROM notify_log 
                WHERE cdate > now() - interval \'' . $interval . '\' AND sent = false')
            ->queryAll();
    }
}
