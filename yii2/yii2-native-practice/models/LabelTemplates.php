<?php

namespace app\modules\itrack\models;

use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "label_templates".
 *
 * @property int      $id
 * @property string   $name
 * @property string   $typeof
 * @property int      $object_uid
 * @property string   $filename
 * @property string   $created_at
 * @property int      $created_by
 * @property string   $deleted_at
 * @property int      $deleted_by
 * @property string   $tempdata
 *
 * @property Facility $object
 */
class LabelTemplates extends \yii\db\ActiveRecord
{
    public static $types = [
        '1' => 'Индивидуальный',
        '2' => 'На бандероль',
        '3' => 'На гофрокороб',
        '4' => 'На паллету',
    ];
    static $auditOperation = AuditOperation::OP_DEFAULT;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'label_templates';
    }
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    public static function find()
    {
        return parent::find()->andWhere('label_templates.deleted_at is null');
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $this->created_at = 'NOW()';
            $this->tempdata = base64_encode($this->tempdata);
        });
        $this->on(self::EVENT_AFTER_FIND, function ($event) {
            /** @var $event ModelEvent */
            $this->tempdata = base64_decode($this->tempdata);
        });
        $this->on(self::EVENT_BEFORE_UPDATE, function ($event) {
            /** @var $event ModelEvent */
            unset($event->sender->object_uid);
            if (in_array('tempdata', $this->dirtyAttributes)) {
                $this->tempdata = base64_encode($this->tempdata);
            }
        });
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            $event->sender->tempdata = '';
            if (\Yii::$app->request->isPut) {
                function parse_raw_http_request(array &$a_data)
                {
                    // read incoming data
                    $input = file_get_contents('php://input');
                    
                    // grab multipart boundary from content type header
                    preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
                    $boundary = $matches[1];
                    
                    // split content by boundary and get rid of last -- element
                    $a_blocks = preg_split("/-+$boundary/", $input);
                    array_pop($a_blocks);
                    
                    // loop data blocks
                    foreach ($a_blocks as $id => $block) {
                        if (empty($block)) {
                            continue;
                        }
                        
                        // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
                        // parse uploaded files
                        if (strpos($block, 'application/octet-stream') !== false) {
                            // match "name", then everything after "stream" (optional) except for prepending newlines
                            preg_match("/\bname=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                        } // parse all other fields
                        else {
                            // match "name" and optional value in between newline sequences
                            preg_match('/\bname=\"([^\"]*)\".*?[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                        }
                        $a_data[$matches[1]] = $matches[2];
                    }
                }
                
                $a_data = [];
                parse_raw_http_request($a_data);
                if (isset($a_data["file"])) {
                    $event->sender->tempdata = $a_data["file"];
                }
                if (isset($a_data["name"])) {
                    $event->sender->login = $a_data["name"];
                }
                if (isset($a_data["typeof"])) {
                    $event->sender->type = $a_data["typeof"];
                }
                if (isset($a_data["object_uid"])) {
                    $event->sender->object_uid = $a_data["object_uid"];
                }
                //при PUT имя файла не вытаскивается!
            }
            if (\Yii::$app->request->isPost) {
                $f = \yii\web\UploadedFile::getInstanceByName('file');
                if (!empty($f->tempName)) {
                    $event->sender->tempdata = file_get_contents($f->tempName);
                    $event->sender->filename = $f->name;
                }
                if (empty($this->object_uid)) {
                    $this->object_uid = \Yii::$app->user->identity->object_uid;
                }
            }
            if (empty($this->created_by)) {
                $this->created_by = \Yii::$app->user->getId();
            }
        });
    }
    
    public function delete()
    {
        $this->deleted_at = new Expression('NOW()');
        
        return $this->update(false, ['deleted_at']);
    }
    
    public function fields()
    {
        return array_merge(parent::fields(), [
            'typeof_uid' => function () {
                return array_search($this->typeof, self::$types);
            },
        ]);
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'typeof', 'object_uid', 'filename'], 'required'],
            [['name', 'typeof', 'filename', 'tempdata'], 'string'],
            [['name'], 'unique', 'message' => 'Шаблон с таким наименованием уже создан'],
            [['typeof'], 'in', 'range' => self::$types],
            [['object_uid', 'created_by', 'deleted_by'], 'default', 'value' => null],
            [['object_uid', 'created_by', 'deleted_by'], 'integer'],
            [['created_at', 'deleted_at'], 'safe'],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => Yii::t('app', 'ID'),
            'name'       => Yii::t('app', 'Наименование'),
            'typeof'     => Yii::t('app', 'Тип'),
            'object_uid' => Yii::t('app', 'Объект'),
            'filename'   => Yii::t('app', 'Файл загрузки'),
            'created_at' => Yii::t('app', 'Дата создания'),
            'created_by' => Yii::t('app', 'Кто создал'),
            'deleted_at' => Yii::t('app', 'Дата удаления'),
            'deleted_by' => Yii::t('app', 'Кто удалил'),
            'tempdata'   => Yii::t('app', 'Временные данные'),
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }
}
