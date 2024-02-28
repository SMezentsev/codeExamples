<?php


namespace app\modules\itrack\models\exchange\requests;

use app\modules\itrack\models\exchange\exceptions\SerializeException;
use Yii;
use yii\base\Model;

class ExchangeCase extends Model
{
    public $sscc;
    public $content;
    public $product;
    public $commonProduct;
    public $isPacked;

    public function rules()
    {
        return [
            [['sscc', 'content', 'isPacked'], 'required'],
            [['product', 'commonProduct'], 'safe'],
            [['product'], 'required', 'when' => function($model) {
                if ($model->isPacked === false && $model->commonProduct === null) {
                    return true;
                }
            }],

        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        foreach ($this->content as $code) {
            $itemObject = Yii::createObject(ExchangeItem::className());
            $itemObject->code = $code;
            if (!$itemObject->validate()) {
                throw new SerializeException(
                    array_merge(['items' => 'В коробе '. $this->sscc . ' данные упаковок не прошли валидацию!'], $itemObject->getErrors())
                );
            }
        }

        return parent::validate($attributeNames, $clearErrors);
    }

    public function getProductData()
    {
        return $this->product;
    }
}