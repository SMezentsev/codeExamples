<?php


namespace app\modules\itrack\Services\CodesImport\Models;

use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\Services\CodesImport\DTO\CodeDataDto;
use app\modules\itrack\Services\Product\DTO\ProductDataDto;

/**
 * Class Code
 * @todo возможна реализация L3 все изменения должны быть сделаны тут для поддержки
 */
class Code
{
    /**
     * @var bool
     */
    public $transferFlag;
    /**
     * @var string
     */
    public $code;
    /**
     * @var int
     */
    public $codeType;
    /**
     * @var string
     */
    public $cryptoTail;
    /**
     * @var array|null
     */
    public $product;
    /**
     * @var CodeCollection|null
     */
    public $dataItems;

    private const PACK_FLAG = 1;
    private const TRANSFER_FLAG = 2;
    private const PALLET_FLAG = 512;
    private const TYPE_ITEM = 1;
    private const TYPE_BOX = 2;
    private const TYPE_PALLET = 3;

    public function __construct(CodeDataDto $codeDataDto)
    {
        $this->code = $codeDataDto->getCode();
        $this->codeType = $codeDataDto->getCodeType();
        $this->cryptoTail = $codeDataDto->getCryptoTail();
        $this->product = $codeDataDto->getProduct();
        $this->dataItems = $codeDataDto->getDataItems();
        $this->transferFlag = $codeDataDto->getTransferFlag();
    }

    /**
     * @return array
     */
    public function getProducts(): array
    {
        $products = [];

        if ($this->product !== []) {
            $products[] = $this->product;
        }

        if ($this->dataItems !== null) {
            foreach ($this->dataItems as $item) {
                $codeProducts = $item->getProducts();
                $diff = array_udiff_assoc($codeProducts, $products, function ($codeProduct, $uniqueProduct) {
                    return $codeProduct !== $uniqueProduct;
                });
                $products = array_merge($products, $diff);
            }
        }

        return $products;
    }

    /**
     * @param array|null $defaultProductData
     * @param Code|null $prevCode
     * @return array
     */
    public function getFormattedCodesHierarchy(array $defaultProductData = null, Code $prevCode = null)
    {
        $codesData = [];

        $codesData[] = $this->formatCodeData($defaultProductData, $prevCode);

        if ($this->dataItems !== null) {
            foreach ($this->dataItems as $item) {
                $formattedCodes = $item->getFormattedCodesHierarchy($defaultProductData, $this);
                $codesData = array_merge($codesData, $formattedCodes);
            }
        }

        return $codesData;
    }

    /**
     * @param array|null $defaultProductData
     * @param Code|null $prevCode
     * @return array
     * @throws \ErrorException
     */
    private function formatCodeData(array $defaultProductData = null, Code $prevCode = null): array
    {
        $prevProduct = ($prevCode !== null) ? $prevCode->product : null;
        $childrens = ($this->codeType === CodeType::CODE_TYPE_INDIVIDUAL) ? [$this->cryptoTail] : $this->serializeChildrenCodes();

        return [
            'code' => $this->code,
            'flag' => $this->calculateCodeFlag($this->getPackType()),
            'code_type' => $this->codeType,
            'parent_code' => ($prevCode === null) ? null : $prevCode->code,
            'product_uid' => $this->calculatePackProduct($this, $prevProduct, $defaultProductData),
            'generation_uid' => null,
            'childrens' => pghelper::arr2pgarr($childrens),
        ];
    }

    /**
     * @return array
     */
    public function serializeChildrenCodes(): array
    {
        $childrenCodes = [];

        if ($this->dataItems === null) {
            return $childrenCodes;
        }

        foreach ($this->dataItems as $code) {
            $childrenCodes = array_merge($childrenCodes, $code->serializeChildrenCodes());
            $childrenCodes[] = $code->code;
        }

        return $childrenCodes;
    }

    /**
     * @param Code $context
     * @param array|null $parentProductData
     * @param array|null $defaultProductData
     * @return array|mixed|null
     * @throws \ErrorException
     */
    private function calculatePackProduct(Code $context, array $parentProductData = null, array $defaultProductData = null)
    {
        if (!empty($parentProductData)) {
            return $parentProductData;
        }

        if (!empty($context->product)) {
            return $context->product;
        }

        $products = [];

        if ($context->dataItems !== null) {
            foreach ($context->dataItems as $code) {
                if (!empty($code->product)) {
                    $products[] = $code->product;
                }
            }
        }

        if (count($products) > 1) {
            return null;
        } elseif (count($products) === 1) {
            return array_pop($products);
        } elseif (count($products) === 0) {
            if ($defaultProductData !== null) {
                return $defaultProductData;
            } else {
                throw new \ErrorException(
                    \Yii::t('app', 'Не установлена товарная карта для кода!'));
            }
        }

        throw new \ErrorException(
            \Yii::t('app', 'Не установлена товарная карта для кода!'));
    }

    /**
     * @param int $packType
     * @return int
     */
    private function calculateCodeFlag(int $packType): int
    {
        switch ($packType) {
            case self::TYPE_ITEM:
                $flag = self::PACK_FLAG;
                break;
            case self::TYPE_BOX:
                $flag = self::PACK_FLAG;
                break;
            case self::TYPE_PALLET:
                $flag = self::PACK_FLAG + self::PALLET_FLAG;
                break;
            default:
                $flag = 0;
                break;
        }

        if ($this->transferFlag) {
            $flag += self::TRANSFER_FLAG;
        }

        return $flag;
    }

    /**
     * @return int
     */
    private function getPackType(): int
    {
        $packType = self::TYPE_ITEM;

        if ($this->dataItems === null) {
            return $packType;
        }

        if (count($this->dataItems) > 0) {
            $packType = self::TYPE_BOX;
        }

        foreach ($this->dataItems as $pack) {
            if ($pack->dataItems === null) {
                return $packType;
            }

            if (count($pack->dataItems) > 0) {
                $packType = self::TYPE_PALLET;
            }
        }

        return $packType;
    }
}