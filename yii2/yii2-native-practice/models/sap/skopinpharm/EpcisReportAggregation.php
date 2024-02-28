<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\components\pghelper;

/**
 * Класс строит EPCIS aggregation отчет
 * Class EpcisReportAggregation
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class EpcisReportAggregation extends EpcisReport
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
        
        foreach ($this->codes as $code) {
            $codeData = json_decode($code, true);
            $xml = $this->buildEventAggregation($xml, $codeData['codes'], $codeData['grp']);
        }
        
        $xml->endElement();
        $xml->endElement();
        
        return $xml;
    }

    /**
     * Строит структуру для события агрегации
     * @param \XMLWriter $xml
     * @param $codes
     * @param $groupCode
     * @return \XMLWriter
     */
    private function buildEventAggregation(\XMLWriter $xml, $codes, $groupCode)
    {
        $xml->startElement('AggregationEvent');

        $xml->startElement('eventTime');
        $xml->text($this->operationFnsSendTime->format('Y-m-d\TH:i:s\Z'));
        $xml->endElement();

        $xml->startElement('eventTimeZoneOffset');
        $xml->text('-05:00');
        $xml->endElement();

        $xml->startElement('parentID');
        $xml->text('(00)' . $groupCode);
        $xml->endElement();

        $xml->startElement('childEPCs');

        foreach ($codes as $code) {
            $xml->startElement('epc');

            if ($this->mode === self::PACK_LEVEL_PALLET) {
                $xml->text('(00)' . $code);
            } else {
                $xml->text('(01)' . $this->nomenclature->gtin . '(21)' . $code);
            }

            $xml->endElement();
        }

        $xml->endElement();

        $xml->startElement('action');
        $xml->text('ADD');
        $xml->endElement();

        $xml->startElement('bizStep');
        $xml->text('urn:epcglobal:cbv:bizstep:packing');
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

        $xml->endElement();

        return $xml;
    }

    /**
     * @param \DateTime $operationDateTime
     */
    public function setOperationDate(\DateTime $operationDateTime)
    {
        $this->operationFnsSendTime = $operationDateTime;
    }
}