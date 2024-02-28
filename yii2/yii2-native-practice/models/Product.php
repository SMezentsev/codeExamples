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
 * Class Product
 *   Сущность Товарная карточка
 *          т.е. при выпуске продукции, создается карточка в которой выбирается продукция и добавляется персональная информация
 *          по данной серии выпуска.. Дата выпуска, срок годности и тп. Не должна изменятся и удалятся (поэтому поля лишние)!
 *
 * @property integer      $id                 - Идентифкатор записи
 * @property integer      $nomenclature_uid   - ССылка на номенклатурный справочник
 * @property string       $created_at         - Дата создания
 * @property integer      $created_by         - Ссылка на создавшео пользователя
 * @property string       $cdate              - Дата выпуска продукции
 * @property string       $series             - Серия выпуска продукции
 * @property string       $expdate            - Срок годности выпущенной продукции
 * @property string       $expdate_full       - Срок годности выпущенной продукции
 * @property string       $deleted_at         - не нужно
 * @property integer      $deleted_by         - не нужно
 * @property string       $components
 * @property boolean      $editable
 * @property boolean      $accepted
 * @property integer      $object_uid
 * @property integer      $recipe_uid
 *
 * @property Nomenclature $nomenclature
 * @property Recipe       $recipe
 * @property bool         $canDelete
 *
 * Методы:
 *  - список
 *  - просмотр
 *  - создание
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Product",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example="1733"),
 *          @OA\Property(property="series", type="string", example="1234588"),
 *          @OA\Property(property="recipe_uid", type="integer", example=null),
 *          @OA\Property(property="cdate", type="string", example="03 2020"),
 *          @OA\Property(property="cdatewdots", type="string", example=null),
 *          @OA\Property(property="expdate", type="string", example="12 2021"),
 *          @OA\Property(property="expdate_full", type="string", example="31 12 2021"),
 *          @OA\Property(property="object_uid", type="integer", example="2"),
 *          @OA\Property(property="accepted", type="boolean", example=true),
 *          @OA\Property(property="nomenclature", type="array", @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Nomenclature")),
 *          @OA\Property(property="created_at", type="string", example="2020-03-10 10:55:09+0300"),
 *      }
 * )
 */
class Product extends ActiveRecord
{
    /**
     * @var int
     */
    static $auditOperation = AuditOperation::OP_PRODUCT;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'product';
    }
    
    /**
     * @return array|string[]
     */
    public static function primaryKey()
    {
        return ['id'];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public static function find()
    {
        return parent::find()->andWhere('product.deleted_at is null');
    }
    
    /**
     * Изменение данных по товарной карте и номенклатуре... для сторонних систем.
     *
     * @param type $params
     *
     * @return array
     * @throws \yii\web\BadRequestHttpException
     * @throws \Exception
     */
    static function UpdateExternal($params)
    {
        $trans = \Yii::$app->db->beginTransaction();
        
        try {
            if (empty($params["gtin"])) {
                throw new \Exception('Не передано занчение gtin');
            }
            
            if (empty($params["serie"])) {
                throw new \Exception('Не передано занчение serie');
            }
            
            $nomen = Nomenclature::findOne(['gtin' => $params["gtin"]]);
            
            if (empty($nomen)) {
                throw new \Exception('Номенклатура с gtin `' . $params["gtin"] . '` не найдена');
            }
            
            $product = Product::findOne(['nomenclature_uid' => $nomen->id, 'series' => $params["serie"]]);
            
            if (empty($product)) {
                throw new \Exception('Товарная карта с серией `' . $params['serie'] . '` не найдена');
            }
            
            if (empty($params["manufacturer"])) {
                throw new \Exception('Не передан производитель (manufacturer)');
            }
            
            if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', $params["productionDate"] ?? "", $match)) {
                $params["productionDate"] = "$match[1] $match[2] $match[3]";
            } elseif (preg_match('#^(\d{2})\.(\d{4})$#', $params["productionDate"] ?? "", $match)) {
                $params["productionDate"] = "$match[1] $match[2]";
            } else {
                throw new \Exception('Формат даты изготовления должен быть в формате `dd.mm.YYYY` или `mm.YYYY`');
            }
            
            $nomen->ean13 = $params["ean13"] ?? $nomen->ean13;
            $nomen->code1c = $params["productCode"] ?? $nomen->code1c;
            $nomen->name = $params["name"] ?? $nomen->name;
            $product->cdate = $params["productionDate"] ?? $product->cdate;
            
            $manufacturer = Manufacturer::findOne(['name' => $params["manufacturer"], 'external' => true]);
            
            if (empty($manufacturer)) {
                $manufacturer = new Manufacturer();
                $manufacturer->load([
                    'name'     => $params["manufacturer"],
                    'external' => true,
                ], '');
                
                if (!$manufacturer->save(false)) {
                    throw new \Exception('Ошибка сохранения производителя');
                }
            }
            
            if (!$nomen->save(false)) {
                throw new \Exception('Ошибка создания номенклатуры (' . implode(", ", array_map(function ($v) {
                        return implode(' - ', $v);
                    }, $nomen->errors)) . ')');
            }
            
            if (!$product->save(false)) {
                throw new \Exception('Ошибка сохранения товарной карты (' . implode(", ", array_map(function ($v) {
                        return implode(' - ', $v);
                    }, $product->errors)) . ')');
            }
            
            $trans->commit();
        } catch (\Exception $ex) {
            throw new \yii\web\BadRequestHttpException($ex->getMessage());
        }
        
        return ['message' => 'Ok', 'status' => 200];
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
            $this->created_at = 'NOW()';
            
            if (\Yii::$app->user->can('WEB.recipe.product.recipe_uid') && $this->recipe_uid == null) {
                $recipeUid = $this->nomenclature->recipe_uid;
                
                if ($recipeUid == null) {
                    throw new \ErrorException('У номенклатуры не установлен рецепт.', 400);
                }
                
                $this->recipe_uid = $this->nomenclature->recipe_uid;
            } else {
                $this->recipe_uid = null;
            }
        });
        
        $this->on(self::EVENT_BEFORE_UPDATE, function ($event) {
            /** @var $event ModelEvent */
            unset($event->sender->object_uid);
        });
        
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($this->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            if (is_array($this->components)) {
                $this->components = json_encode($this->components);
            }
            
            if (Constant::get('ProductAccept') == 'true' && empty($event->sender->accepted)) {
                $this->accepted = false;
            }
            /*
                        $user = Yii::$app->user->getIdentity();
                        if ($user && isset($user->object_uid) && !empty($user->object_uid)) {
                            $this->object_uid = $user->object_uid;
                        }
              */
            if (empty($this->object_uid)) {
                $n = (new Nomenclature())->findOne(["id" => $event->sender->nomenclature_uid]);
                $this->object_uid = $n->object_uid;
            }
        });
    }
    
    public function afterFind()
    {
        parent::afterFind();
        
        if (is_string($this->components)) {
            $this->components = json_decode($this->components, true);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [

//            ['expdate', 'date', 'min' => time(), 'format' => 'php:m Y'],
            [['nomenclature_uid', 'created_by', 'series', 'expdate', 'expdate_full'], 'required'],
            [['created_by', 'nomenclature_uid', 'series', 'cdate', 'expdate', 'expdate_full', 'object_uid'], 'required', 'on' => 'odinStask'],
            [['cdate'], 'required', 'message' => 'Не задана дата производства'],
            [['nomenclature_uid', 'created_by', 'deleted_by', 'object_uid', 'recipe_uid'], 'integer'],
            [['created_at', 'deleted_at'], 'safe'],
            [['series', 'cdate', 'expdate', 'components', 'expdate_full'], 'string'],
            ['series', 'string', 'max' => 40],
            [['editable'], 'boolean'],
            [['accepted'], 'boolean'],
            [['nomenclature_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Nomenclature::class, 'targetAttribute' => ['nomenclature_uid' => 'id']],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
            [['recipe_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Recipe::class, 'targetAttribute' => ['recipe_uid' => 'id']],
            [['nomenclature_uid'],
                'unique',
                'targetAttribute' => ['series', 'nomenclature_uid'],
                'message'         => 'Товарная карта с указанной номенклатурой и серией имеется в справочнике',
                'filter'          =>
                    function ($query) {
                        return $query->andWhere("coalesce(product.id,0) <> coalesce(:id,0)", [
                            ":id" => $this->id,
                        ]);
                    },
            ],
        ];
    }
    
    /**
     * @return array
     */
    public function scenarios()
    {
        return array_merge(parent::scenarios(), [
            'odinStask' => ['created_by', 'nomenclature_uid', 'series', 'cdate', 'expdate', 'expdate_full', 'object_uid'],
        ]);
    }
    
    /**
     * @return array|false
     */
    public function fields()
    {
        return [
            'uid'        => 'id',
            'series',
            'recipe_uid',
            'cdate',
            'cdatewdots' => function () {
//                return date('d.m.Y', strtotime($this->components[0]['cdate']));
            },
            'expdate',
            'expdate_full',
            'object_uid',
            'accepted',
            'nomenclature',
            'created_at' => function () {
                return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
        ];
    }
    
    /**
     * @return array|false
     */
    public function extraFields()
    {
        return [
            'nomenclature_uid',
            'components',
            'created_by',
            'deleted_at',
            'deleted_by',
            'canDelete',
            'tnved',
            'recipe',
        ];
    }
    
    /**
     * @return mixed
     */
    public function getTnved()
    {
        return $this->nomenclature->tnved;
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'               => 'ID',
            'nomenclature_uid' => 'Номенклатура',
            'created_at'       => 'Дата создания',
            'created_by'       => 'Кто создал',
            'cdate'            => 'Дата выпуска',
            'series'           => 'Серия',
            'expdate'          => 'Дата окончания срока годности',
            'deleted_at'       => 'Дата удаления',
            'deleted_by'       => 'Кто удалил',
            'components'       => 'Компоненты',
            'object_uid'       => 'Объект',
            'expdate_full'     => 'Дата окончания срока годности',
            'accepted'         => 'Подтверждено',
        ];
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
    public function getNomenclature()
    {
        return $this->hasOne(Nomenclature::class, ['id' => 'nomenclature_uid']);
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
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeletedBy()
    {
        return $this->hasOne(User::class, ['id' => 'deleted_by']);
    }
    
    /**
     * @return false|int
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function delete()
    {
        $this->deleted_at = new Expression('NOW()');
        $this->deleted_by = Yii::$app->user->getId() ?? null;
        $this->series = $this->series . '_deleted_'.$this->deleted_at;

        return $this->update(false, ['deleted_at', 'series', 'deleted_by']);
    }
    
    /**
     * @return bool
     * @throws \yii\db\Exception
     */
    public function getCanDelete()
    {
        $count = $this->getDb()->createCommand("
                SELECT sum(cnt) FROM generations WHERE product_uid = :ID and status_uid not in (4,6,8,14,15)", [
            ':ID' => $this->id,
        ])->queryScalar();
        
        return ($count == 0);
    }

    /**
     * @param array $attributes
     * @return Product
     * @throws \ErrorException
     */
    public static function createProduct(array $attributes): Product
    {
        $product = new Product();
        $product->attributes = $attributes;

        if (!$product->save()) {
            throw new \ErrorException(\Yii::t('app', 'Не удалось сохранить товарную карту!'));
        }

        return $product;
    }
}
