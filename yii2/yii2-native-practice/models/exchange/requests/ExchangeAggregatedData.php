<?php


namespace app\modules\itrack\models\exchange\requests;

use app\modules\itrack\models\exchange\exceptions\SerializeException;
use Yii;
use yii\base\Model;

class ExchangeAggregatedData extends Model
{
    public $pallets;
    public $cases;
    public $commonProduct;

    public function rules()
    {
        return [
            [['pallets', 'cases', 'commonProduct'], 'safe']
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        if ($this->pallets !== null) {
            $palletObjects = [];

            foreach ($this->pallets as $pallet) {
                $palletObject = Yii::createObject(ExchangePallet::className());
                $palletObject->attributes = $pallet;
                $palletObject->commonProduct = $this->commonProduct;

                if (!$palletObject->validate()) {
                    throw new SerializeException(
                        array_merge(['aggregatedData' => 'данные палет не прошли валидацию!'], $palletObject->getErrors())
                    );
                }

                $palletObjects[] = $palletObject;
            }
        }

        if ($this->cases !== null) {
            $caseObjects = [];

            foreach ($this->cases as $case) {
                $caseObject = Yii::createObject(ExchangeCase::className());
                $caseObject->attributes = $case;
                $caseObject->commonProduct = $this->commonProduct;
                $caseObject->isPacked = false;

                if (!$caseObject->validate()) {
                    throw new SerializeException(
                        array_merge(['aggregatedData' => 'данные коробов не прошли валидацию!'], $caseObject->getErrors())
                    );
                }

                $caseObjects[] = $caseObject;
            }
        }

        return parent::validate($attributeNames, $clearErrors);
    }
}