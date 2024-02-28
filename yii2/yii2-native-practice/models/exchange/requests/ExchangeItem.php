<?php


namespace app\modules\itrack\models\exchange\requests;

use Yii;
use yii\base\Model;

class ExchangeItem extends Model
{
    public $code;

    public function rules()
    {
        return [
            ['code', 'required'],
        ];
    }

}