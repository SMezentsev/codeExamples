<?php


namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\ProductionOrder;
use app\modules\itrack\Services\CodesImport\DTO\CodeDataDto;
use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\CodesImport\Interfaces\CodesImportDataParserInterface;
use app\modules\itrack\Services\CodesImport\Models\Code;
use app\modules\itrack\Services\CodesImport\Models\CodeCollection;

/**
 * Class CodesImportDataParser
 */
class CodesImportDataParser implements CodesImportDataParserInterface
{
    /**
     * @var array
     */
    private $importData;
    /**
     * @var bool|null
     */
    private $isTransfer;
    /**
     * @var ProductionOrder|null
     */
    private $productionOrder;
    /**
     * @var array|null
     */
    private $productData;
    /**
     * @var string|null
     */
    private $subjectData;
    /**
     * @var CodeCollection
     */
    private $aggregatedDataCollection;
    /**
     * @var CodeCollection
     */
    private $ssccDataCollection;
    /**
     * @var CodeCollection
     */
    private $serialDataCollection;

    private const AGGREGATED_DATA_KEY = 'aggregatedData';
    private const TRANSFER_FLAG_KEY = 'isTransfer';
    private const PRODUCTION_ORDER_KEY = 'productionOrder';
    private const SUBJECT_ID_KEY = 'subject_id';
    private const PRODUCT_KEY = 'product';
    private const EMPTY_DATA_KEY = 'emptyData';
    private const EMPTY_SSCC_KEY = 'sscc';
    private const EMPTY_SERIALS_KEY = 'serial';
    private const DATA_ITEMS_KEY = 'dataItems';
    private const CODE_KEY = 'code';
    private const CODE_TYPE_KEY = 'type';
    private const CODE_TYPE_SSCC = 'sscc';
    private const CODE_TYPE_SERIAL = 'sgtin';
    private const CONTENT_KEY = 'content';

    public function __construct(
        CodeCollection $aggregatedDataCollection,
        CodeCollection $ssccDataCollection,
        CodeCollection $serialDataCollection
    )
    {
        $this->aggregatedDataCollection = $aggregatedDataCollection;
        $this->ssccDataCollection = $ssccDataCollection;
        $this->serialDataCollection = $serialDataCollection;
        $this->importData = [];
    }

    /**
     * @param array $importData
     * @throws \ErrorException
     */
    public function parse(array $importData): void
    {
        $this->importData = $importData;
        $this->setIsTransfer();
        $this->setProductionOrder();
        $this->setProductData();
        $this->setObjectData();
        $this->setAggregatedData();
        $this->setEmptyData();
    }

    /**
     * @return bool|null
     */
    public function getIsTransfer(): ?bool
    {
        return $this->isTransfer;
    }

    /**
     * @return ProductionOrder|null
     */
    public function getProductionOrder(): ?ProductionOrder
    {
        return $this->productionOrder;
    }

    /**
     * @return array|null
     */
    public function getProductData(): ?array
    {
        return $this->productData;
    }

    /**
     * @return string|null
     */
    public function getSubjectData(): ?string
    {
        return $this->subjectData;
    }

    /**
     * @return CodeCollection
     */
    public function getAggregatedDataCollection(): CodeCollection
    {
        return $this->aggregatedDataCollection;
    }

    /**
     * @return CodeCollection
     */
    public function getSsccDataCollection(): CodeCollection
    {
        return $this->ssccDataCollection;
    }

    /**
     * @return CodeCollection
     */
    public function getSerialDataCollection(): CodeCollection
    {
        return $this->serialDataCollection;
    }

    private function setIsTransfer(): void
    {
        if (array_key_exists(self::TRANSFER_FLAG_KEY, $this->importData)) {
            $this->isTransfer = $this->importData[self::TRANSFER_FLAG_KEY];
        }
    }

    /**
     * @throws \ErrorException
     */
    private function setProductionOrder(): void
    {
        if (array_key_exists(self::PRODUCTION_ORDER_KEY, $this->importData)) {
            $this->productionOrder = $this->importData[self::PRODUCTION_ORDER_KEY];
        } else {
            throw new \ErrorException(\Yii::t('app', 'Отсутствуют данные о производственном заказе!'));
        }
    }

    private function setProductData(): void
    {
        if (array_key_exists(self::PRODUCT_KEY, $this->importData)) {
            $this->productData = $this->importData[self::PRODUCT_KEY];
        }
    }

    /**
     * @throws \ErrorException
     */
    private function setObjectData(): void
    {
        if (array_key_exists(self::SUBJECT_ID_KEY, $this->importData)) {
            $this->subjectData = $this->importData[self::SUBJECT_ID_KEY];
        } else {
            throw new \ErrorException(\Yii::t('app', 'Не установлены данные объекта!'));
        }
    }

    /**
     * @throws \ErrorException
     */
    private function setAggregatedData(): void
    {
        if (!array_key_exists(self::AGGREGATED_DATA_KEY, $this->importData)) {
            return;
        }

        $codesData = $this->importData[self::AGGREGATED_DATA_KEY][self::DATA_ITEMS_KEY];

        foreach ($codesData as $code) {
            $this->aggregatedDataCollection->attach($this->parseCodeData($code));
        }
    }

    /**
     * @param array $codeData
     * @return Code
     * @throws \ErrorException
     */
    private function parseCodeData(array $codeData): Code
    {
        $codeDataDto = new CodeDataDto();
        $codeDataDto->setCode($codeData[self::CODE_KEY]);
        $codeDataDto->setCodeType($this->getCodeTypeByStr($codeData[self::CODE_TYPE_KEY]));
        $codeDataDto->setTransferFlag($this->isTransfer);

        if (array_key_exists(self::PRODUCT_KEY, $codeData)) {
            $codeDataDto->setProduct($codeData[self::PRODUCT_KEY]);
        }

        if (array_key_exists(self::CONTENT_KEY, $codeData)) {
            $dataItems = new CodeCollection();
            $codes = $codeData[self::CONTENT_KEY][self::DATA_ITEMS_KEY];

            foreach ($codes as $code) {
                $dataItems->attach($this->parseCodeData($code));
            }

            $codeDataDto->setDataItems($dataItems);
        }

        return new Code($codeDataDto);
    }

    /**
     * @param string $codeType
     * @return int
     * @throws \ErrorException
     */
    private function getCodeTypeByStr(string $codeType): int
    {
        switch ($codeType) {
            case self::CODE_TYPE_SSCC:
                return CodeType::CODE_TYPE_GROUP;
            case self::CODE_TYPE_SERIAL:
                return CodeType::CODE_TYPE_INDIVIDUAL;
            default:
                throw new \ErrorException(\Yii::t('app', 'Выбранный тип кода не поддерживается!'));
        }
    }

    /**
     * @param string $code
     * @param string $codeType
     * @param null $cryptoTail
     * @return Code
     * @throws \ErrorException
     */
    private function parseEmptyCodeData(string $code, string $codeType, $cryptoTail = null): Code
    {
        $codeDataDto = new CodeDataDto();
        $codeDataDto->setCode($code);
        $codeDataDto->setCodeType($this->getCodeTypeByStr($codeType));

        if ($cryptoTail !== null) {
            $codeDataDto->setCryptoTail($cryptoTail);
        }

        return new Code($codeDataDto);
    }

    /**
     * @throws \ErrorException
     */
    private function setEmptyData(): void
    {
        if (array_key_exists(self::EMPTY_DATA_KEY, $this->importData)) {
            if (array_key_exists(self::EMPTY_SSCC_KEY, $this->importData[self::EMPTY_DATA_KEY])) {
                $ssccCodes = $this->importData[self::EMPTY_DATA_KEY][self::EMPTY_SSCC_KEY];

                foreach ($ssccCodes as $ssccCode) {
                    $this->ssccDataCollection->attach(
                        $this->parseEmptyCodeData($ssccCode, self::CODE_TYPE_SSCC)
                    );
                }
            }

            if (array_key_exists(self::EMPTY_SERIALS_KEY, $this->importData[self::EMPTY_DATA_KEY])) {
                $serialCodes = $this->importData[self::EMPTY_DATA_KEY][self::EMPTY_SERIALS_KEY];

                foreach ($serialCodes as $serialCode => $cryptoTail) {
                    $this->serialDataCollection->attach(
                        $this->parseEmptyCodeData($serialCode, self::CODE_TYPE_SERIAL, $cryptoTail)
                    );
                }
            }
        }
    }
}