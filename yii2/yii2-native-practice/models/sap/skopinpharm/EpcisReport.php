<?php

namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Product;
use yii\base\Model;

/**
 * Базовый класс для построения EPCIS отчетов
 * Class EpcisReport
 *
 * @package app\modules\itrack\models\sap\skopinpharm
 */
abstract class EpcisReport extends Model
{
    const PACK_LEVEL_PALLET = 'PL';
    const PACK_LEVEL_CASE = 'CA';
    const PACK_LEVEL_ITEM = 'EA';

    const PALLET_FLAG = 512;

    protected $xml;
    protected $operation;
    protected $product;
    protected $nomenclature;
    protected $codes;
    protected $mode;
    protected $sendingSystem;
    protected $senderGln;
    protected $receiverGln;
    protected $instanceIdentifier;
    /**
     * @var \DateTime|null
     */
    protected $operationFnsSendTime;
    
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->xml = null;
        $this->operation = null;
        $this->codes = null;
        $this->product = null;
        $this->nomenclature = null;
        $this->mode = self::PACK_LEVEL_CASE;
        $this->sendingSystem = \Yii::$app->params['sapIchSettings']['sendingSystem'];
        $this->senderGln = \Yii::$app->params['sapIchSettings']['senderGln'];
        $this->receiverGln = \Yii::$app->params['sapIchSettings']['receiverGln'];
        $this->instanceIdentifier = \Yii::$app->params['sapIchSettings']['instanceIdentifier'];
    }

    /**
     * Устанавливает данные товарной карты и номенклатуры
     * @param int $productId
     * @throws \ErrorException
     * @return void
     */
    public function setProductData(int $productId): void
    {
        $this->product = Product::findOne($productId);

        if ($this->product == null) {
            throw new \ErrorException('Не удалось сформировать epcis отчет, т.к. товарная карта не найдена.');
        }

        $this->nomenclature = $this->product->nomenclature;
    }

    /**
     * @param $codeFlag
     */
    public function setMode($codeFlag)
    {
        if (($codeFlag & self::PALLET_FLAG) == self::PALLET_FLAG) {
            $this->mode = self::PACK_LEVEL_PALLET;
        }
    }


    /**
     * Устанавливает базовые данные из операции для построения отчета
     * @param array $codesData
     * @throws \ErrorException
     * @throws \yii\db\Exception
     */
    public function setData(array $codesData) : void
    {
        $this->codes = $codesData;
    }

    /**
     * Генерирует отчет в формате xml
     * @return bool
     * @throws \ErrorException
     * @throws \ReflectionException
     */
    public function buildReport() :string
    {
        $this->xml = new \XMLWriter();
        $this->xml->openMemory();
        $this->xml->startDocument("1.0", 'UTF-8');
        $this->xml->startElement("epcis:EPCISDocument");
        $this->xml->writeAttribute('creationDate', date('Y-m-d\TH:i:s\Z', time()));
        $this->xml->writeAttribute('schemaVersion', '1.1');
        $this->xml->writeAttribute('xmlns:epcis', 'urn:epcglobal:epcis:xsd:1');
        $this->xml->writeAttribute('xmlns:gs1ushc', 'http://epcis.gs1us.org/hc/ns');
        $this->xml->writeAttribute('xmlns:sbdh', 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader');
        
        $this->xml = $this->buildEpcisHeader($this->xml);
        $this->xml = $this->buildEpcisBody($this->xml);
        
        $this->xml->endElement();
        $this->xml->endDocument();

        return $xmlData = $this->xml->outputMemory();
    }

    protected function sendXmlToSapIch(string $xmlData)
    {
        $connector = \Yii::createObject(SapIchConnector::className());
        $connector->epcisReportRequest($xmlData);
    }

    /**
     * Создает структуру заголовков для документа
     *
     * @param \XMLWriter $xml
     *
     * @return \XMLWriter
     */
    protected function buildEpcisHeader(\XMLWriter $xml)
    {
        $xml->startElement('EPCISHeader');
        
        $xml->startElement('n1:StandardBusinessDocumentHeader');
        $xml->writeAttribute('xmlns:n1', 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader');
        
        $xml->startElement('n1:HeaderVersion');
        $xml->text('1.0');
        $xml->endElement();
        
        $xml->startElement('n1:Sender');
        $xml->startElement('n1:Identifier');
        $xml->writeAttribute('Authority', 'GLN');
        $xml->text($this->senderGln);
        $xml->endElement();
        $xml->endElement();
        
        $xml->startElement('n1:Receiver');
        $xml->startElement('n1:Identifier');
        $xml->writeAttribute('Authority', 'GLN');
        $xml->text($this->receiverGln);
        $xml->endElement();
        $xml->endElement();
        
        $xml->startElement('n1:DocumentIdentification');
        $xml->startElement('n1:Standard');
        $xml->text('EPCglobal');
        $xml->endElement();
        
        $xml->startElement('n1:TypeVersion');
        $xml->text('1.0');
        $xml->endElement();
        
        $xml->startElement('n1:InstanceIdentifier');
        $xml->text($this->instanceIdentifier);
        $xml->endElement();
        
        $xml->startElement('n1:Type');
        $xml->text('Events');
        $xml->endElement();
        
        $xml->startElement('n1:CreationDateAndTime');
        $xml->text(date('Y-m-d\TH:i:s\Z', time()));
        $xml->endElement();
        $xml->endElement();
        
        $xml->endElement();
        $xml->endElement();
        
        return $xml;
    }

    /**
     * Строит тело отчета по доступным данным кодов
     * @param \XMLWriter $xml
     * @return mixed
     */
    abstract protected function buildEpcisBody(\XMLWriter $xml);
}