<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\models\sklad\models\cache\Invoices;
use app\modules\itrack\components\boxy\ActiveRecord;
use yii\db\ActiveQuery;
use yii\web\IdentityInterface;
use yii\web\MethodNotAllowedHttpException;

/**
 * @OA\Schema(schema="app_modules_itrack_models_Facility",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="string", example="1"),
 *          @OA\Property(property="name", type="string", example="Тестовый объект_1"),
 *          @OA\Property(property="address", type="string", example="Тестовая улица, 3/4"),
 *          @OA\Property(property="fns_subject_id", type="string", example="00000000000268"),
 *          @OA\Property(property="fns_auto", type="boolean", example=false),
 *          @OA\Property(property="gs1", type="string", example="1010101015"),
 *          @OA\Property(property="inn", type="string", example=null),
 *          @OA\Property(property="parent_uid", type="integer", example=2),
 *          @OA\Property(property="grp", type="boolean", example=false),
 *          @OA\Property(property="code1c", type="string", example=null),
 *          @OA\Property(property="visibility", type="array", @OA\Items(type="integer")),
 *          @OA\Property(property="guid", type="string", example=null),
 *          @OA\Property(property="suz_enable", type="boolean", example=false),
 *          @OA\Property(property="suz_report", type="boolean", example=false),
 *          @OA\Property(property="external", type="boolean", example=false),
 *          @OA\Property(property="flags", type="integer", example=0),
 *          @OA\Property(property="suz_uid", type="integer", example=null),
 *      }
 * )
 */

/**
 * Class Object
 *  Сущность объект - территориальное сооружение, к которому могут быть привязаны пользователи
 *  служит для группировки пользотваелей, для территориальной разметки действий попродукции
 * Методы:
 *  - список, просмотр, создание, удаление
 *  - список пользовталей привязанных к данному объекту
 *
 * @property integer          $id          - Идентифкатор объекта
 * @property string           $name        - Наименование объекта
 * @property string           $address     - Адрес объекта
 * @property bool             $is_admin
 * @property bool             $has_server
 * @property string|null      $token
 * @property string           $gs1
 * @property bool             $external
 * @property string|null      $fns_subject_id
 * @property bool             $fns_auto
 * @property string|null      $inn
 * @property string|null      $kpp
 * @property string|null      $foreign_name
 * @property string|null      $foreign_address
 * @property bool             $is_foreign
 * @property int|null         $uso_uid
 * @property int|null         $uso_uid_out - Коннектор для исходящих документов
 * @property int              $flags       - побитовая маска типа объекта
 * @property string|null      $server_name
 * @property int|null         $parent_uid
 * @property string|null      $code1c
 * @property int              $visibility
 * @property string|null      $guid
 * @property bool             $suz_enable
 * @property bool             $suz_report
 * @property int|null         $suz_uid
 *
 * @property Extdata[]        $extdatas
 * @property Invoices[]       $invoices
 * @property LabelTemplates[] $labelTemplates
 * @property Nomenclature[]   $nomenclatures
 * @property Ocs[]            $ocs
 * @property Product[]        $products
 * @property Recipe[]         $recipes
 * @property Sicpa[]          $sicpas
 * @property User[]           $users
 * @property UsoCache[]       $usoCaches
 * @property UsoConnectors    $inConnector
 * @property UsoConnectors    $outConnector
 */
class Facility extends ActiveRecord implements IdentityInterface
{
    static $auditOperation = AuditOperation::OP_OBJECT;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'objects';
    }
    
    public static function find()
    {
        $q = parent::find()
            ->andWhere(["external" => false]);
        
        if (\Yii::$app->request instanceof \yii\web\Request) {
            $full = \Yii::$app->request->getQueryParam('full') ?? '';
            if ($full != 'true' && \Yii::$app->user->identityClass == User::class) {
                $identity = \Yii::$app->user->getIdentity();
                if (!empty($identity)) {
                    $vis = $identity->object->visibility ?? [];
                    array_push($vis, $identity->object_uid);
                    
                    if (count($vis) && !\Yii::$app->user->can('see-all-objects')) {
                        $q->andWhere(['objects.id' => $vis]);
                    }
                }
            }
        }
        
        return $q->select(new \yii\db\Expression("*, exists(select * from objects as a where a.parent_uid = objects.id) as grp"));
    }
    
    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }
    
    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['token' => $token]);
    }
    
    /**
     * Поиск старшего объекта в иерархии  (!!без учета видимости)
     *
     * @param type $object_uid
     *
     * @return type
     */
    public static function findParent($object_uid)
    {
        do {
            $obj = self::find()->where(['id' => $object_uid])->one();
            $object_uid = $obj->parent_uid;
        } while (!empty($object_uid));
        
        return $obj->id;
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
            [['name'], 'required'],
            [['name'], 'unique', 'message' => 'Объект с таким наименованием уже создан'],
            [['name', 'address', 'token', 'gs1', 'fns_subject_id', 'inn', 'code1c', 'guid'], 'string'],
            [['parent_uid', 'flags'], 'integer'],
            [['visibility'], 'each', 'rule' => ['integer']],
            [['is_admin', 'has_server', 'external', 'fns_auto', 'suz_enable', 'suz_report'], 'boolean'],
            ['uso_uid_out', 'exist', 'targetClass' => UsoConnectors::class, 'targetAttribute' => 'id'],
            ['uso_uid', 'exist', 'targetClass' => UsoConnectors::class, 'targetAttribute' => 'id'],
            ['suz_uid', 'exist', 'targetClass' => SuzConnectors::class, 'targetAttribute' => 'id'],
        ];
    }
    
    public function attributes()
    {
        return array_merge(parent::attributes(), ['grp']);
    }
    
    public function fields()
    {
        return [
            'uid' => 'id',
            'name',
            'address',
            'fns_subject_id',
            'fns_auto',
            'gs1',
            'inn',
            'parent_uid',
            'grp',
            'code1c',
            'visibility',
            'guid',
            'suz_enable',
            'suz_report',
            'external',
            'flags',
            'suz_uid',
            'uso_uid',
            'uso_uid_out',
        ];
    }
    
    public function extraFields()
    {
        return [
            'users',
            'canDelete',
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'name'            => 'Название',
            'address'         => 'Адрес',
            'is_admin'        => 'Флаг админобъекта',
            'has_server'      => 'Есть внешний сервер',
            'token'           => 'Токен для миграции',
            'gs1'             => 'GS1',
            'external'        => 'Флаг стороннего',
            'fns_subject_id'  => 'МДЛП идентификатор',
            'fns_auto'        => 'Автоотправка документов',
            'inn'             => 'ИНН',
            'kpp'             => 'КПП',
            'foreign_name'    => 'Наименование внешнего объекта',
            'foreign_address' => 'Адрес внешнего объекта',
            'is_foreign'      => 'Внешний',
            'uso_uid'         => 'Идентификатор УСО для входящих',
            'uso_uid_out'     => 'Идентификатор УСО для исходящих',
            'flags'           => 'Флаги объекта',
            'server_name'     => 'Название внешнего сервера',
            'parent_uid'      => 'Родитель объекта',
            'code1c'          => 'Код 1С',
            'visibility'      => 'Видимость объектов',
        ];
    }
    
    public function afterFind()
    {
        parent::afterFind();
        try {
            $this->visibility = \app\modules\itrack\components\pghelper::pgarr2arr($this->visibility);
        } catch (\Exception $ex) {
        }
    }
    
    /**
     * проверка флага обеъкта является ли он складом ответственного хранения
     */
    public function isOTH()
    {
        return \Yii::$app->db->createCommand("SELECT object_flag('SAFE STOCK',:flag)", [":flag" => $this->flags])->queryScalar();
    }
    
    public function beforeSave($insert)
    {
        if (is_array($this->visibility)) {
            $this->visibility = \app\modules\itrack\components\pghelper::arr2pgarr($this->visibility);
        }
        
        return parent::beforeSave($insert);
    }
    
    public function afterSave($insert, $changedAttributes)
    {
        try {
            $this->visibility = \app\modules\itrack\components\pghelper::pgarr2arr($this->visibility);
        } catch (\Exception $ex) {
        }
        
        return parent::afterSave($insert, $changedAttributes);
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            if ($event->sender->id == $event->sender->parent_uid && !empty($event->sender->parent_uid)) {
                $this->addError('parent_uid', 'Ссылка на родительский элемент не может ссылаться на самого себя');
                
                return false;
            }
        });
    }
    
    /**
     * Проверка зарегистрирован ли в МДЛП лекарственный препараст с заданным GTIN
     *
     * @param type $gtin
     *
     * @return bool статус проверки
     */
    public function checkGtinMdlp($gtin)
    {
        $ism = new \app\modules\itrack\components\ISMarkirovka($this->uso_uid);
        $response = $ism->getPublicGtinInfo($gtin);
        if (!is_array($response) || $response["gtin"] != $gtin || !in_array($response["reg_status"], ["Действующий", "Измененный"])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @return ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::class, ['object_uid' => 'id']);
    }
    
    public function delete()
    {
        if (true == $this->canDelete) {
            Facility::updateAll(['parent_uid' => null], ['parent_uid' => $this->id]);
            
            return parent::delete();
        }
        throw new MethodNotAllowedHttpException("Can't delete object");
    }
    
    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Not Allowed
     */
    public function getAuthKey()
    {
        throw new MethodNotAllowedHttpException;
    }
    
    /**
     * Not Allowed
     */
    public function validateAuthKey($authKey)
    {
        throw new MethodNotAllowedHttpException;
    }
    
    public function getCanDelete()
    {
//    (SELECT 1 as c FROM codes WHERE object_uid = :oId LIMIT 1)
//        UNION
        if (SERVER_RULE == SERVER_RULE_RF) {
            $sql = 'SELECT SUM(c) FROM (
    (SELECT 1 as c FROM generations WHERE object_uid = :oId LIMIT 1)
    UNION
    (SELECT 1 as c FROM product WHERE object_uid = :oId LIMIT 1)
    UNION
    (SELECT 1 as c FROM users WHERE object_uid = :oId LIMIT 1)
) t';
            $e = static::getDb()->createCommand($sql, [':oId' => $this->id])->queryScalar();
            if ($e == 0) {
                return true;
            }
        }
        
        return false;
        // generations
        // codes
        // manufacture
        // product
    }
    
    /**
     * Gets query for [[Extdatas]].
     *
     * @return ActiveQuery
     */
    public function getExtdatas()
    {
        return $this->hasMany(Extdata::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Invoices]].
     *
     * @return ActiveQuery
     */
    public function getInvoices()
    {
        return $this->hasMany(Invoices::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[LabelTemplates]].
     *
     * @return ActiveQuery
     */
    public function getLabelTemplates()
    {
        return $this->hasMany(LabelTemplates::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Nomenclatures]].
     *
     * @return ActiveQuery
     */
    public function getNomenclatures()
    {
        return $this->hasMany(Nomenclature::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Ocs]].
     *
     * @return ActiveQuery
     */
    public function getOcs()
    {
        return $this->hasMany(Ocs::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Products]].
     *
     * @return ActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Recipes]].
     *
     * @return ActiveQuery
     */
    public function getRecipes()
    {
        return $this->hasMany(Recipe::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[Sicpas]].
     *
     * @return ActiveQuery
     */
    public function getSicpas()
    {
        return $this->hasMany(Sicpa::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[UsoCaches]].
     *
     * @return ActiveQuery
     */
    public function getUsoCaches()
    {
        return $this->hasMany(UsoCache::class, ['object_uid' => 'id']);
    }
    
    /**
     * Gets query for [[UsoU]].
     *
     * @return ActiveQuery
     */
    public function getInConnector()
    {
        return $this->hasOne(UsoConnectors::class, ['id' => 'uso_uid']);
    }
    
    /**
     * Gets query for [[UsoUidOut]].
     *
     * @return ActiveQuery
     */
    public function getOutConnector()
    {
        return $this->hasOne(UsoConnectors::class, ['id' => 'uso_uid_out']);
    }
    
    /**
     * @return int
     */
    public function getInConnectorId()
    {
        return $this->inConnector ? $this->inConnector->id : null;
    }
    
    /**
     * @return int
     */
    public function getOutConnectorId()
    {
        return $this->outConnector ? $this->outConnector->id : null;
    }

    /**
     * @param string $fnsSubjectId
     * @return Facility
     * @throws \ErrorException
     */
    public static function findByFnsSubjectId(string $fnsSubjectId): Facility
    {
        $facility = self::findOne(['fns_subject_id' => $fnsSubjectId]);

        if ($facility === null) {
            throw new \ErrorException(\Yii::t('app', 'Объект не найден!'));
        }

        return $facility;
    }
}
