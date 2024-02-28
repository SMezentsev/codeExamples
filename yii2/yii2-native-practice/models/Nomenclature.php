<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use Yii;
use yii\base\ModelEvent;
use yii\db\Expression;

/**
 * Class Nomenclature
 *      Справочник выпускаемых номенклатур, используется для создания торваных карточек,
 *      несет в себе добавчную информацию по нонклатурам , ЕАН13, период годности и тп...
 *
 * @property integer      $id            - Идентификатор номенклатуры
 * @property string       $name          - Наименование номенклатуры
 * @property string       $ean13         - Штрих код
 * @property string       $created_at    - Дата создания
 * @property integer      $created_by    - Ссылка на создавшего
 * @property string       $deleted_at    - Дата удаления
 * @property integer      $deleted_by    - ССылка на удалившего
 * @property string       $description   - Описательная информация по номеклатуре (текст,html, с картинами или инструкции и тп)
 * @property string       $ru
 * @property string       $info          — Информация по производителю
 * @property integer      $cnt           — Количество в гофрокоробе
 * @property integer      $expmonth      — Дефолтный срок годности в месяцах
 * @property string       $gtin          — GTIN
 * @property string       $code1c        — Код продукта
 * @property string       $tnved         — ТНВЭД
 * @property integer      $sngroup_uid   — SN Code Group
 * @property integer      $object_uid
 * @property integer      $manufacturer_uid
 * @property integer      $band_in_korob_cnt
 * @property string       $number_of_layers
 * @property string       $codes_on_layer
 * @property string       $cnt_on_pallet
 * @property bool         $hasl3
 * @property bool         $is_payment
 *
 * @property User         $createdBy
 * @property User         $deletedBy
 * @property Product[]    $products
 * @property Manufacturer $manufacturer
 * @property bool         $canDelete
 * @property Recipe       $recipe
 *
 *  Методы:
 *  - список, просмотр, создание, удаление
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Nomenclature",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="string", example="1"),
 *          @OA\Property(property="name", type="string", example="22222"),
 *          @OA\Property(property="ean13", type="number", example=null),
 *          @OA\Property(property="created_by", type="integer", example="2589"),
 *          @OA\Property(property="created_at", type="string", example="2020-02-25 15:22:11+0300"),
 *          @OA\Property(property="description", type="string", example=""),
 *          @OA\Property(property="ru", type="string", example=""),
 *          @OA\Property(property="hasl3", type="boolean", example=false),
 *          @OA\Property(property="object_uid", type="string", example="2"),
 *          @OA\Property(property="info", type="string", example=""),
 *          @OA\Property(property="cnt", type="integer", example=null),
 *          @OA\Property(property="expmonth", type="integer", example=null),
 *          @OA\Property(property="gtin", type="number", example="22222222222226"),
 *          @OA\Property(property="manufacturer_uid", type="integer", example="132"),
 *          @OA\Property(property="manufacturer_name", type="string", example=null),
 *          @OA\Property(property="code1c", type="string", example="1231"),
 *          @OA\Property(property="tnved", type="string", example=""),
 *          @OA\Property(property="fns_order_type", type="integer", example=2),
 *          @OA\Property(property="fns_owner_id", type="string", example=null),
 *          @OA\Property(property="number_of_layers", type="string", example=null),
 *          @OA\Property(property="codes_on_layer", type="string", example=null),
 *          @OA\Property(property="cnt_on_pallet", type="integer", example="1111"),
 *          @OA\Property(property="band_in_korob_cnt", type="number", example="0"),
 *          @OA\Property(property="recipe_uid", type="string", example=null),
 *      }
 * )
 */
class Nomenclature extends ActiveRecord
{
    static $auditOperation = AuditOperation::OP_NOMENCLATURE;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'nomenclature';
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public static function find()
    {
        $query = parent::find();
        $query->andWhere('nomenclature.deleted_at is null');
        
        return $query;
    }
    
    /**
     * @return array
     */
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    public function init()
    {
        parent::init();
        
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->created_at = 'NOW()';
            if (!\Yii::$app->user->can('WEB.recipe.nomenclature.recipe_uid')) {
                $this->recipe_uid = null;
            }
        });
        
        $this->on(self::EVENT_BEFORE_UPDATE, function ($event) {
            /** @var $event ModelEvent */
            unset($event->sender->object_uid);
        });
        
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            $user = Yii::$app->user->getIdentity();
            
            if (!\Yii::$app->user->can('see-all-objects') || empty($event->sender->object_uid)) {
                $event->sender->object_uid = $user->object_uid;
            }
            /*
                if (isset(\Yii::$app->params["NomenclatureOnObject"]) && \Yii::$app->params["NomenclatureOnObject"] == false)
                    $event->sender->object_uid = new Expression ('null');
            */
            if (intval($this->ean13) == 0 && Constant::get('DisableCheckEan13') != 'true') {
                $this->addError('ean13', 'Неверный штрих-код');
                
                return false;
            }

            if (!$this->validateEan13($this->ean13) && Constant::get('DisableCheckEan13') != 'true') {
                $this->addError('ean13', 'Неверный штрих-код');
                
                return false;
            }
            
            if (empty($this->gtin)) {
                $this->gtin = str_pad($this->ean13, 14, '0', STR_PAD_LEFT);
            }
        });
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [
            [['name', 'created_by', 'code1c'], 'required'],
            ['manufacturer_uid', 'required', 'message' => 'Необходимо указать производителя'],
            [['name', 'description', 'ru', 'info', 'code1c', 'fns_owner_id', 'number_of_layers', 'codes_on_layer'], 'string'],
            [['name'], 'unique', 'message' => 'Номеклатура с таким названием уже создана'],
            [['tnved'], 'string', 'length' => Constant::get('extendedTNVED') == 'true' ? [4, 24] : [4, 4]],
            [['tnved'], 'match', 'pattern' => Constant::get('extendedTNVED') == 'true' ? '#^\d{4,24}$#' : '#^\d{4}$#'],
            [['code1c'],
                'unique',
                'message' => 'Номенклатура с данным Кодом продукта уже создана',
                'when'    => function ($model) {
                    return $model->isAttributeChanged('code1c');
                }],
            ['description', 'string', 'max' => 50],
            ['cnt', 'integer', 'max' => 1500],
            [['ean13', 'gtin', 'band_in_korob_cnt'], 'number'],
            [['ean13'],
                'unique',
                'message' => 'Номенклатура с данным штрих кодом уже создана',
                'when'    => function ($model) {
                    return $model->isAttributeChanged('ean13');
                }],
            [['created_at', 'deleted_at'], 'safe'],
            [['created_by', 'deleted_by', 'cnt', 'expmonth', 'manufacturer_uid', 'fns_order_type', 'cnt_on_pallet'], 'integer'],
            [['fns_order_type'], 'in', 'range' => [1, 2]],
            [['fns_order_type'], 'default', 'value' => 2],
            [['hasl3', 'is_payment'], 'boolean'],
            [['hasl3', 'is_payment'], 'default', 'value' => false],
            [['ean13', 'created_by', 'name', 'cnt', 'gtin', 'manufacturer_uid', 'code1c', 'tnved', 'fns_order_type', 'fns_owner_id',], 'required', 'on' => 'odinStask', 'message' => '{attribute} является обязательным'],
            [['manufacturer_uid'], 'exist', 'skipOnError' => false, 'targetClass' => Manufacturer::class, 'targetAttribute' => ['manufacturer_uid' => 'id']],
            [['recipe_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Recipe::class, 'targetAttribute' => ['recipe_uid' => 'id']],
            [['gtin'],
                'unique',
                'message' => 'Номенклатура с данным GTIN продукта уже создана',
                'when'    => function ($model) {
                    return $model->isAttributeChanged('gtin');
                }],
//            ['canDelete', 'safe'],
        ];

//        if(!isset(\Yii::$app->params["NomenclatureOnObject"]) || \Yii::$app->params["NomenclatureOnObject"]!=false)
//        {
        array_push($rules,
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => FacilitySort::class, 'targetAttribute' => ['object_uid' => 'id']]
        );
        array_push($rules,
            [['object_uid'], 'integer']
        );

//        }
        return $rules;
    }
    
    /**
     * @return array|false
     */
    public function fields()
    {
        return [
            'uid'        => 'id',
            'name',
            'is_payment',
            'ean13',
            'created_by',
            'created_at' => function () {
                return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
            'description',
            'ru',
            'hasl3',
            'object_uid',
            'info',
            'cnt',
            'expmonth',
            'gtin',
            'manufacturer_uid',
            'manufacturer_name',
            'code1c',
            'tnved',
            'fns_order_type',
            'fns_owner_id',
            'number_of_layers',
            'codes_on_layer',
            'cnt_on_pallet',
            'band_in_korob_cnt',
            'recipe_uid',
        ];
    }
    
    /**
     * @return array|false
     */
    public function extraFields()
    {
        return [
            'deleted_at',
            'deleted_by',
            'object',
            'canDelete',
            'manufacturer',
            'recipe',
        ];
    }
    
    /**
     * @return array
     */
    public function scenarios()
    {
        return array_merge(parent::scenarios(), [
            'odinStask' => ['ean13', 'created_by', 'name', 'cnt', 'gtin', 'manufacturer_uid', 'code1c', 'tnved', 'fns_order_type', 'fns_owner_id','is_payment'],
        ]);
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'               => 'ID',
            'name'             => 'Название',
            'is_payment'       => 'Оплачивается криптохвост или нет',
            'ean13'            => 'Штрих-код',
            'created_at'       => 'Дата создания',
            'created_by'       => 'Кто создал',
            'deleted_at'       => 'Дата удаления',
            'deleted_by'       => 'Кто удалил',
            'description'      => 'Сокращение для файла выгрузки',
            'ru'               => 'РУ',
            'info'             => 'Информация',
            'cnt'              => 'Упаковок в гофрокоробе',
            'expmonth'         => 'Дефолтный срок годности в месяцах',
            'canDelete'        => 'Возможно ли удаление',
            'manufacturer_uid' => 'Компания-производитель ',
            'code1c'           => 'Код продукта',
            'tnved'            => 'ТН ВЭД',
            'object_uid'       => 'Объект',
            'fns_order_type'   => 'Тип производства',
            'fns_owner_id'     => 'Собственник',
            'gtin'             => 'GTIN',
            'cnt_on_pallet'    => 'Кол-во коробов на паллете',
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeletedBy()
    {
        return $this->hasOne(User::class, ['id' => 'deleted_by']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid'])->where('1=1');
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::class, ['nomenclature_uid' => 'id']);
    }
    
    /**
     * @return |null
     */
    public function getManufacturer_name()
    {
        return $this->owner->name ?? null;
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getManufacturer()
    {
        return $this->hasOne(Manufacturer::class, ['id' => 'manufacturer_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRecipe()
    {
        return $this->hasOne(Recipe::class, ['id' => 'recipe_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwner()
    {
        return $this->hasOne(Manufacturer::class, ['ownerid' => 'fns_owner_id']);
    }
    
    /**
     * @return bool|false|int
     */
    public function delete()
    {
        $this->deleted_at = new Expression('NOW()');
        $this->deleted_by = Yii::$app->user->getId() ?? null;
        $this->gtin = $this->gtin . '_deleted_' . $this->deleted_at;
        
        return $this->save();
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
//    public function getProducts()
//    {
//        return $this->hasMany(Product::class, ['nomenclature_uid' => 'id']);
//    }

    /**
     * @return bool
     * @throws \yii\db\Exception
     */
    public function getCanDelete()
    {
        $count = $this->getDb()->createCommand("
                SELECT count(product.id) FROM product
                JOIN nomenclature on product.nomenclature_uid = nomenclature.id AND nomenclature.id = :ID",
            [':ID' => $this->id,
            ])->queryScalar();
        
        return ($count == 0);
    }
    
    /**
     * @param $value
     *
     * @return bool
     */
    private function validateEan13($value)
    {
        $code = str_pad(substr($value, 0, 12), 12, "0", STR_PAD_LEFT);
        $sum = 0;
        for ($i = (strlen($code) - 1); $i >= 0; $i--) {
            $sum += (($i % 2) * 2 + 1) * $code[$i];
        }
        $d = ($sum % 10);
        if ($d > 0) {
            $d = 10 - $d;
        }
        
        return substr($value, 12, 1) == $d;
    }

    /**
     * @param string $gtin
     * @return Nomenclature|null
     */
    public static function findNomenclatureByGtin(string $gtin): ?Nomenclature
    {
        return Nomenclature::findOne(['gtin' => $gtin]);
    }
}