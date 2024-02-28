<?php

namespace app\modules\itrack\components\Notify\Fns;

use app\modules\itrack\components\ArrayHelper;
use app\modules\itrack\components\Notify\Fns\Interfaces\FnsMacrosBuilderInterface;
use app\modules\itrack\models\Fns;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Invoice;
use app\modules\itrack\models\Product;
use \yii\db\Connection;
use Yii;

class FnsMacrosBuilder implements FnsMacrosBuilderInterface
{
    /**
     * @var FnsMacros
     */
    private $macros;

    /**
     * @var Connection
     */
    private $db;

    public function __construct()
    {
        $this->db = Yii::$app->db;
    }

    public function build(Fns $fns):FnsMacros
    {
        $this->macros = new FnsMacros();

        $this->macros->id = $fns->id;
        $this->macros->operation = $fns->operation;
        $this->macros->status = Fns::getStateInfo($fns->state);
        $this->macros->fnsid = $fns->fnsid;
        $this->macros->grp_count = $fns->grpcnt;
        $this->macros->sgtin_count = $fns->indcnt;

        $this->setInvoice($fns->invoice, pghelper::pgarr2arr($fns->data));
        $this->setProducts(isset($fns->product) ? [$fns->product] : $fns->getFullProduct());
        $fns_params = unserialize($fns->fns_params);
        $this->setParameters(is_array($fns_params) ? $fns_params : []);
        $this->updateSubjectId($fns->getFullobject());
        $this->updateSubjectName();

        $this->macros->fillEmptyFields();

        return $this->macros;
    }

    private function setParameters(array $parameters): void
    {
        $this->macros->gtin = $parameters['gtins'] ?? null;
        $this->macros->subject_id = $parameters['subject_id'] ?? null;
    }

    private function updateSubjectId($facility):void
    {
        if (!isset($this->macros->subject_id) && $facility->fns_subject_id) {
            $this->macros->subject_id = $facility->fns_subject_id;
        }
    }

    private function updateSubjectName()
    {
        if (isset($this->macros->subject_id)) {
            $subjectData = $this->db->createCommand(
                'SELECT * FROM suppliers WHERE subject_id = :subject_id OR regnum = :regnum',
            [
                ':subject_id' => $this->macros->subject_id,
                ':regnum' => $this->macros->subject_id
            ])->queryOne();
            $this->macros->subject_name = isset($subjectData) ? $subjectData['name'] : $this->macros->subject_id;
        }
    }

    private function setInvoice(?Invoice $invoice, array $data): void
    {
        $docNum = $data[0] ?? null;
        $docDate = $data[1] ?? null;

        $this->macros->invoice_number = $invoice ? $invoice->invoice_number : $docNum;
        $this->macros->invoice_date = $invoice ? $invoice->invoice_date : $docDate;
    }

    private function setProducts(array $products): void
    {
        if (!isset($products)) {
            return;
        }

        $series = $nomenclatureName = $nomenclatureGtin = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $series[] = $product->series;
            $nomenclatureName[] = $product->nomenclature->name;
            $nomenclatureGtin[] = $product->nomenclature->gtin;
        }

        $this->macros->serie = ArrayHelper::arr2str($series);
        $this->macros->nomenclature = ArrayHelper::arr2str($nomenclatureName);
        $this->macros->gtin = ArrayHelper::arr2str($nomenclatureGtin);
    }
}