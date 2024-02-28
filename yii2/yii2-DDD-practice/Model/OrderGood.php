<?php

namespace common\components\order\Model;

use common\models\Autopart;
use common\models\DiskGood;
use common\models\TyreGood;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 *
 * @property integer                $good_id
 * @property integer                $order_id
 * @property integer                $good_category_id
 * @property integer                $quantity
 * @property float                  $price
 *
 * @property-read OrderGood[]|array $goodsModels
 */
class OrderGood extends ActiveRecord
{
    
    public const CATEGORY_TYRE       = 1;
    public const CATEGORY_DISK       = 2;
    public const CATEGORY_AUTO_PARTS = 3;
    
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%order_good}}';
    }
    
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['good_id', 'order_id', 'quantity', 'good_category_id', 'price'], 'required'],
            [['good_id', 'order_id', 'quantity', 'good_category_id'], 'integer'],
            [['price'], 'number'],
            [['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
            [['good_category_id'], 'exist', 'targetClass' => GoodCategory::class, 'targetAttribute' => 'id'],
            [['created_at'], 'safe'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'quantity'         => 'Количество товара',
            'order_id'         => 'ID заказа',
            'good_id'          => 'ID товара',
            'good_category_id' => 'ID категории товара',
            'price'            => 'Цена товара',
        ];
    }
    
    //todo тут какая-то хрень
    public function getGoodCategory()
    {
        return $this->hasOne(GoodCategory::class, ['id' => 'good_category_id'])->one();
    }
    
    public function getGood()
    {
        $good = null;
        switch ($this->good_category_id) {
            case self::CATEGORY_TYRE:
                $good = TyreGood::find()->where(['idx' => $this->good_id])->one();
                break;
            case self::CATEGORY_DISK:
                $good = DiskGood::find()->where(['disk_id' => $this->good_id])->one();
                break;
            case self::CATEGORY_AUTO_PARTS:
                $good = Autopart::find()->where(['autopart_id' => $this->good_id])->one();
        }
        
        return $good;
    }
    
    public function extraFields(): array
    {
        return [
            'good_category' => static function (self $model) {
                return $model->getGoodCategory();
            },
            'price_rub'     => static function (self $model) {
                return $model->price / 100;
            },
            'price_total'   => static function (self $model) {
                return ($model->price / 100) * $model->quantity;
            },
            'good'          => static function (self $model) {
                return $model->getGood();
            },
        
        ];
    }
    
    /**
     * @param bool $insert
     *
     * @return bool
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->setAttribute('price', $this->price * 100);
        }
        
        if ($insert && $this->created_at === '') {
            $this->created_at = new Expression('NOW()');
        }
        
        return parent::beforeSave($insert);
    }
    
}