<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Code;
use app\modules\itrack\models\Fns;

class EpcisReportCommissioning extends EpcisReport
{
    /**
     * Строит тело отчета по операции с кодами
     * @param \XMLWriter $xml
     * @return \XMLWriter
     * @throws \ErrorException
     */
    protected function buildEpcisBody(\XMLWriter $xml)
    {
        $xml->startElement('EPCISBody');
        $xml->startElement('EventList');
        
        if ($this->codes === null) {
            throw new \ErrorException('В операции нет информации об упакованных кодах.');
        }

        $xml = $this->buildEventCommissioningItemLevel($xml, $this->codes['serials']);

        foreach ($this->codes['groupCodes'] as $groupCode) {
            $xml = $this->buildEventCommissioningCaseLevel($xml, $groupCode);
        }

        $xml->endElement();
        $xml->endElement();

        return $xml;
    }

    /**
     * Строит структуру отчета для родительской упаковки
     *
     * @param \XMLWriter $xml
     * @param $groupCode
     *
     * @return \XMLWriter
     */
    protected function buildEventCommissioningCaseLevel(\XMLWriter $xml, $groupCode)
    {
        $xml->startElement('ObjectEvent');

        $xml->startElement('eventTime');
        $xml->text($this->operationFnsSendTime->format('Y-m-d\TH:i:s\Z'));
        $xml->endElement();

        $xml->startElement('eventTimeZoneOffset');
        $xml->text('-05:00');
        $xml->endElement();

        $xml->startElement('epcList');
        $xml->startElement('epc');
        $xml->text('(00)' . $groupCode['code']);
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('action');
        $xml->text('ADD');
        $xml->endElement();

        $xml->startElement('bizStep');
        $xml->text('urn:epcglobal:cbv:bizstep:commissioning');
        $xml->endElement();

        $xml->startElement('disposition');
        $xml->text('urn:epcglobal:cbv:disp:active');
        $xml->endElement();

        $xml->startElement('readPoint');
        $xml->startElement('id');
        $xml->text('urn:epc:id:sgln:461001202.999.7');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('bizLocation');
        $xml->startElement('id');
        $xml->text('urn:epc:id:sgln:461001202.999.7');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('SAPExtension');
        $xml->startElement('objAttributes');
        $xml->startElement('PACKAGINGLEVEL');
        $xml->text((($groupCode['flag'] & self::PALLET_FLAG) > 0) ? self::PACK_LEVEL_PALLET : self::PACK_LEVEL_CASE);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        $xml->endElement();

        return $xml;
    }

    /**
     * Строит структуру отчета для дочерней упаковки
     *
     * @param \XMLWriter $xml
     * @param $codes
     * @return \XMLWriter
     */
    protected function buildEventCommissioningItemLevel(\XMLWriter $xml, $codes)
    {
        $xml->startElement('ObjectEvent');

        $xml->startElement('eventTime');
        $xml->text($this->operationFnsSendTime->format('Y-m-d\TH:i:s\Z'));
        $xml->endElement();

        $xml->startElement('eventTimeZoneOffset');
        $xml->text('-05:00');
        $xml->endElement();

        $xml->startElement('epcList');

        foreach ($codes as $code) {
            $xml->startElement('epc');
            $xml->text('(01)' . $this->nomenclature->gtin . '(21)' . $code);
            $xml->endElement();
        }

        $xml->endElement();

        $xml->startElement('action');
        $xml->text('ADD');
        $xml->endElement();

        $xml->startElement('bizStep');
        $xml->text('urn:epcglobal:cbv:bizstep:commissioning');
        $xml->endElement();

        $xml->startElement('disposition');
        $xml->text('urn:epcglobal:cbv:disp:active');
        $xml->endElement();

        $xml->startElement('readPoint');
        $xml->startElement('id');
        $xml->text('urn:epc:id:sgln:461001202.999.7');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('bizLocation');
        $xml->startElement('id');
        $xml->text('urn:epc:id:sgln:461001202.999.7');
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('SAPExtension');
        $xml->startElement('objAttributes');

        $xml->startElement('DATMF');
        $xml->text(date('Ymd', strtotime(str_replace(' ', '.', '01 ' . $this->product->cdate))));
        $xml->endElement();

        $xml->startElement('LOTNO');
        $xml->text($this->product->series);
        $xml->endElement();

        $xml->startElement('DATEX');
        $xml->text(date('Ymd', strtotime(str_replace(' ', '.', '01 ' . $this->product->expdate))));
        $xml->endElement();

        $xml->startElement('GTIN');
        $xml->text($this->nomenclature->gtin);
        $xml->endElement();

        $xml->startElement('PACKAGINGLEVEL');
        $xml->text(($this->mode == self::PACK_LEVEL_CASE) ? self::PACK_LEVEL_ITEM : self::PACK_LEVEL_CASE);
        $xml->endElement();

        $xml->endElement();
        $xml->endElement();

        $xml->endElement();

        return $xml;
    }

    /**
     * @param \DateTime $createdAt
     * @throws \ErrorException
     */
    public function setOperationDate(\DateTime $createdAt): void
    {
        $operation = Fns::find()
            ->where(
                [
                    'operation_uid' => Fns::OPERATION_PACK_ID,
                    'product_uid' => $this->product->id,
                ]
            )
            ->andWhere(['<=', 'created_at', $createdAt->format('Y-m-d')])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->one();

        if ($operation === null) {
            throw new \ErrorException(\Yii::t(
                'app',
                'Не удалось найти операцию для товарной карты с id {product}',
                ['product' => $this->product->id]
            ));
        }
        $this->operationFnsSendTime = new \DateTime($operation['updated_at']);
    }
}