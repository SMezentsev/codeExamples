<?php

namespace app\modules\itrack\components\Notify\Fns;

use Yii;

class FnsMacros
{
    /**
     * @var string
     */
    public $nomenclature;
    /**
     * @var string
     */
    public $serie;
    /**
     * @var string
     */
    public $gtin;
    /**
     * @var string
     */
    public $id;
    /**
     * @var string
     */
    public $fnsid;
    /**
     * @var string
     */
    public $subject_id;
    /**
     * @var string
     */
    public $subject_name;
    /**
     * @var string
     */
    public $operation;
    /**
     * @var string
     */
    public $status;
    /**
     * @var string
     */
    public $grp_count;
    /**
     * @var string
     */
    public $sgtin_count;
    /**
     * @var string
     */
    public $invoice_number;
    /**
     * @var string
     */
    public $invoice_date;

    /**
     * запонить пустые поля значениями по-умолчанию
     */
    public function fillEmptyFields(): void
    {
        $defaultValues = self::defaults();
        $properties = get_object_vars($this);

        foreach ($properties as $name => $value) {
            if (empty($value)) {
                $this->$name = $defaultValues->$name;
            }
        }
    }

    /**
     * получить значения по-умолчанию
     * @return FnsMacros
     */
    public static function defaults()
    {
        $macros = new self();

        $macros->nomenclature = Yii::t('app', 'Не определена');
        $macros->serie = Yii::t('app', 'Не определена');
        $macros->gtin = Yii::t('app', 'Не определен');
        $macros->id = Yii::t('app', 'Не определен');
        $macros->fnsid = Yii::t('app', 'Не определен');
        $macros->subject_id = Yii::t('app', 'Не определен');
        $macros->subject_name = Yii::t('app', 'Не определен');
        $macros->operation = Yii::t('app', 'Не определена');
        $macros->status = Yii::t('app', 'Не определен');
        $macros->grp_count = 0;
        $macros->sgtin_count = 0;
        $macros->invoice_number = Yii::t('app', 'Не определен');
        $macros->invoice_date = Yii::t('app', 'Не определена');

        return $macros;
    }
}