<?php


namespace app\modules\itrack\models\exchange\requests;

use app\modules\itrack\models\exchange\exceptions\SerializeException;
use Yii;
use yii\base\Model;

/**
 * Class ExchangePallet
 * @package app\modules\itrack\models\exchange\requests
 */
class ExchangePallet extends Model
{
    public $sscc;
    public $content;
    public $product;
    public $commonProduct;
    private $casesCount;
    private $oneType;

    public function rules()
    {
        return [
            [['sscc', 'content'], 'required'],
            ['commonProduct', 'safe'],
            [['product'], 'required', 'when' => function($model) {
                if ($model->oneType === true && $model->commonProduct === null) {
                    return true;
                } elseif ($model->oneType === false && $model->commonProduct === null) {
                    return false;
                } else {
                    return false;
                }
            }],
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        $this->casesCount = count($this->content);

        foreach ($this->content as $case) {
            $caseObject = Yii::createObject(ExchangeCase::className());
            $caseObject->attributes = $case;
            $caseObject->isPacked = true;

            if (!$caseObject->validate()) {
                throw new SerializeException(
                    array_merge(['cases' => 'В палете '. $this->sscc . ' данные коробов не прошли валидацию!'], $caseObject->getErrors())
                );
            }

            if ($this->oneType === null) {
                if ($caseObject->getProductData() === null) {
                    $this->oneType = true;
                } else {
                    $this->oneType = false;
                }
            }
        }

        return parent::validate($attributeNames, $clearErrors);
    }
}