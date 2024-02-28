<?php

namespace app\modules\itrack\models;

use Yii;

/**
 * This is the model class for table "recipes".
 *
 * @property int            $id
 * @property string         $name
 * @property int            $object_uid
 * @property int            $cnt_in_layer
 * @property int            $layers_in_pack
 * @property int            $packs_on_pallet
 * @property int            $layer_height
 * @property int            $template_l1
 * @property int            $template_l2
 * @property int            $template_l3
 * @property int            $template_l4
 * @property string         $created_at
 * @property int            $created_by
 * @property bool           $type_def
 *
 * @property LabelTemplates $templateL1
 * @property LabelTemplates $templateL2
 * @property LabelTemplates $templateL3
 * @property LabelTemplates $templateL4
 * @property Facility       $object
 * @property User           $createdBy
 */
class Recipe extends \yii\db\ActiveRecord
{
    static $auditOperation = \app\modules\itrack\models\AuditOperation::OP_PRODUCT;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'recipes';
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            if (!\Yii::$app->user->can('WEB.recipe.template1')) {
                $this->template_l1 = null;
            }
            if (!\Yii::$app->user->can('WEB.recipe.template2')) {
                $this->template_l2 = null;
            }
            if (!\Yii::$app->user->can('WEB.recipe.template3')) {
                $this->template_l3 = null;
            }
            if (!\Yii::$app->user->can('WEB.recipe.template4')) {
                $this->template_l4 = null;
            }
        });
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
            [['name', 'cnt_in_layer', 'layers_in_pack', 'packs_on_pallet', 'layer_height'], 'required'],
            [['name'], 'string'],
            [['name'], 'unique', 'message' => 'Рецепт с таким наименованием уже создан'],
            [['object_uid', 'cnt_in_layer', 'layers_in_pack', 'packs_on_pallet', 'layer_height', 'template_l1', 'template_l2', 'template_l3', 'template_l4', 'created_by'], 'default', 'value' => null],
            [['object_uid', 'cnt_in_layer', 'layers_in_pack', 'packs_on_pallet', 'layer_height', 'template_l1', 'template_l2', 'template_l3', 'template_l4', 'created_by'], 'integer'],
            [['created_at'], 'safe'],
            [['type_def'], 'boolean'],
            [['template_l1'], 'exist', 'skipOnError' => true, 'targetClass' => LabelTemplates::class, 'targetAttribute' => ['template_l1' => 'id']],
            [['template_l2'], 'exist', 'skipOnError' => true, 'targetClass' => LabelTemplates::class, 'targetAttribute' => ['template_l2' => 'id']],
            [['template_l3'], 'exist', 'skipOnError' => true, 'targetClass' => LabelTemplates::class, 'targetAttribute' => ['template_l3' => 'id']],
            [['template_l4'], 'exist', 'skipOnError' => true, 'targetClass' => LabelTemplates::class, 'targetAttribute' => ['template_l4' => 'id']],
            [['object_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Facility::class, 'targetAttribute' => ['object_uid' => 'id']],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['created_by' => 'id']],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => Yii::t('app', 'ID'),
            'name'            => Yii::t('app', 'Наименование'),
            'object_uid'      => Yii::t('app', 'Объект'),
            'cnt_in_layer'    => Yii::t('app', 'Количество упаковок в слое короба'),
            'layers_in_pack'  => Yii::t('app', 'Количество слоев в коробе'),
            'packs_on_pallet' => Yii::t('app', 'Коробов на паллете'),
            'layer_height'    => Yii::t('app', 'Высота слоя'),
            'template_l1'     => Yii::t('app', 'Шаблон индивидуальной этикетки'),
            'template_l2'     => Yii::t('app', 'Шаблон этикетки на бандероль'),
            'template_l3'     => Yii::t('app', 'Шаблон этикетки на короб'),
            'template_l4'     => Yii::t('app', 'Шаблон этикетки на паллеты'),
            'created_at'      => Yii::t('app', 'Дата создания'),
            'created_by'      => Yii::t('app', 'Кто создал'),
            'type_def'        => Yii::t('app', 'Тип'),
        ];
    }
    
    public function fields()
    {
        return array_merge(parent::fields(), [
            'uid' => 'id',
            'templateL1',
            'templateL2',
            'templateL3',
            'templateL4',
        ]);
    }
    
    public function delete()
    {
        if ($this->type_def) {
            throw new \yii\web\BadRequestHttpException('Ошибка удаления, дефолтный рецепт');
        }
        $pr = Product::findOne(['recipe_uid' => $this->id]);
        if (!empty($pr)) {
            throw new \yii\web\BadRequestHttpException('Ошибка удаления, рецепт используется');
        }
        
        return parent::delete();
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTemplateL1()
    {
        if (!\Yii::$app->user->can('WEB.recipe.template1')) {
            return null;
        }
        
        return $this->hasOne(LabelTemplates::class, ['id' => 'template_l1']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTemplateL2()
    {
        if (!\Yii::$app->user->can('WEB.recipe.template2')) {
            return null;
        }
        
        return $this->hasOne(LabelTemplates::class, ['id' => 'template_l2']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTemplateL3()
    {
        if (!\Yii::$app->user->can('WEB.recipe.template3')) {
            return null;
        }
        
        return $this->hasOne(LabelTemplates::class, ['id' => 'template_l3']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTemplateL4()
    {
        if (!\Yii::$app->user->can('WEB.recipe.template4')) {
            return null;
        }
        
        return $this->hasOne(LabelTemplates::class, ['id' => 'template_l4']);
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
}
